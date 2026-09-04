<?php

declare(strict_types=1);

final class Http {
    public static function json(array $data, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    public static function fail(string $message, int $status = 500): never {
        self::json(['success' => false, 'error' => $message], $status);
    }
}
