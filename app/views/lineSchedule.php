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

            <!-- Selettori: linea, data, ora -->
            <div class="row g-2 align-items-end mb-3">
                <div class="col-12 col-sm-4">
                    <label for="line-input" class="form-label">Linea</label>
                    <input type="text" id="line-input" class="form-control" placeholder="es. 5E" autocomplete="off">
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
                    <button id="load-btn" class="btn btn-primary w-100">Cerca</button>
                </div>
            </div>

            <div id="weekday-hint" class="text-muted small mb-3"></div>

            <!-- Stato / errori -->
            <div id="status" class="alert alert-info d-none" role="alert"></div>

            <!-- Lista varianti di percorso -->
            <div id="variants" class="mb-4"></div>

            <!-- Dettaglio variante selezionata (fermate + orari) -->
            <div id="variant-detail"></div>

        </div>
    </body>
</html>
