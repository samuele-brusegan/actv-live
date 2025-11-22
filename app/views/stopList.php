<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutte le Stazioni - ACTV</title>
    <?php
    require COMMON_HTML_HEAD;
    ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F5F5;
            margin: 0;
            padding: 0;
        }

        /* Header Verde */
        .header-green {
            background: #009E61;
            padding: 2rem 1.5rem 6rem;
            color: white;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            clip-path: polygon(0 0, 100% 0, 100% 75%, 0 100%);
            margin-bottom: -4rem;
        }

        .header-title {
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            font-size: 28px;
            line-height: 1.2;
            margin-top: 1rem;
        }

        /* Sezioni Titoli */
        .section-title {
            font-family: 'SF Pro', sans-serif;
            font-weight: 590;
            font-size: 20px;
            color: #000000;
            margin: 1.5rem 1.5rem 0.5rem;
        }

        /* Card Fermata */
        .stop-card {
            background: #FFFFFF;
            box-shadow: 2px 0px 9.7px -4px rgba(0, 0, 0, 0.24);
            border-radius: 15px;
            padding: 1rem;
            margin: 0.5rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            color: inherit;
            position: relative;
            transition: transform 0.1s;
        }
        
        .stop-card:active {
            transform: scale(0.98);
        }

        .stop-info {
            display: flex;
            flex-direction: column;
        }

        .stop-name {
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 20px;
            color: #000000;
            margin-bottom: 0.2rem;
        }

        .stop-desc {
            font-family: 'SF Pro', sans-serif;
            font-weight: 510;
            font-size: 14px;
            color: #666;
        }

        /* Badge Linea */
        .line-badge {
            background: #0152BB;
            border-radius: 7px;
            color: #FFFFFF;
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 14px;
            padding: 2px 8px;
            min-width: 36px;
            text-align: center;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 2px;
        }
        
        /* Search Input */
        .search-container {
            padding: 0 1.5rem;
            margin-bottom: 1rem;
        }
        
        .search-input {
            width: 100%;
            padding: 1rem;
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            font-family: 'Inter', sans-serif;
            font-size: 16px;
        }

        /* Quick Action */
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 24px;
            color: #333;
        }
        
        /* Loading */
        #loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

    </style>
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <div style="height: 20px;">
             <a href="/" style="color: white; text-decoration: none; font-size: 24px;">&larr;</a>
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
                <?php foreach ($stations as $station): ?>
                    <?php
                        $rawName = $station['description'];
                        // Extract IDs from description (e.g. "Name [123] [456]")
                        preg_match_all('/\[(\d+)\]/', $rawName, $matches);
                        $ids = $matches[1] ?? [];
                        
                        // Clean name by removing IDs
                        $cleanName = trim(preg_replace('/\[\d+\]/', '', $rawName));
                        
                        // If no IDs found, use a placeholder or the original name logic
                        if (empty($ids)) {
                            $ids = ['']; 
                        }

                        // Generate a unique group ID based on the raw name
                        $groupId = md5($rawName);

                        $lines = $station['lines'] ?? [];
                        // Prepare lines HTML (all lines, as we can't filter by sub-ID yet)
                        $linesHtml = '';
                        if (!empty($lines)) {
                            foreach ($lines as $line) {
                                $linesHtml .= '<span class="badge bg-secondary me-1" style="font-size: 0.7em; background-color: #6c757d; color: white; padding: 2px 5px; border-radius: 4px;">' . htmlspecialchars($line['alias']) . '</span>';
                            }
                        }
                    ?>

                    <?php foreach ($ids as $stopId): ?>
                        <a href="/aut/stops/stop?id=<?= urlencode($stopId ?: $station['name']) ?>&name=<?= urlencode($cleanName) ?>" 
                           class="stop-card station-item" 
                           data-name="<?= htmlspecialchars(strtoupper($cleanName)) ?>"
                           data-station-id="<?= htmlspecialchars($stopId) ?>"
                           data-group-id="<?= $groupId ?>"
                           data-original-name="<?= htmlspecialchars($rawName) ?>"
                           id="card-<?= htmlspecialchars($stopId) ?>">
                            
                            <div class="d-flex align-items-center" style="width: 100%;">
                                <!-- Blue rectangle with Station ID -->
                                <div style="min-width: 60px; text-align: center;">
                                    <div class="line-badge" style="width: auto; padding: 5px 10px; background-color: #007bff; color: white; border-radius: 5px; font-weight: bold;">
                                        <?= htmlspecialchars($stopId) ?>
                                    </div>
                                </div>
                                
                                <div class="stop-info ms-3" style="flex-grow: 1;">
                                    <span class="stop-name d-block"><?= htmlspecialchars($cleanName) ?></span>
                                    <!-- Subtitle for Direction -->
                                    <span class="stop-desc direction-subtitle" id="subtitle-<?= htmlspecialchars($stopId) ?>">
                                        <span class="spinner-border spinner-border-sm text-muted" role="status"></span> Caricamento...
                                    </span>
                                </div>
                            </div>
                            <div class="quick-action">
                                <span style="font-size: 20px; color: #ccc;">&rsaquo;</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
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

        document.addEventListener('DOMContentLoaded', () => {
            fetchDirections();
        });

        async function fetchDirections() {
            const cards = document.querySelectorAll('.station-item');
            const groups = {};

            // Group cards by group-id
            cards.forEach(card => {
                const groupId = card.getAttribute('data-group-id');
                if (!groups[groupId]) {
                    groups[groupId] = [];
                }
                groups[groupId].push(card);
            });

            // Process each group
            for (const groupId in groups) {
                const groupCards = groups[groupId];
                const promises = groupCards.map(async (card) => {
                    const stationId = card.getAttribute('data-station-id');
                    if (!stationId) return { card, data: [] };

                    try {
                        const response = await fetch(`https://oraritemporeale.actv.it/aut/backend/passages/${stationId}-web-aut`);
                        if (!response.ok) throw new Error('Network response was not ok');
                        const data = await response.json();
                        return { card, data };
                    } catch (error) {
                        console.error('Error fetching data for', stationId, error);
                        return { card, data: [] };
                    }
                });

                // Wait for all fetches in this group
                const results = await Promise.all(promises);

                // Check if ALL in group are empty
                const allEmpty = results.every(r => r.data.length === 0);

                if (allEmpty && groupCards.length > 1) {
                    // MERGE LOGIC
                    const firstCard = groupCards[0];
                    const originalName = firstCard.getAttribute('data-original-name');
                    
                    // Hide others
                    for (let i = 1; i < groupCards.length; i++) {
                        groupCards[i].style.display = 'none';
                        groupCards[i].classList.add('merged-hidden');
                    }

                    // Update first card
                    const nameEl = firstCard.querySelector('.stop-name');
                    const subtitleEl = firstCard.querySelector('.direction-subtitle');
                    const badgeEl = firstCard.querySelector('.line-badge');

                    if (nameEl) nameEl.textContent = originalName.replace(/\[\d+\]/g, '').trim();
                    
                    if (subtitleEl) {
                        subtitleEl.textContent = "Nessun autobus in arrivo";
                        subtitleEl.style.color = "#dc3545";
                    }
                    
                    // Stack IDs vertically in badge
                    const allIds = groupCards.map(c => c.getAttribute('data-station-id'));
                    if (badgeEl) {
                        badgeEl.innerHTML = allIds.map(id => `<div style="line-height: 1.2;">${id}</div>`).join('');
                        badgeEl.style.backgroundColor = '#6c757d';
                        badgeEl.style.color = '#fff';
                        badgeEl.style.padding = '5px 8px';
                    }

                } else {
                    // NOT ALL EMPTY (or single card)
                    results.forEach(({ card, data }) => {
                        const subtitleEl = card.querySelector('.direction-subtitle');
                        if (!subtitleEl) return;

                        if (data.length === 0) {
                            subtitleEl.textContent = "Nessun arrivo previsto";
                            subtitleEl.style.color = "#6c757d";
                        } else {
                            // Calculate frequent destination
                            const destinations = {};
                            data.forEach(bus => {
                                const dest = bus.destination;
                                destinations[dest] = (destinations[dest] || 0) + 1;
                            });
                            
                            // Sort by frequency
                            const sortedDest = Object.entries(destinations).sort((a, b) => b[1] - a[1]);
                            const mostFreq = sortedDest[0][0];

                            subtitleEl.textContent = "Dir. " + mostFreq;
                            subtitleEl.style.color = "#000";
                            subtitleEl.style.fontWeight = "500";
                        }
                    });
                }
            }
        }
    </script>

</body>
</html>
