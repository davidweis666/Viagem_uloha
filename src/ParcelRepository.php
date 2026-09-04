<?php

declare(strict_types=1);

final class ParcelRepository {
    public static function findByPoint(float $lat, float $lng): ?array {
        $rows = Database::query('parcels', [
            'geometry' => [
                '$geoIntersects' => [
                    '$geometry' => [
                        'type' => 'Point',
                        'coordinates' => [$lng, $lat],
                    ],
                ],
            ],
        ], [
            'limit' => 1,
            'projection' => ['_id' => 0],
        ]);

        return $rows[0] ?? null;
    }

    public static function findInBbox(float $minLng, float $minLat, float $maxLng, float $maxLat, int $limit): array {
        return Database::query('parcels', self::bboxFilter($minLng, $minLat, $maxLng, $maxLat), ['limit' => $limit + 1, 'projection' => ['_id' => 0]]);
    }

    public static function zonings(): array
    {
        return Database::query('zonings', [], ['projection' => ['_id' => 0]]);
    }

    public static function zoningsInBbox(float $minLng, float $minLat, float $maxLng,float $maxLat): array {
        return Database::query('zonings', self::bboxFilter($minLng, $minLat, $maxLng, $maxLat), ['projection' => ['_id' => 0]]);
    }

    private static function bboxFilter(float $minLng, float $minLat, float $maxLng, float $maxLat): array {
        return [
            'geometry' => [
                '$geoIntersects' => [
                    '$geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[
                            [$minLng, $minLat],
                            [$maxLng, $minLat],
                            [$maxLng, $maxLat],
                            [$minLng, $maxLat],
                            [$minLng, $minLat],
                        ]],
                    ],
                ],
            ],
        ];
    }

    public static function toFeatureCollection(array $documents, string $kind): array {
        $features = [];

        foreach ($documents as $document) {
            $geometry = isset($document['geometry']) ? $document['geometry'] : null;

            if (!$geometry) {
                continue;
            }

            unset($document['geometry']);

            $features[] = ['type' => 'Feature', 'properties' => $document + ['kind' => $kind], 'geometry' => $geometry];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
