<?php

declare(strict_types=1);

namespace MoloniOn\Support;

/**
 * A thin, typed view over the PHP request superglobals.
 *
 * Wrapping the superglobals keeps the {@see \MoloniOn\Admin\Dispatcher} free of
 * scattered `(int) ($_GET[...] ?? 0)` casts, centralises the input parsing and
 * — because it can be constructed from arbitrary arrays — makes the dispatcher
 * unit-testable without touching global state.
 */
final class Request
{
    /** @var array<string,mixed> */
    private array $get;

    /** @var array<string,mixed> */
    private array $post;

    /** @var array<string,mixed> */
    private array $request;

    /** @var array<string,mixed> */
    private array $server;

    /**
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $server
     */
    public function __construct(array $get, array $post, array $server)
    {
        $this->get = $get;
        $this->post = $post;
        // Mirror the default $_REQUEST (request_order "GP"): POST overrides GET.
        $this->request = $post + $get;
        $this->server = $server;
    }

    public static function fromGlobals(): self
    {
        return new self($_GET ?? [], $_POST ?? [], $_SERVER ?? []);
    }

    public function query(string $key, string $default = ''): string
    {
        return isset($this->get[$key]) ? (string) $this->get[$key] : $default;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        return isset($this->get[$key]) ? (int) $this->get[$key] : $default;
    }

    public function post(string $key, string $default = ''): string
    {
        return isset($this->post[$key]) ? (string) $this->post[$key] : $default;
    }

    public function postInt(string $key, int $default = 0): int
    {
        return isset($this->post[$key]) ? (int) $this->post[$key] : $default;
    }

    /**
     * Whether a POST field was submitted at all (used for checkbox toggles,
     * where absence is meaningful).
     */
    public function hasPost(string $key): bool
    {
        return isset($this->post[$key]);
    }

    /**
     * @return array<int|string,mixed>
     */
    public function postArray(string $key): array
    {
        return isset($this->post[$key]) ? (array) $this->post[$key] : [];
    }

    public function request(string $key, string $default = ''): string
    {
        return isset($this->request[$key]) ? (string) $this->request[$key] : $default;
    }

    public function requestInt(string $key, int $default = 0): int
    {
        return isset($this->request[$key]) ? (int) $this->request[$key] : $default;
    }

    public function server(string $key, string $default = ''): string
    {
        return isset($this->server[$key]) ? (string) $this->server[$key] : $default;
    }

    public function isPost(): bool
    {
        return strtoupper($this->server('REQUEST_METHOD', 'GET')) === 'POST';
    }
}
