<?php

declare(strict_types=1);

final class Config
{
    private static ?array $values = null;

    public static function get(): array {
        return self::$values ??= require dirname(__DIR__) . '/config/appConfig.php';
    }

    public static function mongoUri(): string {
        return self::get()['mongo_uri'];
    }

    public static function mongoDb(): string {
        return self::get()['mongo_db'];
    }

    public static function dataDir(): string {
        return rtrim(self::get()['data_dir'], '/\\');
    }

    public static function zonings(): array {
        return self::get()['zonings'];
    }

    public static function datasetUrl(string $code): string {
        return sprintf(self::get()['dataset_url'], $code);
    }

    public static function minParcelZoom(): int {
        return (int) self::get()['min_parcel_zoom'];
    }

    public static function bboxLimit(): int {
        return (int) self::get()['bbox_limit'];
    }
}
