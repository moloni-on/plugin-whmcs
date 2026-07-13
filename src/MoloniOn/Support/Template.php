<?php

declare(strict_types=1);

namespace MoloniOn\Support;

/**
 * Renders a PHP template from /templates with an isolated data scope.
 *
 * Templates receive their variables as locals (extracted from $data) plus a
 * $lang closure for translations and helpers for building module URLs.
 */
final class Template
{
    private string $basePath;

    private string $moduleLink;

    private string $assetBase;

    public function __construct(string $basePath, string $moduleLink, string $assetBase = '')
    {
        $this->basePath = rtrim($basePath, '/');
        $this->moduleLink = $moduleLink;
        $this->assetBase = rtrim($assetBase, '/');
    }

    /**
     * Render a template file and return its output as a string.
     *
     * @param array<string,mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->basePath . '/' . $template . '.php';

        if (!is_file($file)) {
            return '';
        }

        // Helpers made available to every template.
        $lang = static fn (string $key, array $r = []): string => Lang::get($key, $r);
        $url = fn (array $params = []): string => $this->url($params);
        $asset = fn (string $path): string => $this->asset($path);
        $e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        // WHMCS admin order-view link, relative to the admin directory the addon
        // page is already served from.
        $orderUrl = static fn (int $orderId): string => 'orders.php?action=view&id=' . $orderId;
        // Format an amount with a row's client currency (prefix/suffix, code as
        // fallback). $row is any object carrying currency_prefix / currency_suffix
        // / currency_code; missing fields degrade to the bare formatted number.
        $money = static function (float $amount, object $row): string {
            $formatted = number_format($amount, 2);
            $prefix = trim((string) ($row->currency_prefix ?? ''));
            $suffix = trim((string) ($row->currency_suffix ?? ''));
            $code = trim((string) ($row->currency_code ?? ''));

            if ($prefix === '' && $suffix === '') {
                return $code !== '' ? $formatted . ' ' . $code : $formatted;
            }

            return $prefix . $formatted . ($suffix !== '' ? ' ' . $suffix : '');
        };
        $csrf = fn (): string => $this->csrf();
        $postForm = fn (array $params, string $label, array $opts = []): string
            => $this->postForm($params, $label, $opts);
        $paginate = fn (Paginator $paginator, array $baseParams = [], string $pageParam = 'page'): string
            => $this->render('Blocks/pagination', [
                'paginator' => $paginator,
                'baseParams' => $baseParams,
                'pageParam' => $pageParam,
            ]);

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    /**
     * Build a link to the module page with the given query params.
     *
     * @param array<string,string|int> $params
     */
    public function url(array $params = []): string
    {
        $query = http_build_query($params);

        return $this->moduleLink . ($query !== '' ? '&' . $query : '');
    }

    public function asset(string $path): string
    {
        $relative = 'modules/addons/moloni_on/public/' . ltrim($path, '/');

        // Admin pages are served from /admin/, but addon assets live at the web
        // root, so a relative path would 404 under /admin/. Prepend the WHMCS
        // system URL when available to produce an absolute URL.
        $url = $this->assetBase !== '' ? $this->assetBase . '/' . $relative : $relative;

        // Cache-bust on the module version so browsers refetch CSS/JS after an
        // upgrade instead of serving a stale copy.
        $separator = strpos($url, '?') === false ? '?' : '&';

        return $url . $separator . 'v=' . rawurlencode(Platform::VERSION);
    }

    public function partial(string $template, array $data = []): void
    {
        echo $this->render('Blocks/' . $template, $data);
    }

    /**
     * WHMCS CSRF hidden field (empty outside a WHMCS runtime).
     */
    public function csrf(): string
    {
        return function_exists('generate_token') ? (string) generate_token() : '';
    }

    /**
     * Render a small inline POST form + submit button, including the CSRF token.
     * Used for state-changing row actions so they are never GET requests.
     *
     * @param array<string,string|int> $params  Hidden fields (must include "op").
     * @param array{class?:string,confirm?:string} $opts
     */
    public function postForm(array $params, string $label, array $opts = []): string
    {
        $esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $class = $opts['class'] ?? 'btn btn-sm btn-secondary';
        $confirm = $opts['confirm'] ?? '';
        $confirmAttr = $confirm !== '' ? ' data-moloni-confirm="' . $esc($confirm) . '"' : '';

        $hidden = '';
        foreach ($params as $name => $value) {
            $hidden .= '<input type="hidden" name="' . $esc($name) . '" value="' . $esc($value) . '">';
        }

        return '<form method="post" action="' . $esc($this->moduleLink) . '" class="moloni-on__inline-form">'
            . $this->csrf()
            . $hidden
            . '<button type="submit" class="' . $esc($class) . '"' . $confirmAttr . '>' . $esc($label) . '</button>'
            . '</form>';
    }
}
