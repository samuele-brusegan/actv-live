<?php
// Resiliente anche fuori dal bootstrap completo (es. test)
require_once BASE_PATH . '/app/services/I18n.php';
if (!function_exists('t')) {
    require_once BASE_PATH . '/public/functions.php';
}
?>
<!doctype html>
<html lang="<?= I18n::lang() ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    $SEO = [
        'title' => '404 — ' . t('meta.defaultTitle'),
        'desc' => t('meta.defaultDesc'),
        'robots' => 'noindex,follow',
    ];
    if (defined('COMMON_HTML_HEAD')) {
        require COMMON_HTML_HEAD;
    } else {
        echo '<title>' . htmlspecialchars($SEO['title']) . '</title>';
    }
    ?>
</head>
<body>
    <header class="header-green compact">
        <a href="/" class="back-button" aria-label="<?= te('ui.backHome') ?>">&larr;</a>
        <h1 class="header-title">404</h1>
        <p class="header-subtitle"><?= I18n::lang() === 'en' ? 'Page not found!' : 'Pagina non trovata!' ?></p>
    </header>
    <main style="padding:24px;text-align:center;">
        <p><a href="/">ACTV Live</a></p>
    </main>
</body>
</html>
