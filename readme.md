Viagem_uloha

Webová mapa parcel v okolí města Jičín. Parcely se stáhnou při startu z předpřipravené GML sady katastrálních území, uloží se do MongoDB a aplikace čte jen lokální databázi.

Pokrytí

Povinné minimum ze zadání bylo město Jičín + alespoň 3 další oblasti z KÚ.

já mám 7:
    '659541' => 'Jičín',
    '641243' => 'Holín',
    '776530' => 'Valdice',
    '740225' => 'Robousy',
    '723746' => 'Podhradí u Jičína',
    '740217' => 'Moravčice',
    '724572' => 'Kbelnice u Jičína',

Požadavky

- Docker

Spuštění

v cmd:
    docker compose up -d --build


První start chvíli trvá: čeká se na MongoDB, stáhnou se 7 ZIP sad a naimportují se parcely. Další starty import přeskočí, pokud databáze není prázdná.

Aplikace: http://localhost:8080

Znovu stáhnout a naimportovat data

v cmd:
    docker compose exec web php /var/www/html/scripts/import.php --force


Jak to funguje

1. Import (`scripts/import.php`) stáhne INSPIRE CPX ZIP pro každé KÚ z `https://services.cuzk.gov.cz/gml/inspire/cpx/epsg-4258/{kod}.zip`.
2. GML se streamuje přímo ze ZIPu (`XMLReader`) a ukládá se do MongoDB jako GeoJSON (`parcels`, `zonings`).
3. Mapa v malém měřítku ukáže jen hranice katastrálních území. Od zoomu 15 se z MongoDB načítají parcely v aktuálním výřezu.
4. Klik na parcelu zobrazí atributy z databáze. Bodový dotaz `api/parcels.php` používá `$geoIntersects`, ne ČÚZK.

Jak jsem postupoval

Nejdříve jsem si připravil prostředí dockeru a složky js a src. Otevřel jsem návod jak použít GoogleMaps jako základ mapy, ale postupně mi došlo že OpenStreetMap bude lepší, protože nepotřebuje API_KEY.
Stvořil jsem základní mapu a sidebar. Také jsem použil knihovnu Leaflet na rozeznání místa kde uživatel klikl. S tím jak použít ČÚZK API jsem si nechal výrazně poradit od chatgpt. Znovu jsem si přečetl zadání abych se ujistil že je vše správně. Uvědomil jsem si že podmínka 4 katastrů by mohla naznačovat že to chcete lokálně. Proto jsem do dockeru přidal mongodb a začal jsem backend od znova. Nechal jsem si připravit celý import zip souborů do mongodb. Nechal jsem si připravit všechny mongodb operace od ai, protože jsem mongodb nikdy nepoužíval. Program jsem dokončil, nahrál na github a zkusil znovu zprovoznit podle tohoto návodu.

Struktura


Viagem_uloha/
├── api/                 # JSON endpointy nad MongoDB
├── config/app.php       # KÚ, Mongo, cesty
├── data/db/             # Data na import
├── docker/
├── js/map.js            # OpenStreetView mapa a Leaflet overlay
├── scripts/import.php   # stažení + import
├── src/                 # samotné PHP
├── docker-compose.yml
└── Dockerfile

