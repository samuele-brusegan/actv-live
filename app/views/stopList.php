<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tutte le Stazioni - ACTV</title>
        <?php
        require COMMON_HTML_HEAD;
        ?>
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
            
            <div class="search-container">
                <input type="text" id="search-input" class="search-input" placeholder="Cerca stazione..." onkeyup="filterStations()">
            </div>

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

        </div>

        <script>
            function filterStations() {
                let input = document.getElementById('search-input');
                let filter = input.value.toUpperCase();
                let cards = document.getElementsByClassName('station-item');

                for (let i = 0; i < cards.length; i++) {
                    let name = cards[i].getAttribute('data-name');
                    if (name.indexOf(filter) > -1) {
                        cards[i].style.display = "flex";
                    } else {
                        cards[i].style.display = "none";
                    }
                }
            }
        </script>

    </body>
</html>
