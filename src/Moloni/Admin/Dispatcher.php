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
use Moloni\Services\LogService;
use Moloni\Support\Company;
use Moloni\Support\Context;
use Moloni\Support\Lang;
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

    /** Dashboard pages the router will render (keep in sync with pageData/pageTemplate). */
    private const PAGES = ['orders', 'documents', 'config', 'tools', 'logs'];

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
            // Streaming actions terminate the request themselves.
            if ($op === 'downloadPdf') {
                $this->streamPdf($this->request->queryInt('document_id'));
            }

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

            // 4. Logout.
            if ($action === 'logout') {
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

        $settings->set($settings::TAX_EXEMPTION, $this->request->hasPost('tax_exemption') ? '1' : '0');
        $settings->set($settings::AUTO_CREATE, $this->request->hasPost('auto_create') ? '1' : '0');

        foreach ([$settings::MEASUREMENT_UNIT_ID, $settings::PRODUCT_CATEGORY_ID] as $key) {
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

    private function streamPdf(int $documentId): void
    {
        try {
            $pdf = $this->container->documents()->downloadPdf($documentId);
        } catch (MoloniException $e) {
            LoggerFacade::error('PDF download failed.', ['document_id' => $documentId, 'error' => $e->getMessage()]);
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

        $data = $this->sharedData($page) + $this->pageData($page);
        $body = $this->container->template()->render($this->pageTemplate($page), $data);

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

            $selectable[] = $company->getAll();
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
                return [
                    'orders' => $this->container->orders()->getPendingOrders(),
                    'documentTypes' => DocumentType::all(),
                ];

            case 'documents':
                return [
                    'documents' => $this->container->orders()->getCreatedDocuments(),
                    'discarded' => $this->container->orders()->getDiscardedOrders(),
                ];

            case 'config':
                return [
                    'settings' => $this->container->settings()->all(),
                    'documentTypes' => DocumentType::all(),
                    'documentSets' => $this->safeDocumentSets(),
                ];

            case 'logs':
                return ['logs' => (new LogService())->getLogs($this->logFilters())];

            default:
                return [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function safeDocumentSets(): array
    {
        try {
            return $this->container->moloniClient()->getDocumentSets();
        } catch (Throwable $e) {
            LoggerFacade::warning('Could not load document sets.', ['error' => $e->getMessage()]);

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

    private function pageTemplate(string $page): string
    {
        // The orders page template file is named document.php per the spec.
        return $page === 'orders' ? 'document' : $page;
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
}
