<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migrator;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap/app.php';

try {
    $completed = Migrator::applyPending(Database::connection());
    echo $completed
        ? 'Applied: ' . implode(', ', $completed) . PHP_EOL
        : 'Database is already up to date.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
