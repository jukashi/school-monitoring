<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Database;
use App\Services\AnnouncementService;

function verifyAnnouncement(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Database::connection();
$userId = (int) $pdo->query('SELECT id FROM users WHERE status="active" ORDER BY id LIMIT 1')->fetchColumn();
verifyAnnouncement($userId > 0, 'An active user is required for the announcement test.');
$service = new AnnouncementService($pdo);
$createdIds = [];

try {
    $createdIds[] = $service->publish($userId, [
        'title' => 'Immediate bulletin test',
        'body' => 'This announcement should be visible immediately.',
        'audience' => 'all',
        'published_at' => '',
        'expires_at' => '',
    ]);

    $visible = $pdo->prepare('SELECT COUNT(*) FROM announcements WHERE id=? AND audience="all" AND published_at<=NOW() AND (expires_at IS NULL OR expires_at>=NOW())');
    $visible->execute([$createdIds[0]]);
    verifyAnnouncement((int) $visible->fetchColumn() === 1, 'A newly published announcement is not visible to the dashboard query.');

    $before = (int) $pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
    try {
        $service->publish($userId, [
            'title' => 'Invalid bulletin test',
            'body' => 'This announcement must not be saved.',
            'audience' => 'all',
            'published_at' => '2026-09-09T14:32',
            'expires_at' => '2026-09-09T14:30',
        ]);
        throw new RuntimeException('An invalid announcement window was accepted.');
    } catch (InvalidArgumentException $exception) {
        verifyAnnouncement($exception->getMessage() === 'Expires at must be later than Publish at.', 'The invalid date-window message is unclear.');
    }
    verifyAnnouncement((int) $pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn() === $before, 'An invalid announcement was saved.');
    verifyAnnouncement((int) $pdo->query('SELECT COUNT(*) FROM announcements WHERE published_at IS NOT NULL AND expires_at IS NOT NULL AND expires_at<=published_at')->fetchColumn() === 0, 'An invalid stored announcement window remains unrepaired.');

    echo "Announcement publish test passed.\n";
} finally {
    if ($createdIds) {
        $placeholders = implode(',', array_fill(0, count($createdIds), '?'));
        $pdo->prepare("DELETE FROM announcements WHERE id IN ({$placeholders})")->execute($createdIds);
    }
}
