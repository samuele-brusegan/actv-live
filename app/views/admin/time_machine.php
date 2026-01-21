<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Machine Manager - ACTV Live</title>
    <?php require COMMON_HTML_HEAD; ?>
    <style>
        .session-card { margin-bottom: 1rem; }
        .recording-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; background: #ccc; margin-right: 5px; }
        .recording-active { background: #dc3545; animation: blink 1s infinite; }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">Time Machine Manager</span>
            <a href="/" class="btn btn-outline-light btn-sm">Torna alla Home</a>
        </div>
    </nav>

    <div class="container mt-4 pb-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-white"><strong>Nuova Registrazione</strong></div>
                    <div class="card-body">
                        <form id="session-form">
                            <div class="mb-3">
                                <label class="form-label">Nome Sessione</label>
                                <input type="text" class="form-control" id="tm-name" placeholder="es. Mattina Feriale Mestre" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Data e Ora Inizio</label>
                                <input type="datetime-local" class="form-control" id="tm-start" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Data e Ora Fine</label>
                                <input type="datetime-local" class="form-control" id="tm-end" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Fermate (ID separate da virgola)</label>
                                <textarea class="form-control" id="tm-stops" placeholder="4825, 2301, 1077" required></textarea>
                                <div class="form-text">Registrerà solo i real-time di queste fermate.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Crea Sessione</button>
                        </form>
                    </div>
                </div>

                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Playback Control</h5>
                        <p class="card-text small">Attiva la modalità Time Machine lato client.</p>
                        <hr>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="tm-playback-toggle">
                            <label class="form-check-label" for="tm-playback-toggle">Modalità Simulazione</label>
                        </div>
                        <div id="playback-controls" style="display: none;">
                            <label class="form-label small">Data/Ora Simulazione</label>
                            <input type="datetime-local" class="form-control mb-2" id="tm-sim-time">
                            <button class="btn btn-sm btn-light w-100" id="tm-set-time">Imposta Ora</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <h5 class="mb-3">Sessioni Registrate / Programmate</h5>
                <div id="sessions-list">
                    <div class="text-center p-5 text-muted">Caricamento sessioni...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function loadSessions() {
            const res = await fetch('/api/tm/sessions');
            const sessions = await res.json();
            const list = document.getElementById('sessions-list');
            list.innerHTML = sessions.length ? '' : '<div class="alert alert-light">Nessuna sessione trovata.</div>';
            
            sessions.forEach(s => {
                const card = document.createElement('div');
                card.className = 'card session-card';
                const isActive = s.status === 'RECORDING';
                card.innerHTML = `
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <h6 class="card-title mb-1">${s.name}</h6>
                            <span class="badge ${s.status === 'COMPLETED' ? 'bg-success' : (s.status === 'RECORDING' ? 'bg-danger' : 'bg-secondary')}">${s.status}</span>
                        </div>
                        <p class="small text-muted mb-2">
                            ${s.start_time} &rightarrow; ${s.end_time}
                        </p>
                        <div class="mb-1 small">
                            <strong>Fermate:</strong> ${JSON.parse(s.stops).join(', ')}
                        </div>
                        <div class="text-end">
                            ${s.status === 'COMPLETED' ? `<button class="btn btn-sm btn-outline-primary" onclick="startPlayback('${s.start_time}')">Usa come inizio</button>` : ''}
                        </div>
                    </div>
                `;
                list.appendChild(card);
            });
        }

        document.getElementById('session-form').onsubmit = async (e) => {
            e.preventDefault();
            const data = {
                name: document.getElementById('tm-name').value,
                start_timer: document.getElementById('tm-start').value.replace('T', ' '),
                end_timer: document.getElementById('tm-end').value.replace('T', ' '),
                stops: document.getElementById('tm-stops').value.split(',').map(s => s.trim())
            };
            const res = await fetch('/api/tm/create-session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (res.ok) {
                document.getElementById('session-form').reset();
                loadSessions();
            }
        };

        // Playback Sync
        const toggle = document.getElementById('tm-playback-toggle');
        const controls = document.getElementById('playback-controls');
        
        toggle.checked = localStorage.getItem('tm_enabled') === 'true';
        controls.style.display = toggle.checked ? 'block' : 'none';
        
        toggle.onchange = () => {
            localStorage.setItem('tm_enabled', toggle.checked);
            controls.style.display = toggle.checked ? 'block' : 'none';
            if (!toggle.checked) {
                localStorage.removeItem('tm_time');
            }
        };

        document.getElementById('tm-set-time').onclick = () => {
            const time = document.getElementById('tm-sim-time').value;
            if (time) {
                localStorage.setItem('tm_time', time.replace('T', ' '));
                alert('Ora della simulazione impostata!');
            }
        };

        function startPlayback(startTime) {
            toggle.checked = true;
            localStorage.setItem('tm_enabled', 'true');
            localStorage.setItem('tm_time', startTime);
            controls.style.display = 'block';
            document.getElementById('tm-sim-time').value = startTime.replace(' ', 'T');
            alert('Simulazione avviata da: ' + startTime);
        }

        loadSessions();
    </script>
</body>
</html>
