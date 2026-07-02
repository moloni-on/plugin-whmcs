<?php

declare(strict_types=1);

namespace Moloni\Admin;

use Moloni\Enums\DocumentType;
use Moloni\Exceptions\AuthException;
use Moloni\Exceptions\DocumentException;
use Moloni\Exceptions\MoloniException;
use Moloni\Facades\LoggerFacade;
use Moloni\Models\Order;
use Moloni\Services\LogService;
use Moloni\Support\Context;
use Moloni\Support\Lang;
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
    private Container $container;

    /** @var array<string,mixed> */
    private array $vars;

    private string $moduleLink;

    /** @var array<int,array{type:string,text:string}> */
    private array $messages = [];

    /**
     * @param array<string,mixed> $vars WHMCS module output vars.
     */
    public function __construct(Container $container, array $vars)
    {
        $this->container = $container;
        $this->vars = $vars;
        $this->moduleLink = (string) ($vars['modulelink'] ?? 'addonmodules.php?module=moloni_on');
    }

    /**
     * Handle the request and return HTML for the admin page.
     */
    public function dispatch(): string
    {
        Lang::boot((string) ($this->vars['adminLanguage'] ?? 'english') === 'portuguese' ? 'pt' : 'en');

        $action = (string) ($_GET['action'] ?? '');
        $op = (string) ($_REQUEST['op'] ?? '');

        // Reject forged POSTs (state-changing operations are always POST).
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->verifyCsrf();
        }

        try {
            // Streaming actions terminate the request themselves.
            if ($op === 'downloadPdf') {
                $this->streamPdf((int) ($_GET['document_id'] ?? 0));
            }

            // 1. Start the OAuth flow from the login form.
            if ($op === 'connect') {
                return $this->connect();
            }

            $authenticated = $this->container->auth()->ensureAuthenticated();

            // 2. Complete the OAuth callback (code exchange). Only when not
            // already authenticated, and only after validating the CSRF state
            // nonce we issued when starting the flow.
            if (!$authenticated && isset($_GET['code'])) {
                if (!$this->container->auth()->verifyState((string) ($_GET['state'] ?? ''))) {
                    throw new AuthException(Lang::get('oauth_state_mismatch'));
                }

                $this->container->auth()->exchangeCode((string) $_GET['code']);
                $authenticated = $this->container->auth()->ensureAuthenticated();
            }

            // 3. Require a valid session.
            if (!$authenticated) {
                return $this->renderStandalone('login');
            }

            // 4. Company selection.
            if ($op === 'selectCompany') {
                $this->container->auth()->selectCompany((int) ($_POST['company_id'] ?? 0));
            }

            if (!$this->container->auth()->hasCompany()) {
                return $this->renderCompanySelect();
            }

            $this->container->auth()->loadCompany();

            // 5. Logout.
            if ($action === 'logout') {
                $this->container->auth()->logout();

                return $this->renderStandalone('login');
            }

            // 6. Mutating operations.
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

    // ---- Operations -------------------------------------------------------

    private function handleOperation(string $op): void
    {
        switch ($op) {
            case 'createDocument':
                $this->createDocument((int) ($_REQUEST['order_id'] ?? 0), (string) ($_REQUEST['document_type'] ?? ''));
                break;

            case 'bulkCreate':
                $this->bulkCreate();
                break;

            case 'discard':
                $this->container->orders()->discardOrder((int) ($_REQUEST['order_id'] ?? 0));
                $this->success(Lang::get('order_discarded'));
                break;

            case 'revert':
                $this->container->orders()->revertDiscard((int) ($_REQUEST['order_id'] ?? 0));
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

    private function createDocument(int $orderId, string $documentType): void
    {
        try {
            $documentId = $this->container->documents()->createDocumentFromOrder($orderId, $documentType ?: null);
            $this->success(Lang::get('document_created', ['id' => $documentId]));
        } catch (DocumentException $e) {
            $this->error(Lang::get('document_failed', ['error' => $e->getMessage()]));
        }
    }

    private function bulkCreate(): void
    {
        $orderIds = array_map('intval', (array) ($_POST['order_ids'] ?? []));
        $documentType = (string) ($_POST['document_type'] ?? '');

        if ($orderIds === []) {
            $this->error(Lang::get('no_orders_selected'));

            return;
        }

        $result = $this->container->documents()->bulkCreateDocuments($orderIds, $documentType ?: null);

        $this->success(Lang::get('bulk_result', [
            'created' => count($result['created']),
            'failed' => count($result['failed']),
        ]));
    }

    private function saveSettings(): void
    {
        $settings = $this->container->settings();

        if (isset($_POST['document_type'])) {
            $settings->set($settings::DOCUMENT_TYPE, (string) $_POST['document_type']);
        }

        if (isset($_POST['document_status'])) {
            $settings->set($settings::DOCUMENT_STATUS, (string) $_POST['document_status']);
        }

        if (isset($_POST['document_set_id'])) {
            $settings->set($settings::DOCUMENT_SET_ID, (string) $_POST['document_set_id']);
        }

        $settings->set($settings::TAX_EXEMPTION, isset($_POST['tax_exemption']) ? '1' : '0');
        $settings->set($settings::AUTO_CREATE, isset($_POST['auto_create']) ? '1' : '0');

        foreach ([$settings::MEASUREMENT_UNIT_ID, $settings::PRODUCT_CATEGORY_ID] as $key) {
            if (isset($_POST[$key])) {
                $settings->set($key, (string) (int) $_POST[$key]);
            }
        }

        if (isset($_POST[$settings::EXEMPTION_REASON])) {
            $settings->set($settings::EXEMPTION_REASON, trim((string) $_POST[$settings::EXEMPTION_REASON]));
        }

        LoggerFacade::info('Settings saved.');
        $this->success(Lang::get('settings_saved'));
    }

    private function connect(): string
    {
        $developerId = trim((string) ($_POST['developer_id'] ?? ''));
        $clientSecret = trim((string) ($_POST['client_secret'] ?? ''));

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
        $valid = ['orders', 'documents', 'config', 'tools', 'logs'];
        $page = in_array($page, $valid, true) ? $page : 'orders';

        $data = $this->sharedData($page);
        $data += $this->pageData($page);

        $tpl = $this->container->template();

        return $tpl->render('Blocks/header', $data)
            . $tpl->render('Blocks/navbar', $data)
            . $tpl->render('Blocks/messages', $data)
            . $tpl->render($this->pageTemplate($page), $data)
            . $tpl->render('Blocks/footer', $data);
    }

    private function renderStandalone(string $page): string
    {
        $data = $this->sharedData($page);
        $tpl = $this->container->template();

        return $tpl->render('Blocks/header', $data)
            . $tpl->render('Blocks/messages', $data)
            . $tpl->render($page, $data)
            . $tpl->render('Blocks/footer', $data);
    }

    private function renderCompanySelect(): string
    {
        try {
            $companies = $this->container->moloniClient()->getCompanies();
        } catch (Throwable $e) {
            $companies = [];
            $this->error($e->getMessage());
        }

        $data = $this->sharedData('company') + ['companies' => $companies];
        $tpl = $this->container->template();

        return $tpl->render('Blocks/header', $data)
            . $tpl->render('Blocks/messages', $data)
            . $tpl->render('company', $data)
            . $tpl->render('Blocks/footer', $data);
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

        if (!empty($_GET['level'])) {
            $filters['level'] = (string) $_GET['level'];
        }

        if (!empty($_GET['order_id'])) {
            $filters['order_id'] = (int) $_GET['order_id'];
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
            'company' => Context::$company,
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
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $dir = rtrim(dirname((string) ($_SERVER['PHP_SELF'] ?? '')), '/');

        return $scheme . '://' . $host . $dir . '/addonmodules.php?module=moloni_on';
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
