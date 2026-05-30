<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tutte le Stazioni - ACTV</title>
        <?php
        require COMMON_HTML_HEAD;
        ?>
        <link rel="stylesheet" href="/css/structure/structure-stopList.css">
        <link rel="stylesheet" href="/css/stopList.css">
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="/" style="color: white; text-decoration: none; font-size: 24px;">
                    <?= getIcon('arrow_back', 24) ?>
                </a>
            </div> 
            <div class="header-title">Tutte le<br>Stazioni</div>
        </div>

        <!-- Contenuto Principale -->
        <div class="main-content pb-5">
            
            <div class="list-tabs">
                <button type="button" class="list-tab active" id="tab-list" onclick="showListView()">Elenco</button>
                <button type="button" class="list-tab" id="tab-zones" onclick="showZonesView()">Per zona</button>
            </div>

            <div class="search-container" id="search-container">
                <input type="text" id="search-input" class="search-input" placeholder="Cerca stazione..." onkeyup="filterStations()">
            </div>

            <div id="list-view">
            <div class="section-title">Elenco Stazioni</div>
            
            <div id="stations-list">
                <?php if (empty($stations)): ?>
                    <p class='text-center text-muted'>Nessuna stazione trovata.</p>
                <?php else: ?>
                    <?php 
                        // Merge stations by clean name
                        $mergedStations = [];
                        foreach ($stations as $station) {
                            $rawName = $station['description'];
                            // Extract IDs from description (e.g. "Name [123] [456]")
                            preg_match_all('/\[(\d+)\]/', $rawName, $matches);
                            $ids = $matches[1] ?? [];
                            
                            // Clean name by removing IDs
                            $cleanName = trim(preg_replace('/\[\d+\]/', '', $rawName));
                            
                            // If no IDs found, skip this station
                            if (empty($ids)) continue;
                            
                            // Use clean name as key for merging
                            if (!isset($mergedStations[$cleanName])) {
                                $mergedStations[$cleanName] = [
                                    'name' => $cleanName,
                                    'ids' => [],
                                    'lines' => $station['lines'] ?? []
                                ];
                            }
                            
                            // Add all IDs to this station
                            $mergedStations[$cleanName]['ids'] = array_merge(
                                $mergedStations[$cleanName]['ids'],
                                $ids
                            );
                            
                            // Merge lines (avoid duplicates)
                            if (!empty($station['lines'])) {
                                $existingLineAliases = array_column($mergedStations[$cleanName]['lines'], 'alias');
                                foreach ($station['lines'] as $line) {
                                    if (!in_array($line['alias'], $existingLineAliases)) {
                                        $mergedStations[$cleanName]['lines'][] = $line;
                                        $existingLineAliases[] = $line['alias'];
                                    }
                                }
                            }
                        }
                    ?>
                    
                    <?php foreach ($mergedStations as $station): ?>
                        <?php
                            // Prepare lines HTML for subtitle
                            $linesHtml = '';
                            if (!empty($station['lines'])) {
                                $lineAliases = array_map(function($line) {
                                    return htmlspecialchars($line['alias']);
                                }, array_slice($station['lines'], 0, 4)); // Show max 5 lines
                                $linesHtml = implode(', ', $lineAliases);
                                if (count($station['lines']) > 4) {
                                    $linesHtml .= '...';
                                }
                            } else {
                                $linesHtml = 'Nessuna linea disponibile';
                            }
                            
                            // Use first ID for the link
                            $primaryId = $station['ids'][0];
                            $strIds = $station['ids'][0];
                            if (count($station['ids']) > 1) {
                                $strIds .= "-" . $station['ids'][1];
                            }
                            // Create a data attribute with all IDs
                            $allIdsJson = htmlspecialchars(json_encode($station['ids']));
                        ?>
                        
                        <a href="/aut/stops/stop?id=<?= urlencode($strIds) ?>&name=<?= urlencode($station['name']) ?>" 
                        class="stop-card station-item" 
                        data-name="<?= htmlspecialchars(strtoupper($station['name'])) ?>"
                        data-all-ids='<?= $allIdsJson ?>'>
                            
                            <div class="d-flex align-items-center" style="width: 100%;">
                                <!-- Stop IDs Container (vertically stacked badges) -->
                                <div class="stop-ids-container">
                                    <?php foreach ($station['ids'] as $stopId): ?>
                                        <div class="stop-id-badge"><?= htmlspecialchars($stopId) ?></div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="stop-info ms-3" style="flex-grow: 1;">
                                    <span class="stop-name d-block"><?= htmlspecialchars($station['name']) ?></span>
                                    <!-- Subtitle for Lines -->
                                    <span class="stop-desc">Linee: <?= $linesHtml ?></span>
                                </div>
                            </div>
                            <div class="quick-action">
                                <span style="font-size: 20px; color: #ccc;">&rsaquo;</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            </div><!-- /#list-view -->

            <div id="zones-view" style="display:none;"></div>

        </div>

        <script>
            function filterStations() {
                let input = document.getElementById('search-input');
                let filter = input.value.toUpperCase();
                let cards = document.getElementsByClassName('station-item');

                for (let i = 0; i < cards.length; i++) {
                    let name = cards[i].getAttribute('data-name');
                    let allIds = cards[i].getAttribute('data-all-ids');
                    if (name.indexOf(filter) > -1 || allIds.indexOf(filter) > -1) {
                        cards[i].style.display = "flex";
                    } else {
                        cards[i].style.display = "none";
                    }
                }
            }
            async function getStopsFromGTFS() {
                if (document.querySelectorAll('.station-item').length != 0) return;

                try {
                    let res = await fetch('/api/gtfs-stops?return=true');
                    let data = await res.json();

                    //remove duplicates by data_url
                    data = data.filter((item, index) => data.findIndex(t => t.data_url === item.data_url) === index);
                    createStopCards(data);
                } catch (error) {
                    console.log(error);
                }
            }
            function createStopCards(data) {
                let stationsList = document.getElementById('stations-list');
                let ids = [];
                data.forEach(station => {
                    if (station.data_url != null) {
                        let dirtyIds = station.data_url.split('-')
                        ids = dirtyIds.splice(0, dirtyIds.length - 2)
                    } else {
                        ids = [station.stop_id];
                    }

                    let card = document.createElement('div');
                    card.classList.add('station-item', 'stop-card');
                    card.setAttribute('data-name', station.stop_name.toUpperCase());
                    card.setAttribute('data-all-ids', JSON.stringify(ids));
                    card.innerHTML = `
                        <div class="d-flex align-items-center" style="width: 100%;">
                            <div class="stop-ids-container">
                                ${ids.map(id => `<div class="stop-id-badge">${id}</div>`).join('')}
                            </div>
                            <div class="stop-info ms-3" style="flex-grow: 1;">
                                <span class="stop-name d-block">${station.stop_name}</span>
                                <span class="stop-desc">Servizio non in tempo reale (Server ACTV Down)</span>
                            </div>
                        </div>
                        <div class="quick-action">
                            <span style="font-size: 20px; color: #ccc;">&rsaquo;</span>
                        </div>
                    `;
                    card.addEventListener('click', () => {
                        window.location.href = `/aut/stops/stop?id=${JSON.parse(card.getAttribute('data-all-ids')).join('-')}&name=${station.stop_name}`;
                    });
                    stationsList.appendChild(card);
                });
                if (currentView === 'zones') renderZonesIndex();
            }
            // ===== Vista alternativa: navigazione per zone =====
            let currentView = 'list';
            let zonesIndex = null;

            function showListView() {
                currentView = 'list';
                document.getElementById('list-view').style.display = '';
                document.getElementById('zones-view').style.display = 'none';
                const sc = document.getElementById('search-container'); if (sc) sc.style.display = '';
                document.getElementById('tab-list').classList.add('active');
                document.getElementById('tab-zones').classList.remove('active');
            }

            function showZonesView() {
                currentView = 'zones';
                document.getElementById('list-view').style.display = 'none';
                const sc = document.getElementById('search-container'); if (sc) sc.style.display = 'none';
                document.getElementById('zones-view').style.display = '';
                document.getElementById('tab-zones').classList.add('active');
                document.getElementById('tab-list').classList.remove('active');
                renderZonesIndex();
            }

            function buildZonesIndex() {
                const cards = Array.from(document.querySelectorAll('#stations-list .station-item'));
                const map = {};
                const seen = new Set();
                cards.forEach(card => {
                    const nameEl = card.querySelector('.stop-name');
                    const name = (nameEl ? nameEl.textContent : (card.getAttribute('data-name') || '')).trim();
                    if (!name) return;
                    const dedupeKey = name.toUpperCase();
                    if (seen.has(dedupeKey)) return;
                    seen.add(dedupeKey);
                    const firstWord = name.split(/\s+/)[0];
                    const key = firstWord.toUpperCase();
                    if (!map[key]) map[key] = { label: firstWord, cards: [] };
                    map[key].cards.push(card);
                });
                zonesIndex = map;
            }

            function renderZonesIndex() {
                const container = document.getElementById('zones-view');
                if (!container) return;
                buildZonesIndex();
                const keys = Object.keys(zonesIndex).sort((a, b) => a.localeCompare(b, 'it'));
                if (keys.length === 0) { container.innerHTML = "<p class='no-results'>Nessuna zona disponibile.</p>"; return; }
                container.innerHTML = '<div class="section-title">Zone</div>' + keys.map(k => {
                    const z = zonesIndex[k];
                    const safe = k.replace(/"/g, '&quot;');
                    return '<button type="button" class="zone-row stop-card" data-zone="' + safe + '">' +
                           '<span class="zone-name">' + z.label + '</span>' +
                           '<span class="zone-meta"><span class="zone-count">' + z.cards.length + '</span><span class="chevron">&rsaquo;</span></span>' +
                           '</button>';
                }).join('');
                container.querySelectorAll('.zone-row').forEach(btn => {
                    btn.addEventListener('click', () => openZone(btn.getAttribute('data-zone')));
                });
                // Ripristina il focus per la navigazione da tastiera
                const firstZone = container.querySelector('.zone-row');
                if (firstZone) firstZone.focus();
            }

            function openZone(key) {
                const container = document.getElementById('zones-view');
                if (!container || !zonesIndex || !zonesIndex[key]) return;
                const z = zonesIndex[key];
                container.innerHTML =
                    '<button type="button" class="zone-back stop-card" onclick="renderZonesIndex()"><span><span class="chevron-back">&lsaquo;</span> Tutte le zone</span></button>' +
                    '<div class="section-title">' + z.label + '</div><div id="zone-stations"></div>';
                const list = document.getElementById('zone-stations');
                z.cards.forEach(card => {
                    const clone = card.cloneNode(true);
                    if (clone.tagName !== 'A') {
                        const ids = clone.getAttribute('data-all-ids');
                        const nm = (clone.querySelector('.stop-name')?.textContent || '').trim();
                        clone.style.cursor = 'pointer';
                        clone.addEventListener('click', () => {
                            try { window.location.href = '/aut/stops/stop?id=' + JSON.parse(ids).join('-') + '&name=' + encodeURIComponent(nm); } catch (e) {}
                        });
                    }
                    list.appendChild(clone);
                });
                // Sposta il focus sul pulsante 'indietro' per continuare da tastiera
                const backBtn = container.querySelector('.zone-back');
                if (backBtn) backBtn.focus();
            }

            document.addEventListener('DOMContentLoaded', getStopsFromGTFS);
        </script>

    </body>
</html>
