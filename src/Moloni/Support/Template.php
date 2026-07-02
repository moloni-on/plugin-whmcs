<?php

declare(strict_types=1);

namespace Moloni\Support;

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

    public function __construct(string $basePath, string $moduleLink)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->moduleLink = $moduleLink;
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
        $csrf = fn (): string => $this->csrf();
        $postForm = fn (array $params, string $label, array $opts = []): string
            => $this->postForm($params, $label, $opts);

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
        return 'modules/addons/moloni_on/public/' . ltrim($path, '/');
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
