<?php

declare(strict_types=1);

namespace Moloni\Admin;

use Moloni\Enums\DocumentType;
use Moloni\Exceptions\AuthException;
use Moloni\Exceptions\DocumentException;
use Moloni\Exceptions\MoloniException;
use Moloni\Exceptions\SkippedException;
use Moloni\Facades\LoggerFacade;
use Moloni\Models\Order;
use Moloni\Models\Whmcs;
use Moloni\Services\LogService;
use Moloni\Services\SettingsService;
use Moloni\Support\Company;
use Moloni\Support\Context;
use Moloni\Support\Lang;
use Moloni\Support\Platform;
use Moloni\Support\Request;
use Throwable;

/**
 * Front controller for the WHMCS admin addon page.
 *
 * Resolves auth state, runs the requested action and renders the matching
 * template wrapped in the shared layout. Mirrors the classic WHMCS addon
 * dispatch pattern (login -> company -> dashboard).
 */
class Dispatcher
{
    /** WHMCS addon module slug and its relative admin path. */
    private const MODULE_SLUG = 'moloni_on';
    private const MODULE_PATH = 'addonmodules.php?module=' . self::MODULE_SLUG;

    /** Dashboard pages the router will render (each maps to templates/<page>.php + pageData). */
    private const PAGES = ['orders', 'documents', 'discarded', 'config', 'tools', 'logs'];

    private Container $container;

    /** @var array<string,mixed> */
    private array $vars;

    private Request $request;

    private string $moduleLink;

    /** @var array<int,array{type:string,text:string}> */
    private array $messages = [];

    /**
     * @param array<string,mixed> $vars WHMCS module output vars.
     */
    public function __construct(Container $container, array $vars, ?Request $request = null)
    {
        $this->container = $container;
        $this->vars = $vars;
        $this->request = $request ?? Request::fromGlobals();
        $this->moduleLink = (string) ($vars['modulelink'] ?? self::MODULE_PATH);
    }

    /**
     * Handle the request and return HTML for the admin page.
     */
    public function dispatch(): string
    {
        Lang::boot((string) ($this->vars['adminLanguage'] ?? 'english') === 'portuguese' ? 'pt' : 'en');

        $action = $this->request->query('action');
        $op = $this->request->request('op');

        // Reject forged POSTs (state-changing operations are always POST).
        if ($this->request->isPost()) {
            $this->verifyCsrf();
        }

        try {
            // 1. Start the OAuth flow from the login form.
            if ($op === 'connect') {
                return $this->connect();
            }

            // 2. Require a valid OAuth session (completing the callback if any).
            if (!$this->resolveAuthenticatedState()) {
                return $this->renderStandalone('login');
            }

            // 3. Require a selected company.
            $companyPage = $this->ensureCompanySelected($op);

            if ($companyPage !== null) {
                return $companyPage;
            }

            // Document actions that hit the API and terminate/redirect the
            // request themselves. Placed after auth + company so the API client
            // has a valid token and company id.
            if ($op === 'downloadPdf') {
                $this->streamPdf($this->request->queryInt('document_id'));
            }

            if ($op === 'openDocument') {
                return $this->openDocument($this->request->queryInt('document_id'));
            }

            // 4. Logout. Keyed on the POST op so it inherits the CSRF check
            // above; a forged GET can no longer tear down the session.
            if ($op === 'logout') {
                $this->container->auth()->logout();

                return $this->renderStandalone('login');
            }

            // 5. Mutating operations.
            if ($op !== '') {
                $this->handleOperation($op);
            }
        } catch (AuthException $e) {
            $this->error($e->getMessage());

            return $this->renderStandalone('login');
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            LoggerFacade::error('Unhandled dispatcher error.', ['error' => $e->getMessage()]);
        }

        return $this->renderPage($action !== '' ? $action : 'orders');
    }

    /**
     * Resolve the current OAuth session, completing the authorization-code
     * callback when one is present — but only after validating the CSRF state
     * nonce issued when the flow started.
     */
    private function resolveAuthenticatedState(): bool
    {
        $auth = $this->container->auth();
        $authenticated = $auth->ensureAuthenticated();

        if (!$authenticated && $this->request->query('code') !== '') {
            if (!$auth->verifyState($this->request->query('state'))) {
                throw new AuthException(Lang::get('oauth_state_mismatch'));
            }

            $auth->exchangeCode($this->request->query('code'));
            $authenticated = $auth->ensureAuthenticated();
        }

        return $authenticated;
    }

