<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Orari Linea - ACTV</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/lineSchedule.css">
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script defer src="/js/lineSchedule.js"></script>
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="/" style="color: white; text-decoration: none; font-size: 24px;">&larr;</a>
            </div>
            <div class="header-title">Orari<br>Linea</div>
        </div>

        <div class="main-content container py-3">

            <div class="service-switch mb-3" role="group" aria-label="Servizio">
                <button type="button" class="btn btn-outline-primary service-btn" data-service="automobilistico">Automobilistico</button>
                <button type="button" class="btn btn-outline-primary service-btn" data-service="navigation">Navigazione</button>
            </div>

            <!-- Selettori: linea, data, ora -->
            <div class="row g-2 align-items-end mb-3">
                <div class="col-12 col-sm-4">
                    <label for="line-search" class="form-label">Cerca linea</label>
                    <input type="search" id="line-search" class="form-control" placeholder="es. 5E" autocomplete="off">
                    <select id="line-input" class="form-select mt-2" aria-label="Linea" disabled>
                        <option value="">Seleziona una linea</option>
                    </select>
                </div>
                <div class="col-6 col-sm-3">
                    <label for="date-input" class="form-label">Giorno</label>
                    <input type="date" id="date-input" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-sm-3">
                    <label for="time-input" class="form-label">Ora</label>
                    <input type="time" id="time-input" class="form-control" value="<?= date('H:i') ?>">
                </div>
                <div class="col-12 col-sm-2">
                    <button id="load-btn" class="btn btn-primary w-100" disabled>Mostra orari</button>
                </div>
            </div>

            <div id="weekday-hint" class="text-muted small mb-3"></div>
            <div id="feed-meta" class="small text-muted mb-3"></div>

            <!-- Stato / errori -->
            <div id="status" class="alert alert-info d-none" role="alert"></div>

            <!-- Lista varianti di percorso -->
            <div id="variants" class="mb-4"></div>

            <!-- Dettaglio variante selezionata (fermate + orari) -->
            <div id="variant-detail"></div>

        </div>
    </body>
</html>
