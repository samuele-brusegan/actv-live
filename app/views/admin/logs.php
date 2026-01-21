<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Log ed Eccezioni - ACTV Live</title>
    <?php require COMMON_HTML_HEAD; ?>
    <style>
        .log-card { margin-bottom: 1rem; border-left: 5px solid #ccc; }
        .log-PHP_ERROR { border-left-color: #dc3545; }
        .log-JS_ERROR { border-left-color: #ffc107; }
        .log-EXCEPTION { border-left-color: #6610f2; }
        .stack-trace { font-size: 0.8rem; background: #f8f9fa; padding: 10px; overflow-x: auto; display: none; }
        .context { font-size: 0.8rem; color: #6c757d; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">Log ed Eccezioni</span>
            <a href="/" class="btn btn-outline-light btn-sm">Torna alla Home</a>
        </div>
    </nav>

    <div class="container mt-4 pb-5">
        <div class="mb-3 d-flex gap-2 overflow-auto pb-2">
            <a href="/admin/logs" class="btn btn-sm btn-outline-secondary">Tutti</a>
            <a href="/admin/logs?type=PHP_ERROR" class="btn btn-sm btn-danger">PHP Errors</a>
            <a href="/admin/logs?type=JS_ERROR" class="btn btn-sm btn-warning">JS Errors</a>
            <a href="/admin/logs?type=EXCEPTION" class="btn btn-sm btn-primary">Exceptions</a>
        </div>

        <?php if (empty($logs)): ?>
            <div class="alert alert-info">Nessun log trovato.</div>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <div class="card log-card log-<?= $log['type'] ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title mb-1"><?= htmlspecialchars($log['type']) ?></h5>
                            <small class="text-muted"><?= $log['created_at'] ?></small>
                        </div>
                        <p class="card-text mb-1"><strong><?= htmlspecialchars($log['message']) ?></strong></p>
                        <p class="card-text mb-1 small text-muted">
                            <?= htmlspecialchars($log['file']) ?> : <?= $log['line'] ?>
                        </p>
                        <?php if ($log['context']): ?>
                            <div class="context mb-2">
                                <code><?= htmlspecialchars($log['context']) ?></code>
                            </div>
                        <?php endif; ?>
                        <?php if ($log['stack_trace']): ?>
                            <button class="btn btn-sm btn-link p-0" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'">
                                Mostra Stack Trace
                            </button>
                            <pre class="stack-trace mt-2"><?= htmlspecialchars($log['stack_trace']) ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
