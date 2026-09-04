<?php

declare(strict_types=1);

use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

final class Database {
    private static ?Manager $manager = null;

    public static function manager(): Manager {
        if(self::$manager == null){
            self::$manager = new Manager(Config::mongoUri());
        }
        
        return self::$manager;
    }

    public static function ns(string $collection): string {
        return Config::mongoDb() . '.' . $collection;
    }

    public static function ping(): void {
        self::manager()->executeCommand('admin', new Command(['ping' => 1]));
    }

    public static function query(string $collection, array $filter, array $options = []): array {
        $cursor = self::manager()->executeQuery(self::ns($collection), new Query($filter, $options));

        $rows = [];

        foreach ($cursor as $document) {
            $rows[] = json_decode(json_encode($document), true);
        }

        return $rows;
    }

    public static function count(string $collection, array $filter = []): int {
        $result = self::manager()->executeCommand(Config::mongoDb(), new Command(['count' => $collection, 'query' => (object) $filter]))->toArray();

        return (int) ($result[0]->n ? $result[0]->n : 0);
    }
}