    /**
     * Ensure a company is selected and loaded into the Context. Returns the
     * company-select page to render when none is chosen yet, or null once a
     * company is active.
     */
    private function ensureCompanySelected(string $op): ?string
    {
        $auth = $this->container->auth();

        if ($op === 'selectCompany') {
            $auth->selectCompany($this->request->postInt('company_id'));
        }

        if (!$auth->hasCompany()) {
            return $this->renderCompanySelect();
        }

        $auth->loadCompany();

        return null;
    }

    // ---- Operations -------------------------------------------------------

    private function handleOperation(string $op): void
    {
        switch ($op) {
            case 'createDocument':
                $this->createDocument($this->request->requestInt('order_id'), $this->request->request('document_type'));
                break;

            case 'bulkCreate':
                $this->bulkCreate();
                break;

            case 'discard':
                $this->container->orders()->discardOrder($this->request->requestInt('order_id'));
                $this->success(Lang::get('order_discarded'));
                break;

            case 'revert':
                $this->container->orders()->revertDiscard($this->request->requestInt('order_id'));
                $this->success(Lang::get('order_reverted'));
                break;

            case 'saveSettings':
                $this->saveSettings();
                break;

            case 'clearLogs':
                (new LogService())->clearLogs();
                $this->success(Lang::get('logs_cleared'));
                break;
        }
    }

    /**
     * Translate a submitted document type into the value the document service
     * expects: an empty selection means "use the configured default" (null).
     * This empty-string <-> null mapping lives here and nowhere else.
     */
    private function normaliseDocumentType(string $documentType): ?string
    {
        return $documentType !== '' ? $documentType : null;
    }

    private function createDocument(int $orderId, string $documentType): void
    {
        try {
            $documentId = $this->container->documents()
                ->createDocumentFromOrder($orderId, $this->normaliseDocumentType($documentType));
            $this->success(Lang::get('document_created', ['id' => $documentId]));
        } catch (SkippedException $e) {
            $this->success(Lang::get('document_skipped'));
        } catch (DocumentException $e) {
            $this->error(Lang::get('document_failed', ['error' => $e->getMessage()]));
        }
    }

    private function bulkCreate(): void
    {
        $orderIds = array_map('intval', $this->request->postArray('order_ids'));
        $documentType = $this->normaliseDocumentType($this->request->post('document_type'));

        if ($orderIds === []) {
            $this->error(Lang::get('no_orders_selected'));

            return;
        }

        $result = $this->container->documents()->bulkCreateDocuments($orderIds, $documentType);

        $this->success(Lang::get('bulk_result', [
            'created' => count($result['created']),
            'skipped' => count($result['skipped']),
            'failed' => count($result['failed']),
        ]));
    }

    private function saveSettings(): void
    {
        $settings = $this->container->settings();

        if ($this->request->hasPost('document_type')) {
            $settings->set($settings::DOCUMENT_TYPE, $this->request->post('document_type'));
        }

        if ($this->request->hasPost('document_status')) {
            $settings->set($settings::DOCUMENT_STATUS, $this->request->post('document_status'));
        }

        if ($this->request->hasPost('document_set_id')) {
            $settings->set($settings::DOCUMENT_SET_ID, $this->request->post('document_set_id'));
        }

        $settings->set($settings::AUTO_CREATE, $this->request->hasPost('auto_create') ? '1' : '0');
        $settings->set($settings::SEND_EMAIL, $this->request->hasPost('send_email') ? '1' : '0');

        $intKeys = [$settings::MEASUREMENT_UNIT_ID, $settings::PRODUCT_CATEGORY_ID, $settings::PAYMENT_METHOD_ID];

        foreach ($intKeys as $key) {
            if ($this->request->hasPost($key)) {
                $settings->set($key, (string) $this->request->postInt($key));
            }
        }

        if ($this->request->hasPost($settings::EXEMPTION_REASON)) {
            $settings->set($settings::EXEMPTION_REASON, trim($this->request->post($settings::EXEMPTION_REASON)));
        }

        if ($this->request->hasPost($settings::FISCAL_ZONE_BASED_ON)) {
            $settings->set($settings::FISCAL_ZONE_BASED_ON, $this->request->post($settings::FISCAL_ZONE_BASED_ON));
        }

        if ($this->request->hasPost($settings::VAT_FIELD)) {
            $settings->set($settings::VAT_FIELD, trim($this->request->post($settings::VAT_FIELD)));
        }

        if ($this->request->hasPost($settings::CUSTOM_REFERENCE)) {
            $settings->set($settings::CUSTOM_REFERENCE, trim($this->request->post($settings::CUSTOM_REFERENCE)));
        }

        LoggerFacade::info('Settings saved.');
        $this->success(Lang::get('settings_saved'));
    }

