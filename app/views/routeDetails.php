<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dettagli Viaggio - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/routeDetails.css">
        <script src="/js/routeDetails.js"></script>
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="javascript:history.back()" class="back-button">&larr;</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            
            <div class="details-card">
                
                <!-- Header Info inside card -->
                <div class="header-info">
                    <div class="header-date" id="route-date"></div>
                    <div class="header-time" id="route-duration"></div>
                </div>
                
                <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

                <div id="timeline-container" class="timeline">
                    <!-- Injected via JS -->
                </div>

            </div>

            <button class="btn-map" onclick="showMap()">visualizza sulla mappa</button>

        </div>
    </body>
</html>