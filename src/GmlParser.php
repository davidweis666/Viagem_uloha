<?php

declare(strict_types=1);

final class GmlParser
{
    public static function parseZip(string $zipPath, callable $onFeature): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Nelze otevřít ZIP: ' . $zipPath);
        }

        $inner = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name && str_ends_with(strtolower($name), '.xml')) {
                $inner = $name;
                break;
            }
        }

        $zip->close();

        if (!$inner) {
            throw new RuntimeException('ZIP neobsahuje XML: ' . $zipPath);
        }

        $zipPath = realpath($zipPath) ?: $zipPath;

        $reader = new XMLReader();
        $opened = $reader->open(
            'zip://' . $zipPath . '#' . $inner,
            null,
            LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE
        );

        if (!$opened) {
            throw new RuntimeException('Nelze číst GML z ' . $zipPath);
        }

        $skip = [
            'CadastralBoundary',
            'Easement',
            'GeodeticPoint',
            'InnerDrawing',
            'OtherBuilding',
            'PlanimetrySupplement',
            'TopographicalName',
            'Building',
            'ProtectedZone',
            'CadastralParcelOriginalGeometry',
            'CadastralBoundaryOriginalGeometry',
        ];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            $type = $reader->localName;

            if (in_array($type, $skip, true)) {
                $depth = $reader->depth;

                while ($reader->read() && $reader->depth > $depth) {
                    // Skip unused feature types.
                }

                continue;
            }

            if ($type !== 'CadastralParcel' && $type !== 'CadastralZoning') {
                continue;
            }

            $xml = $reader->readOuterXml();
            $dom = new DOMDocument();

            libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml);
            libxml_clear_errors();

            if (!$loaded) {
                continue;
            }

            $feature = $type === 'CadastralParcel'
                ? self::parcel($dom)
                : self::zoning($dom);

            if ($feature) {
                $onFeature($type, $feature);
            }
        }

        $reader->close();
    }

    private static function parcel(DOMDocument $dom): ?array
    {
        $xpath = new DOMXPath($dom);
        $root = $dom->documentElement;

        $geometry = self::geometry($xpath, $root, 'geometry');

        if (!$geometry) {
            return null;
        }

        $namespace = self::value($xpath, './/*[local-name()="inspireId"]/*[local-name()="Identifier"]/*[local-name()="namespace"]', $root);
        $localId = self::value($xpath, './/*[local-name()="inspireId"]/*[local-name()="Identifier"]/*[local-name()="localId"]', $root);

        return [
            'inspire_id' => $namespace && $localId ? $namespace . ':' . $localId : $localId,
            'label' => self::value($xpath, './/*[local-name()="label"]', $root),
            'cadastral_reference' => self::value($xpath, './/*[local-name()="nationalCadastralReference"]', $root),
            'area' => self::number(self::value($xpath, './/*[local-name()="areaValue"]', $root)),
            'land_type' => Translations::fromHref(self::href($xpath, 'landType', $root)),
            'land_use' => Translations::fromHref(self::href($xpath, 'landUse', $root)),
            'land_type_symbol' => Translations::fromHref(self::href($xpath, 'landTypeSymbol', $root)),
            'zoning' => self::title($xpath, 'zoning', $root),
            'zoning_code' => self::zoningCode($xpath, $root),
            'administrative_unit' => self::title($xpath, 'administrativeUnit', $root),
            'begin_lifespan_version' => self::value($xpath, './/*[local-name()="beginLifespanVersion"]', $root),
            'geometry' => $geometry,
        ];
    }

    private static function zoning(DOMDocument $dom): ?array
    {
        $xpath = new DOMXPath($dom);
        $root = $dom->documentElement;
        $geometry = self::geometry($xpath, $root, 'geometry');

        if (!$geometry) {
            return null;
        }

        $code = self::value($xpath, './/*[local-name()="nationalCadastalZoningReference"]', $root)
            ?: self::value($xpath, './/*[local-name()="inspireId"]/*[local-name()="Identifier"]/*[local-name()="localId"]', $root);

        if ($code && str_starts_with($code, 'CZ.')) {
            $code = substr($code, 3);
        }

        return [
            'code' => $code,
            'name' => self::value($xpath, './/*[local-name()="label"]', $root),
            'begin_lifespan_version' => self::value($xpath, './/*[local-name()="beginLifespanVersion"]', $root),
            'geometry' => $geometry,
        ];
    }

    private static function geometry(DOMXPath $xpath, DOMNode $context, string $name): ?array
    {
        $container = $xpath->query('.//*[local-name()="' . $name . '"]', $context)->item(0);

        if (!$container) {
            return null;
        }

        $polygons = [];

        foreach ($xpath->query('.//*[local-name()="Polygon"]', $container) as $polygon) {
            $rings = [];

            $exterior = $xpath->query('.//*[local-name()="exterior"]//*[local-name()="posList"]', $polygon)->item(0);

            if (!$exterior) {
                continue;
            }

            $outer = self::ring($exterior->textContent);

            if (count($outer) < 4) {
                continue;
            }

            $rings[] = $outer;

            foreach ($xpath->query('.//*[local-name()="interior"]//*[local-name()="posList"]', $polygon) as $interior) {
                $hole = self::ring($interior->textContent);

                if (count($hole) >= 4) {
                    $rings[] = $hole;
                }
            }

            $polygons[] = $rings;
        }

        if (!$polygons) {
            return null;
        }

        if (count($polygons) === 1) {
            return [
                'type' => 'Polygon',
                'coordinates' => $polygons[0],
            ];
        }

        return [
            'type' => 'MultiPolygon',
            'coordinates' => $polygons,
        ];
    }

    private static function ring(string $posList): array
    {
        $values = preg_split('/\s+/', trim($posList)) ?: [];
        $ring = [];

        for ($i = 0; $i + 1 < count($values); $i += 2) {
            $lat = (float) $values[$i];
            $lng = (float) $values[$i + 1];
            $ring[] = [$lng, $lat];
        }

        if ($ring && $ring[0] !== $ring[count($ring) - 1]) {
            $ring[] = $ring[0];
        }

        return $ring;
    }

    private static function value(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?string
    {
        $nodes = $xpath->query($query, $context);

        if (!$nodes || !$nodes->length) {
            return null;
        }

        $text = trim($nodes->item(0)->textContent);

        return $text !== '' ? $text : null;
    }

    private static function href(DOMXPath $xpath, string $name, DOMNode $context): ?string
    {
        $node = $xpath->query('.//*[local-name()="' . $name . '"]', $context)->item(0);

        if (!$node instanceof DOMElement) {
            return null;
        }

        $href = $node->getAttributeNS('http://www.w3.org/1999/xlink', 'href');

        return $href !== '' ? $href : null;
    }

    private static function title(DOMXPath $xpath, string $name, DOMNode $context): ?string
    {
        $node = $xpath->query('.//*[local-name()="' . $name . '"]', $context)->item(0);

        if (!$node instanceof DOMElement) {
            return null;
        }

        $title = $node->getAttributeNS('http://www.w3.org/1999/xlink', 'title');

        return $title !== '' ? $title : null;
    }

    private static function zoningCode(DOMXPath $xpath, DOMNode $context): ?string
    {
        $href = self::href($xpath, 'zoning', $context);

        if (!$href) {
            return null;
        }

        if (preg_match('/CZ\.(\d{6})/', $href, $match)) {
            return $match[1];
        }

        return null;
    }

    private static function number(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
