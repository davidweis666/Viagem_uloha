const jicin = [50.4372, 15.3516];
const MIN_PARCEL_ZOOM = 15;

const map = L.map('map', { renderer: L.canvas({ padding: 0.5 }) }).setView(jicin, 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

const sidebar = document.getElementById('parcel-sidebar');
const parcelContent = document.getElementById('parcel-content');
const closeSidebar = document.getElementById('close-sidebar');
const mapHint = document.getElementById('map-hint');

const landColors = {
    'Orná půda': '#e6d48a',
    'Chmelnice': '#c5dc8a',
    'Vinice': '#c9a0dc',
    'Zahrada': '#8fd18f',
    'Ovocný sad': '#6bbf73',
    'Trvalý travní porost': '#b5d67a',
    'Lesní pozemek': '#2f7d4a',
    'Vodní plocha': '#6cb4e0',
    'Zastavěná plocha a nádvoří': '#b9b3a9',
    'Ostatní plocha': '#d2cfc7'
};

let selectedParcelLayer = null;
let parcelRequest = null;
let parcelLoadTimer = null;

function openSidebar() {
    sidebar.classList.add('open');
}

function closeParcelSidebar() {
    sidebar.classList.remove('open');
}

closeSidebar.addEventListener('click', function () {
    closeParcelSidebar();
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeParcelSidebar();
    }
});

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function displayValue(value) {
    if (value === null || value === undefined || value === '') {
        return 'Neuvedeno';
    }

    return escapeHtml(value);
}

function row(label, value) {
    return `
        <div class="parcel-row">
            <span class="parcel-label">${label}</span>
            <span class="parcel-value">${value}</span>
        </div>
    `;
}

function section(title, content) {
    return `
        <div class="parcel-section">
            <h3>${title}</h3>
            ${content}
        </div>
    `;
}

function showParcel(parcel) {
    const basic =
        row('Parcelní číslo', displayValue(parcel.label)) +
        row('Katastrální reference', displayValue(parcel.cadastral_reference)) +
        row(
            'Výměra',
            parcel.area
                ? displayValue(parcel.area) + ' m²'
                : 'Neuvedeno'
        );

    const land =
        row('Druh pozemku', displayValue(parcel.land_type)) +
        (
            parcel.land_use
                ? row('Způsob využití', displayValue(parcel.land_use))
                : ''
        ) +
        (
            parcel.land_type_symbol
                ? row(
                    'Symbol druhu pozemku',
                    displayValue(parcel.land_type_symbol)
                )
                : ''
        );

    const identification =
        row('INSPIRE ID', displayValue(parcel.inspire_id)) +
        row('Katastrální území', displayValue(parcel.zoning)) +
        row('Územní jednotka', displayValue(parcel.administrative_unit));

    const dates = row(
        'Poslední změna',
        displayValue(parcel.begin_lifespan_version)
    );

    parcelContent.innerHTML =
        section('Základní informace', basic) +
        section('Údaje o pozemku', land) +
        section('Identifikace', identification) +
        section('Platnost dat', dates);
}

function highlightGeometry(geometry) {
    if (selectedParcelLayer) {
        map.removeLayer(selectedParcelLayer);
        selectedParcelLayer = null;
    }

    if (!geometry) {
        return;
    }

    selectedParcelLayer = L.geoJSON(
        {
            type: 'Feature',
            geometry: geometry,
            properties: {}
        },
        {
            style: {
                color: '#c0392b',
                weight: 3,
                fillOpacity: 0
            }
        }
    ).addTo(map);
}

function selectParcel(parcel) {
    openSidebar();

    if (!parcel) {
        highlightGeometry(null);
        parcelContent.innerHTML = `
            <p class="no-parcel">
                Na tomto místě nebyla nalezena parcela.
            </p>
        `;
        return;
    }

    highlightGeometry(parcel.geometry);
    showParcel(parcel);
}

const parcelLayer = L.geoJSON(null, {
    style: function (feature) {
        const landType = feature.properties && feature.properties.land_type;

        return {
            color: '#555',
            weight: 0.8,
            fillColor: landColors[landType] || '#d7d2c8',
            fillOpacity: 0.55
        };
    },
    onEachFeature: function (feature, layer) {
        layer.on('click', function (event) {
            ignoreMapClick = true;
            L.DomEvent.stopPropagation(event);

            const parcel = Object.assign(
                {},
                feature.properties,
                { geometry: feature.geometry }
            );

            selectParcel(parcel);
        });
    }
}).addTo(map);

