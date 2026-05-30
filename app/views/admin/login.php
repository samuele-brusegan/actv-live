<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Amministrazione - ACTV Live</title>
    <?php require COMMON_HTML_HEAD; ?>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">Area Amministrazione</span>
            <a href="/" class="btn btn-outline-light btn-sm">Torna alla Home</a>
        </div>
    </nav>

    <div class="container mt-5" style="max-width: 420px;">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Accesso riservato</h5>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" action="/admin/login" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Accedi</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
