<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class AnnouncementService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function publish(int $userId, array $input): int
    {
        $title = trim((string) ($input['title'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        $audience = trim((string) ($input['audience'] ?? 'all'));

        if ($title === '' || $body === '') {
            throw new InvalidArgumentException('Announcement title and message are required.');
        }
        if (mb_strlen($title) > 180) {
            throw new InvalidArgumentException('Announcement title must be 180 characters or fewer.');
        }
        if (!in_array($audience, ['all', 'students', 'teachers', 'staff'], true)) {
            throw new InvalidArgumentException('Choose a valid announcement audience.');
        }

        $publishedAt = $this->dateTime((string) ($input['published_at'] ?? ''), 'Publish at') ?? date('Y-m-d H:i:s');
        $expiresAt = $this->dateTime((string) ($input['expires_at'] ?? ''), 'Expires at');
        if ($expiresAt !== null && $expiresAt <= $publishedAt) {
            throw new InvalidArgumentException('Expires at must be later than Publish at.');
        }

        $statement = $this->pdo->prepare('INSERT INTO announcements(title,body,audience,published_at,expires_at,created_by) VALUES(?,?,?,?,?,?)');
        $statement->execute([$title, $body, $audience, $publishedAt, $expiresAt, $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function dateTime(string $value, string $label): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        throw new InvalidArgumentException($label . ' must be a valid date and time.');
    }
}
