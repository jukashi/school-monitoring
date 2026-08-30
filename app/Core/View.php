<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $viewFile = APP_ROOT . '/app/Views/' . $view . '.php';
        $layoutFile = APP_ROOT . '/app/Views/' . $layout . '.php';
        if (!is_file($viewFile) || !is_file($layoutFile)) {
            throw new RuntimeException('View not found: ' . $view);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();
        require $layoutFile;
    }
}

