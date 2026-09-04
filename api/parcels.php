<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(static function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    Http::fail("PHP error: $message in $file:$line");
});

set_exception_handler(static function (Throwable $e) {
    Http::fail('PHP exception: ' . $e->getMessage());
});

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);

if ($lat === false || $lat === null || $lng === false || $lng === null) {
    Http::fail('Neplatné souřadnice.', 400);
}

Http::json(['success' => true, 'parcel' => ParcelRepository::findByPoint($lat, $lng)]);
