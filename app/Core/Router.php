<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler, ?string $permission = null): void
    {
        $this->add('GET', $path, $handler, $permission);
    }

    public function post(string $path, callable $handler, ?string $permission = null): void
    {
        $this->add('POST', $path, $handler, $permission);
    }

    private function add(string $method, string $path, callable $handler, ?string $permission): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'permission');
    }

    public function dispatch(string $method, string $uri): void
    {
        $configuredUrl = Env::get('APP_URL', '');
        $base = rtrim((string) parse_url($configuredUrl, PHP_URL_PATH), '/');
        if ($configuredUrl === '') {
            $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        }
        $path = '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        if (Auth::check() && Auth::requiresPasswordChange() && !in_array($path, ['/password/change', '/logout'], true)) {
            Auth::redirect('/password/change');
        }

        foreach ($this->routes as $route) {
            $pattern = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['path']) . '$#';
            if ($route['method'] !== $method || !preg_match($pattern, $path, $matches)) {
                continue;
            }
            if ($method === 'POST' && !Csrf::verify($_POST['_token'] ?? null)) {
                http_response_code(419);
                View::render('errors/message', ['title' => 'Session expired', 'message' => 'Please go back, refresh the page, and try again.']);
                return;
            }
            if ($route['permission'] !== null) {
                Auth::requireLogin();
                if (!Authorization::allows($route['permission'])) {
                    http_response_code(403);
                    View::render('errors/message', ['title' => 'Access denied', 'message' => 'You do not have permission to perform this action.']);
                    return;
                }
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            ($route['handler'])(...array_values($params));
            return;
        }

        http_response_code(404);
        View::render('errors/message', ['title' => 'Page not found', 'message' => 'The requested page does not exist.']);
    }
}