    private function connect(): string
    {
        $developerId = trim($this->request->post('developer_id'));
        $clientSecret = trim($this->request->post('client_secret'));

        if ($developerId === '' || $clientSecret === '') {
            $this->error(Lang::get('credentials_required'));

            return $this->renderStandalone('login');
        }

        $this->container->auth()->setClient($developerId, $clientSecret);
        $url = $this->container->auth()->authorizeUrl($this->absoluteModuleUrl());

        return $this->redirect($url);
    }

    /**
     * Redirect the admin to view the document on the Moloni ON website.
     */
    private function openDocument(int $documentId): string
    {
        try {
            $url = $this->container->documents()->documentViewUrl($documentId);
        } catch (MoloniException $e) {
            LoggerFacade::error('Open document failed.', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ] + $e->getData());
            $this->error(Lang::get('open_document_failed'));

            return $this->renderPage('documents');
        }

        return $this->redirect($url);
    }

    private function streamPdf(int $documentId): void
    {
        try {
            $pdf = $this->container->documents()->downloadPdf($documentId);
        } catch (MoloniException $e) {
            LoggerFacade::error('PDF download failed.', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ] + $e->getData());
            header('HTTP/1.1 500 Internal Server Error');
            echo Lang::get('pdf_download_failed');
            exit;
        }

        // Never let an API-supplied filename inject CRLF/quotes into the header.
        $filename = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $pdf['filename']);
        $filename = $filename !== '' ? $filename : ('document-' . $documentId . '.pdf');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf['content']));
        echo $pdf['content'];
        exit;
    }

    // ---- Rendering --------------------------------------------------------

    private function renderPage(string $page): string
    {
        $page = in_array($page, self::PAGES, true) ? $page : 'orders';

        // Build page data first: it may queue flash messages (e.g. a document
        // sets load failure) that sharedData() then snapshots for rendering.
        $pageData = $this->pageData($page);
        $data = $this->sharedData($page) + $pageData;
        $body = $this->container->template()->render($page, $data);

        return $this->renderLayout($body, $data, true);
    }

    private function renderStandalone(string $page): string
    {
        $data = $this->sharedData($page);
        $body = $this->container->template()->render($page, $data);

        return $this->renderLayout($body, $data);
    }

    private function renderCompanySelect(): string
    {
        try {
            $companies = $this->filterSelectableCompanies($this->container->moloniClient()->getCompanies());
        } catch (Throwable $e) {
            $companies = [];
            $this->error($e->getMessage());
        }

        $data = $this->sharedData('company') + ['companies' => $companies];
        $body = $this->container->template()->render('company', $data);

        return $this->renderLayout($body, $data);
    }

    /**
     * Wrap page body HTML in the shared header/messages/footer chrome. The
     * dashboard pages also get the navigation bar (between header and messages);
     * the standalone login/company screens do not.
     *
     * @param array<string,mixed> $data
     */
    private function renderLayout(string $body, array $data, bool $withNavbar = false): string
    {
        $tpl = $this->container->template();

        $html = $tpl->render('Blocks/header', $data);

        if ($withNavbar) {
            $html .= $tpl->render('Blocks/navbar', $data);
        }

        return $html
            . $tpl->render('Blocks/messages', $data)
            . $body
            . $tpl->render('Blocks/footer', $data);
    }

    /**
     * Keep only companies the user can actually connect: confirmed and with the
     * API client add-on purchased (feature-gated via the company's permissions).
     *
     * @param array<int,array<string,mixed>> $companies
     * @return array<int,array<string,mixed>>
     */
    private function filterSelectableCompanies(array $companies): array
    {
        $selectable = [];

        foreach ($companies as $row) {
            $company = new Company($row);

            if ($company->getCompanyId() <= 0 || !$company->get('isConfirmed') || !$company->hasApiClient()) {
                continue;
            }

            $row = $company->getAll();
            $img = trim((string) ($row['img1'] ?? ''));
            $row['logo'] = $img !== '' ? Platform::MEDIA_API_URL . $img : '';

            $selectable[] = $row;
        }

        return $selectable;
    }

    /**
     * @return array<string,mixed>
     */
    private function pageData(string $page): array
    {
        switch ($page) {
            case 'orders':
                $orders = $this->container->orders()->getPendingOrders($this->pageParam('page'));

                return [
                    'orders' => $orders->items(),
                    'ordersPagination' => $orders,
                    'documentTypes' => DocumentType::all(),
                    'selectedDocumentType' => $this->container->settings()->get(
                        SettingsService::DOCUMENT_TYPE
                    ),
                ];

            case 'documents':
                $documents = $this->container->orders()->getCreatedDocuments($this->pageParam('page'));

                return [
                    'documents' => $documents->items(),
                    'documentsPagination' => $documents,
                ];

            case 'discarded':
                $discarded = $this->container->orders()->getDiscardedOrders($this->pageParam('page'));

                return [
                    'discarded' => $discarded->items(),
                    'discardedPagination' => $discarded,
                ];

            case 'config':
                return $this->configPageData();

            case 'logs':
                $filters = $this->logFilters();
                $logs = (new LogService())->getLogs($filters, $this->pageParam('page'));

                return [
                    'logs' => $logs->items(),
                    'logsPagination' => $logs,
                    'logFilters' => $filters,
                ];

            default:
                return [];
        }
    }

    /**
     * Read a 1-based page number from the query string, never below 1.
     */
    private function pageParam(string $key): int
    {
        return max(1, $this->request->queryInt($key, 1));
    }

    /**
     * Config-page data. Each Moloni ON list is fetched defensively so a single
     * failing lookup never blanks the whole page; the exemption reasons come
     * from the already-loaded company payload.
     *
     * @return array<string,mixed>
     */
    private function configPageData(): array
    {
        $client = $this->container->moloniClient();
        $company = Context::company();

        return [
            'settings' => $this->container->settings()->all(),
            'documentTypes' => DocumentType::all(),
            'documentSets' => $this->safeList(
                'document sets',
                [$client, 'getDocumentSets'],
                'document_sets_unavailable'
            ),
            'measurementUnits' => $this->safeList('measurement units', [$client, 'getMeasurementUnits']),
            'productCategories' => $this->safeList('product categories', [$client, 'getProductCategories']),
            'paymentMethods' => $this->safeList('payment methods', [$client, 'getPaymentMethods']),
            'exemptionReasons' => $company ? $company->getExemptionReasons() : [],
            'productCustomFields' => Whmcs::productCustomFieldNames(),
        ];
    }

    /**
     * Fetch a Moloni ON list, degrading to [] (and a log entry) on failure so
     * the page still renders. When $userMessage is given, a warning alert is
     * also shown to the admin.
     *
     * @param callable():array<int,array<string,mixed>> $fetch
     * @return array<int,array<string,mixed>>
     */
    private function safeList(string $what, callable $fetch, ?string $userMessage = null): array
    {
        try {
            return $fetch();
        } catch (Throwable $e) {
            $context = ['error' => $e->getMessage()];

            if ($e instanceof MoloniException) {
                $context += $e->getData();
            }

            LoggerFacade::warning('Could not load ' . $what . '.', $context);

            if ($userMessage !== null) {
                $this->warning(Lang::get($userMessage));
            }

            return [];
        }
    }

    /**
     * @return array{level?:string,order_id?:int}
     */
    private function logFilters(): array
    {
        $filters = [];

        if ($this->request->query('level') !== '') {
            $filters['level'] = $this->request->query('level');
        }

        if ($this->request->queryInt('order_id') > 0) {
            $filters['order_id'] = $this->request->queryInt('order_id');
        }

        return $filters;
    }

    /**
     * @return array<string,mixed>
     */
    private function sharedData(string $activeTab): array
    {
        return [
            'activeTab' => $activeTab,
            'messages' => $this->messages,
            'company' => Context::company() ? Context::company()->getAll() : [],
            'moduleLink' => $this->moduleLink,
            'orderStatuses' => [Order::STATUS_PENDING, Order::STATUS_FAILED],
        ];
    }


    private function redirect(string $url): string
    {
        $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $link = '<a href="' . $safe . '">' . Lang::get('redirect_continue') . '</a>';

        return '<meta http-equiv="refresh" content="0;url=' . $safe . '">'
            . '<script>window.location.href=' . json_encode($url) . ';</script>'
            . '<p>' . Lang::get('redirecting') . ' ' . $link . '.</p>';
    }

    private function absoluteModuleUrl(): string
    {
        $https = $this->request->server('HTTPS');
        $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
        $host = $this->request->server('HTTP_HOST');
        $dir = rtrim(dirname($this->request->server('PHP_SELF')), '/');

        return $scheme . '://' . $host . $dir . '/' . self::MODULE_PATH;
    }

    /**
     * Validate the WHMCS CSRF token on POST requests. WHMCS' check_token()
     * halts the request itself on failure; it is a no-op outside WHMCS.
     */
    private function verifyCsrf(): void
    {
        if (function_exists('check_token')) {
            check_token();
        }
    }

    private function success(string $text): void
    {
        $this->messages[] = ['type' => 'success', 'text' => $text];
    }

    private function error(string $text): void
    {
        $this->messages[] = ['type' => 'danger', 'text' => $text];
    }

    private function warning(string $text): void
    {
        $this->messages[] = ['type' => 'warning', 'text' => $text];
    }
}
