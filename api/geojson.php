<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    Http::fail("PHP error: $message in $file:$line");
});

set_exception_handler(function (Throwable $e) {
    Http::fail('PHP exception: ' . $e->getMessage());
});

$type = isset($_GET['type']) ? $_GET['type'] : 'parcels';
$bbox = isset($_GET['bbox']) ? $_GET['bbox'] : null;

if ($type == 'zonings' && ($bbox == null || $bbox == '')) {
    Http::json(['success' => true, 'truncated' => false, 'geojson' => ParcelRepository::toFeatureCollection(ParcelRepository::zonings(), 'zoning')]);
    exit;
}

$parts = array_map('trim', explode(',', (string)$bbox));

if (count($parts) != 4) {
    Http::fail('Neplatný bbox. Očekáván formát minLng,minLat,maxLng,maxLat.', 400);
    exit;
}

$minLng = (float)$parts[0];
$minLat = (float)$parts[1];
$maxLng = (float)$parts[2];
$maxLat = (float)$parts[3];

if ($minLng >= $maxLng || $minLat >= $maxLat) {
    Http::fail('Neplatný bbox.', 400);
    exit;
}

if ($type == 'zonings') {
    $documents = ParcelRepository::zoningsInBbox($minLng, $minLat, $maxLng, $maxLat);
    Http::json(['success' => true, 'truncated' => false, 'geojson' => ParcelRepository::toFeatureCollection($documents, 'zoning')]);
    exit;
}

if ($type != 'parcels') {
    Http::fail('Neplatný typ vrstvy.', 400);
    exit;
}

$limit = Config::bboxLimit();
$documents = ParcelRepository::findInBbox($minLng, $minLat, $maxLng, $maxLat, $limit);
$truncated = count($documents) > $limit;

if ($truncated) {
    $documents = array_slice($documents, 0, $limit);
}

Http::json(['success' => true, 'truncated' => $truncated, 'geojson' => ParcelRepository::toFeatureCollection($documents, 'parcel')]);