function updateHint(truncated) {
    if (!mapHint) {
        return;
    }

    if (map.getZoom() < MIN_PARCEL_ZOOM) {
        mapHint.textContent = 'Přibližte mapu, aby se vykreslily parcely.';
        mapHint.hidden = false;
        return;
    }

    if (truncated) {
        mapHint.textContent = 'Ve výřezu je příliš mnoho parcel. Přibližte mapu.';
        mapHint.hidden = false;
        return;
    }

    mapHint.hidden = true;
}

function fetchJson(url, options) {
    return fetch(url, options).then(function (response) {
        return response.text().then(function (text) {
            let data;

            try {
                data = JSON.parse(text);
            } catch (error) {
                throw new Error('Server nevrátil platný JSON.');
            }

            if (!response.ok || data.success === false) {
                throw new Error(data.error || ('HTTP chyba ' + response.status));
            }

            return data;
        });
    });
}

let zoningLayer = null;

function loadZonings() {
    return fetchJson('api/geojson.php?type=zonings').then(function (data) {
        if (zoningLayer == null) {
            zoningLayer = L.geoJSON(null, {
                style: {
                    color: '#1f4e79',
                    weight: 2,
                    fillColor: '#1f4e79',
                    fillOpacity: 0.04
                }
            }).addTo(map);
        }

        zoningLayer.clearLayers();
        zoningLayer.addData(data.geojson);

        if (zoningLayer.getLayers().length) {
            map.fitBounds(zoningLayer.getBounds().pad(0.08));
        }
    });
}

function loadParcels() {
    if (map.getZoom() < MIN_PARCEL_ZOOM) {
        parcelLayer.clearLayers();
        updateHint(false);

        if (parcelRequest) {
            parcelRequest.abort();
            parcelRequest = null;
        }

        return;
    }

    const bounds = map.getBounds();
    const bbox = [
        bounds.getWest(),
        bounds.getSouth(),
        bounds.getEast(),
        bounds.getNorth()
    ].join(',');

    if (parcelRequest) {
        parcelRequest.abort();
    }

    parcelRequest = new AbortController();

    fetchJson(
        'api/geojson.php?type=parcels&bbox=' + encodeURIComponent(bbox),
        { signal: parcelRequest.signal }
    ).then(function (data) {
        parcelLayer.clearLayers();
        parcelLayer.addData(data.geojson);
        updateHint(Boolean(data.truncated));
    }).catch(function (error) {
        if (error && error.name === 'AbortError') {
            return;
        }

        console.error(error);
        updateHint(false);
    });
}

function scheduleParcelLoad() {
    clearTimeout(parcelLoadTimer);
    parcelLoadTimer = setTimeout(loadParcels, 200);
}

map.on('moveend', scheduleParcelLoad);
map.on('zoomend', scheduleParcelLoad);

let ignoreMapClick = false;

map.on('click', function (event) {
    if (ignoreMapClick) {
        ignoreMapClick = false;
        return;
    }

    if (map.getZoom() < MIN_PARCEL_ZOOM) {
        openSidebar();
        parcelContent.innerHTML = `
            <p class="no-parcel">
                Přibližte mapu a klikněte na konkrétní parcelu.
            </p>
        `;
        return;
    }

    openSidebar();
    parcelContent.innerHTML = '<p class="loading">Načítám informace o parcele...</p>';

    const url =
        'api/parcels.php?lat=' +
        encodeURIComponent(event.latlng.lat) +
        '&lng=' +
        encodeURIComponent(event.latlng.lng);

    fetchJson(url)
        .then(function (data) {
            selectParcel(data.parcel);
        })
        .catch(function (error) {
            parcelContent.innerHTML = `
                <p class="error">
                    <strong>Chyba</strong><br>
                    ${displayValue(error.message)}
                </p>
            `;
        });
});

loadZonings()
    .then(loadParcels)
    .catch(function (error) {
        console.error(error);
        openSidebar();
        parcelContent.innerHTML = `
            <p class="error">
                <strong>Chyba</strong><br>
                ${displayValue(error.message)}
            </p>
        `;
    });
