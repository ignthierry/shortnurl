<?php

declare(strict_types=1);

final class Shortener
{
    private const CODE_LENGTH = 6;
    private const CHARSET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public static function generateCode(): string
    {
        $max = strlen(self::CHARSET) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CHARSET[random_int(0, $max)];
        }

        return $code;
    }

    public static function shorten(string $url): array
    {
        $url = self::sanitizeUrl($url);

        if (!$url) {
            return ['success' => false, 'error' => 'Please enter a valid URL.'];
        }

        $db = Database::connection();

        $check = $db->prepare('SELECT short_code FROM urls WHERE original_url = :url LIMIT 1');
        $check->execute([':url' => $url]);
        $existing = $check->fetch();

        if ($existing) {
            return [
                'success'   => true,
                'short_code' => $existing['short_code'],
                'original_url' => $url,
                'duplicate' => true,
            ];
        }

        $attempts = 0;

        while ($attempts < 10) {
            $code = self::generateCode();

            $insert = $db->prepare(
                'INSERT INTO urls (original_url, short_code) VALUES (:url, :code)'
            );

            try {
                $insert->execute([':url' => $url, ':code' => $code]);

                return [
                    'success'     => true,
                    'short_code'  => $code,
                    'original_url' => $url,
                    'duplicate'   => false,
                ];
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    $attempts++;
                    continue;
                }
                throw $e;
            }
        }

        return ['success' => false, 'error' => 'Could not generate a unique code. Please try again.'];
    }

    public static function redirect(string $code): void
    {
        $db = Database::connection();

        $stmt = $db->prepare('SELECT id, original_url FROM urls WHERE short_code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            exit('Short link not found.');
        }

        $update = $db->prepare('UPDATE urls SET click_count = click_count + 1 WHERE id = :id');
        $update->execute([':id' => $row['id']]);

        header('Location: ' . $row['original_url'], true, 302);
        exit;
    }

    public static function recent(int $limit = 10): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT original_url, short_code, click_count, created_at 
             FROM urls 
             ORDER BY created_at DESC 
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);

        if ($url === false || $url === '') {
            return '';
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $url;
    }
}
