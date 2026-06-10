<?php
require_once BASE_PATH . '/public/commons/head.php';
?>

<link rel="stylesheet" href="/css/admin.css">

<div class="admin-container">
    <div class="admin-header">
        <h1>Dashboard Amministrazione</h1>
        <div class="d-flex align-items-center gap-2">
            <a href="/admin/gtfs-update" class="btn btn-sm btn-outline-primary">Aggiornamento GTFS</a>
            <div class="last-update" id="last-update">In attesa di dati...</div>
        </div>
    </div>

    <!-- Progress bar primo caricamento -->
    <div id="dashboard-progress" class="dashboard-progress" style="display:none;">
        <div class="dashboard-progress-track">
            <div class="dashboard-progress-fill" id="dashboard-progress-fill"></div>
        </div>
        <div class="dashboard-progress-label" id="dashboard-progress-label">Caricamento bus…</div>
    </div>

    <!-- Big Numbers Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-title">Bus Attivi</div>
            <div class="stat-value" id="stat-total-buses">--</div>
            <div class="stat-sub">Monitorati in tempo reale</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Ritardo Medio</div>
            <div class="stat-value" id="stat-avg-delay">--</div>
            <div class="stat-sub">Su tutta la rete</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-title">Max Ritardo</div>
            <div class="stat-value" id="stat-max-delay">--</div>
            <div class="stat-sub" id="stat-max-delay-line">--</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-title">No GPS</div>
            <div class="stat-value" id="stat-no-gps">--</div>
            <div class="stat-sub">Bus senza segnale</div>
        </div>
    </div>

    <!-- Charts / Secondary Stats -->
    <div class="secondary-grid">
        <div class="panel-card">
            <h2>Bus per Linea</h2>
            <div class="lines-chart" id="lines-stats-container">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    <div class="panel-card full-width">
        <div class="panel-header">
            <h2>Dettaglio Flotta</h2>
            <input type="text" id="table-filter" placeholder="Cerca bus, linea, destinazione, operatore...">
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Linea</th>
                        <th>Verso</th>
                        <th>Trip ID</th>
                        <th>Prossima Fermata</th>
                        <th>Stato</th>
                        <th>Ritardo</th>
                        <th>Veicolo</th>
                        <th>Operatore</th>
                    </tr>
                </thead>
                <tbody id="buses-table-body">
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/js/delayHistory.js"></script>
<script src="/js/adminDashboard.js"></script>

<?php
// Footer is optional depending on design, but usually included
// require_once BASE_PATH . '/app/views/footer.php'; 
?>
</body>
</html>
