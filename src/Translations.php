<?php

declare(strict_types=1);

final class Translations {
    private const LABELS = [
        'ArableGround' => 'Orná půda',
        'Hopgarden' => 'Chmelnice',
        'Vineyard' => 'Vinice',
        'Garden' => 'Zahrada',
        'Orchard' => 'Ovocný sad',
        'Grassland' => 'Trvalý travní porost',
        'PermanentGrassland' => 'Trvalý travní porost',
        'Forest' => 'Lesní pozemek',
        'WaterArea' => 'Vodní plocha',
        'BuiltUpArea' => 'Zastavěná plocha a nádvoří',
        'OtherArea' => 'Ostatní plocha',
        'OtherRoads' => 'Ostatní komunikace',
        'OhterRoads' => 'Ostatní komunikace',
        'Road' => 'Silnice',
        'HandlingArea' => 'Manipulační plocha',
        'Rail' => 'Dráha',
        'GreenArea' => 'Zeleň',
        'SportAndRecreationArea' => 'Sportovní a rekreační plocha',
        'WatercourseNatural' => 'Vodní tok přirozený',
        'WatercourseArtificial' => 'Vodní tok umělý',
        'ReservoirArtificial' => 'Nádrž umělá',
        'CommonYard' => 'Společný dvůr',
        'Dump' => 'Skládka',
        'MiningArea' => 'Dobývací prostor',
        'OtherAgriculture' => 'Zemědělská usedlost',
        'Cemetery' => 'Pohřebiště',
        'UnusedArea' => 'Neplodná půda',
    ];

    public static function label(?string $code): ?string {
        if (!$code) {
            return null;
        }

        return self::LABELS[$code] ?? null;
    }

    public static function fromHref(?string $href): ?string {
        if (!$href) {
            error_log('TRANSLATION: href is NULL/EMPTY');
            return null;
        }

        $path = parse_url($href, PHP_URL_PATH);

        if (!$path) {
            error_log('TRANSLATION: cannot parse path: ' . $href);
            return null;
        }

        $code = basename(rtrim($path, '/'));
        $code = rawurldecode($code);

        error_log('TRANSLATION HREF: ' . $href);
        error_log('TRANSLATION PATH: ' . $path);
        error_log('TRANSLATION CODE: ' . $code);
        error_log(
            'TRANSLATION LABEL: ' . (self::LABELS[$code] ?? 'NOT FOUND')
        );

        return self::label($code);
    }
}
