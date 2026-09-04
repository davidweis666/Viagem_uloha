<?php

declare(strict_types=1);

use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\WriteConcern;

final class Importer
{
    public static function imported(): bool {
        return Database::count('parcels') > 0 && Database::count('zonings') > 0;
    }

    public static function run(bool $force = false): void{
        if (!$force && self::imported()) {
            fwrite(STDOUT, "MongoDB už obsahuje parcely, import se přeskakuje.\n");
            return;
        }

        $dataDir = Config::dataDir();

        if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
            throw new RuntimeException('Nelze vytvořit adresář ' . $dataDir);
        }

        if ($force || self::imported()) {
            self::drop('parcels');
            self::drop('zonings');
            self::drop('meta');
        }

        $parcelCount = 0;
        $zoningCount = 0;

        foreach (Config::zonings() as $code => $name) {
            $zipPath = $dataDir . DIRECTORY_SEPARATOR . $code . '.zip';
            self::download('' . $code, $name, $zipPath);

            fwrite(STDOUT, "Importuji $name ($code)...\n");

            $parcelBatch = [];
            $zoningBatch = [];

            GmlParser::parseZip($zipPath, function (string $type, array $feature) use (&$parcelBatch, &$zoningBatch, &$parcelCount, &$zoningCount) {
                if ($type === 'CadastralParcel') {
                    $parcelBatch[] = $feature;
                    $parcelCount++;

                    if (count($parcelBatch) >= 400) {
                        self::insert('parcels', $parcelBatch);
                        $parcelBatch = [];
                    }

                    return;
                }

                $zoningBatch[] = $feature;
                $zoningCount++;
            });

            if ($parcelBatch) {
                self::insert('parcels', $parcelBatch);
            }

            if ($zoningBatch) {
                self::insert('zonings', $zoningBatch);
            }
        }

        self::indexes();

        $bulk = new BulkWrite();
        $bulk->update(
            ['_id' => 'import'],
            [
                '_id' => 'import',
                'zonings' => Config::zonings(),
                'parcels' => $parcelCount,
                'imported_at' => new UTCDateTime(),
            ],
            ['upsert' => true]
        );

        Database::manager()->executeBulkWrite(
            Database::ns('meta'),
            $bulk,
            ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]
        );

        fwrite(STDOUT, "Hotovo: $parcelCount parcel, $zoningCount katastrálních území.\n");
    }

    private static function download(string $code, string $name, string $zipPath): void {
        if (is_file($zipPath) && filesize($zipPath) > 0) {
            fwrite(STDOUT, "Používám stažený soubor $name ($code).\n");
            return;
        }

        $url = Config::datasetUrl($code);
        fwrite(STDOUT, "Stahuji $name ($code) z ČÚZK...\n");

        $tmp = $zipPath . '.part';
        $fh = fopen($tmp, 'wb');

        if ($fh === false) {
            throw new RuntimeException('Nelze zapsat ' . $tmp);
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_USERAGENT => 'ViagemUloha/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $ok = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fh);

        if ($ok === false || $status !== 200) {
            @unlink($tmp);
            throw new RuntimeException("Stažení $code selhalo (HTTP $status): $error");
        }

        rename($tmp, $zipPath);
    }

    private static function insert(string $collection, array $documents): void {
        $bulk = new BulkWrite(['ordered' => false]);

        foreach ($documents as $document) {
            $bulk->insert($document);
        }

        Database::manager()->executeBulkWrite(Database::ns($collection), $bulk);
    }

    private static function drop(string $collection): void {
        try {
            Database::manager()->executeCommand(
                Config::mongoDb(),
                new Command(['drop' => $collection])
            );
        } catch (Throwable $e) {
        }
    }

    private static function indexes(): void {
        Database::manager()->executeCommand(
            Config::mongoDb(),
            new Command([
                'createIndexes' => 'parcels',
                'indexes' => [
                    [
                        'key' => ['geometry' => '2dsphere'],
                        'name' => 'geometry_2dsphere',
                    ],
                    [
                        'key' => ['inspire_id' => 1],
                        'name' => 'inspire_id',
                    ],
                    [
                        'key' => ['zoning_code' => 1],
                        'name' => 'zoning_code',
                    ],
                ],
            ])
        );

        Database::manager()->executeCommand(
            Config::mongoDb(),
            new Command([
                'createIndexes' => 'zonings',
                'indexes' => [
                    [
                        'key' => ['geometry' => '2dsphere'],
                        'name' => 'geometry_2dsphere',
                    ],
                    [
                        'key' => ['code' => 1],
                        'name' => 'code',
                        'unique' => true,
                    ],
                ],
            ])
        );
    }
}
