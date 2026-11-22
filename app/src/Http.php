<?php
declare(strict_types=1);
namespace App;

final class Request
{
    public static function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }

    public static function path(): string
    {
        $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $p = '/' . trim(rawurldecode($p), '/');
        return $p === '/' ? '/' : $p;
    }

    private static ?array $json = null;

    public static function json(): array
    {
        if (self::$json !== null) return self::$json;
        $raw = file_get_contents('php://input') ?: '';
        if (strlen($raw) > 200_000) throw new UserError('Request too large.');
        return self::$json = (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json') ? (json_decode($raw, true) ?: []) : []);
    }

    public static function setJson(array $d): void { self::$json = $d; }

    public static function input(string $k, mixed $default = null): mixed
    {
        $j = self::json();
        return $_POST[$k] ?? $j[$k] ?? $_GET[$k] ?? $default;
    }

    public static function str(string $k, int $max = 2000): string { return mb_substr(trim((string)(self::input($k, '') ?? '')), 0, $max); }

    public static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_starts_with(self::path(), '/api/');
    }

    /** CSRF check for every state-changing request (form field or X-CSRF-Token header). */
    public static function requireCsrf(): void
    {
        $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (self::json()['_csrf'] ?? null);
        if (!Csrf::verify($t)) {
            Audit::security('csrf.failed', self::path());
            throw new HttpError(419, 'Your session expired. Refresh the page and try again.');
        }
    }
}

final class HttpError extends \RuntimeException
{
    public function __construct(public int $status, string $message) { parent::__construct($message); }
}

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, array $opts = []): void
    {
        $re = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[A-Za-z0-9_\-]+)', $pattern) . '$#';
        $this->routes[] = [$method, $re, $handler, $opts];
    }

    public function get(string $p, callable $h, array $o = []): void { $this->add('GET', $p, $h, $o); }
    public function post(string $p, callable $h, array $o = []): void { $this->add('POST', $p, $h, $o); }

    public function dispatch(string $method, string $path): void
    {
        $allowed = false;
        foreach ($this->routes as [$m, $re, $h, $o]) {
            if (!preg_match($re, $path, $mt)) continue;
            $allowed = true;
            if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) continue;
            $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
            try {
                if ($method === 'POST' && empty($o['nocsrf'])) Request::requireCsrf();
                if (isset($o['perm'])) self::guard($o['perm']);
                if (!empty($o['auth'])) self::guardLogin();
                $h($params);
            } catch (HttpError $e) {
                self::fail($e->status, $e->getMessage());
            } catch (UserError $e) {
                if (Request::wantsJson()) View::json(['ok' => false, 'error' => $e->getMessage()], 422);
                else { Session::flash('error', $e->getMessage()); View::redirect($_SERVER['HTTP_REFERER'] ?? '/'); }
            }
            return;
        }
        self::fail($allowed ? 405 : 404, $allowed ? 'Method not allowed.' : 'Page not found.');
    }

    public static function guardLogin(): void
    {
        if (!Auth::user()) {
            if (Request::wantsJson()) throw new HttpError(401, 'Please sign in.');
            Session::start(); $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/portal';
            View::redirect('/login');
        }
    }

    public static function guard(string $perm): void
    {
        self::guardLogin();
        if (!Rbac::can(Auth::user(), $perm)) { Audit::security('authz.denied', $perm . ' ' . Request::path()); throw new HttpError(403, 'You do not have access to this area.'); }
    }

    public static function fail(int $status, string $msg): void
    {
        if (Request::wantsJson()) { View::json(['ok' => false, 'error' => $msg], $status); return; }
        http_response_code($status);
        View::page('public/error', ['title' => $status === 404 ? 'Page not found' : 'Error', 'status' => $status, 'message' => $msg]);
    }
}

final class View
{
    public static array $shared = [];

    public static function render(string $tpl, array $data = []): string
    {
        $file = APP_ROOT . '/views/' . $tpl . '.php';
        if (!is_file($file)) throw new \RuntimeException("View not found: {$tpl}");
        extract(self::$shared + $data, EXTR_SKIP);
        ob_start();
        try { include $file; } catch (\Throwable $e) { ob_end_clean(); throw $e; }
        return (string)ob_get_clean();
    }

    /** Render a page inside a layout chosen by its folder (public / portal / admin). */
    public static function page(string $tpl, array $data = []): void
    {
        $layout = match (explode('/', $tpl)[0]) { 'admin' => 'layouts/admin', 'portal' => 'layouts/portal', default => 'layouts/public' };
        $data['content'] = self::render($tpl, $data);
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        echo self::render($layout, $data);
    }

    public static function json(array $data, int $status = 200): void
    {
        if (!headers_sent()) { http_response_code($status); header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store'); }
        echo jenc($data);
    }

    public static function redirect(string $to, int $code = 303): void
    {
        // Only allow same-site relative redirects (open-redirect protection).
        if (!str_starts_with($to, '/') || str_starts_with($to, '//')) {
            $p = parse_url($to);
            $to = (($p['host'] ?? '') === parse_url(url('/'), PHP_URL_HOST)) ? (($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '')) : '/';
        }
        if (PHP_SAPI === 'cli') { View::$shared['_redirect'] = $to; throw new Redirected($to); }
        header('Location: ' . $to, true, $code);
        exit;
    }
}

final class Redirected extends \RuntimeException {}
