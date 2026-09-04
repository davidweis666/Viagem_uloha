<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

ini_set('memory_limit', '512M');

$force = in_array('--force', $argv ?? [], true);

try {
    $tries = 0;

    while (true) {
        try {
            Database::ping();
            break;
        } catch (Throwable $e) {
            $tries++;

            if ($tries >= 30) {
                throw new RuntimeException('MongoDB není dostupné: ' . $e->getMessage());
            }

            fwrite(STDOUT, "Čekám na MongoDB ($tries/30)...\n");
            sleep(2);
        }
    }

    Importer::run($force);
} catch (Throwable $e) {
    fwrite(STDERR, 'Import selhal: ' . $e->getMessage() . "\n");
    exit(1);
}
