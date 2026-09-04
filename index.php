<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Parcely - Okolí Jičín</title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    />

    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            font-family: Arial, sans-serif;
        }

        #map {
            width: 100%;
            height: 100%;
        }

        /* Right Sidebar */

        #parcel-sidebar {
            position: fixed;
            top: 0;
            right: 0;

            width: 380px;
            height: 100%;

            background: white;

            box-shadow: -3px 0 12px rgba(0, 0, 0, 0.2);

            z-index: 1000;

            transform: translateX(100%);
            transition: transform 0.25s ease;

            overflow-y: auto;
        }

        #parcel-sidebar.open {
            transform: translateX(0);
        }

        /* Header */

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            padding: 18px 20px;

            border-bottom: 1px solid #ddd;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 20px;
        }

        #close-sidebar {
            border: none;
            background: none;

            font-size: 28px;
            line-height: 1;

            cursor: pointer;

            color: #555;
        }

        #close-sidebar:hover {
            color: #000;
        }

        /* Content */

        #parcel-content {
            padding: 20px;
        }

        .parcel-section {
            margin-bottom: 24px;
        }

        .parcel-section h3 {
            margin: 0 0 14px 0;

            font-size: 15px;
            color: #555;

            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .parcel-row {
            padding: 10px 0;

            border-bottom: 1px solid #eee;
        }

        .parcel-label {
            display: block;

            font-size: 13px;
            color: #777;

            margin-bottom: 4px;
        }

        .parcel-value {
            display: block;

            font-size: 16px;
            color: #222;

            word-break: break-word;
        }

        .loading {
            color: #666;
            font-size: 15px;
        }

        .error {
            color: #b00020;
            font-size: 15px;
        }

        .no-parcel {
            color: #666;
            line-height: 1.5;
        }

        #map-hint {
            position: fixed;
            left: 50%;
            bottom: 18px;
            transform: translateX(-50%);
            z-index: 900;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 14px;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.18);
            font-size: 14px;
        }

        #legend {
            position: fixed;
            left: 12px;
            bottom: 18px;
            z-index: 900;
            background: rgba(255, 255, 255, 0.95);
            padding: 10px 12px;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.18);
            font-size: 12px;
            max-width: 220px;
        }

        #legend h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #555;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 4px 0;
        }

        .legend-swatch {
            width: 14px;
            height: 14px;
            border: 1px solid #888;
            flex: 0 0 14px;
        }

        /* Mobile */

        @media (max-width: 600px) {

            #parcel-sidebar {
                width: 100%;
            }

        }
    </style>
</head>

<body>

<div id="map"></div>

<div id="map-hint">
    Přibližte mapu, aby se vykreslily parcely z MongoDB.
</div>

<div id="legend">
    <h3>Druh pozemku</h3>
    <div class="legend-item"><span class="legend-swatch" style="background:#b9b3a9"></span>Zastavěná plocha</div>
    <div class="legend-item"><span class="legend-swatch" style="background:#8fd18f"></span>Zahrada</div>
    <div class="legend-item"><span class="legend-swatch" style="background:#e6d48a"></span>Orná půda</div>
    <div class="legend-item"><span class="legend-swatch" style="background:#b5d67a"></span>Trvalý travní porost</div>
    <div class="legend-item"><span class="legend-swatch" style="background:#2f7d4a"></span>Lesní pozemek</div>
    <div class="legend-item"><span class="legend-swatch" style="background:#6cb4e0"></span>Vodní plocha</div>
    <div class="legend-item"><span class="legend-swatch" style="background:#d2cfc7"></span>Ostatní</div>
</div>


<!-- Parcel information sidebar -->

<aside id="parcel-sidebar">

    <div class="sidebar-header">

        <h2>Informace o parcele</h2>

        <button id="close-sidebar" title="Zavřít">
            ×
        </button>

    </div>

    <div id="parcel-content">

        <p class="no-parcel">
            Klikněte na parcelu v mapě.
        </p>

    </div>

</aside>


<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script src="js/map.js"></script>

</body>
</html>