<!doctype html>
<html lang="<?= I18n::lang() ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    $SEO = ['title' => t('meta.feedback.title'), 'desc' => t('meta.feedback.desc')];
    require COMMON_HTML_HEAD;
    ?>
    <link rel="stylesheet" href="/css/feedback.css">
</head>
<body>
    <header class="header-green compact">
        <a href="/" class="back-button" aria-label="<?= te('ui.backHome') ?>">←</a>
        <h1 class="header-title"><?= te('feedback.title') ?></h1>
        <p class="header-subtitle"><?= te('feedback.subtitle') ?></p>
    </header>
    <main class="feedback-page">
        <form id="feedback-form" class="feedback-card" novalidate>
            <div class="feedback-intro"><?= te('feedback.intro') ?></div>
            <label for="feedback-category"><?= te('feedback.category') ?> <span>*</span></label>
            <select id="feedback-category" name="category" required>
                <option value=""><?= te('feedback.selectCategory') ?></option>
                <option value="feature"><?= te('feedback.catFeature') ?></option>
                <option value="bug"><?= te('feedback.catBug') ?></option>
                <option value="improvement"><?= te('feedback.catImprovement') ?></option>
                <option value="question"><?= te('feedback.catQuestion') ?></option>
                <option value="other"><?= te('feedback.catOther') ?></option>
            </select>
            <label for="feedback-priority"><?= te('feedback.priority') ?></label>
            <select id="feedback-priority" name="priority">
                <option value="low"><?= te('feedback.priLow') ?></option><option value="normal" selected><?= te('feedback.priNormal') ?></option><option value="high"><?= te('feedback.priHigh') ?></option>
            </select>
            <label for="feedback-subject"><?= te('feedback.subject') ?></label>
            <input id="feedback-subject" name="subject" maxlength="180" placeholder="<?= te('feedback.subjectPh') ?>">
            <label for="feedback-message"><?= te('feedback.message') ?> <span>*</span></label>
            <textarea id="feedback-message" name="message" rows="7" minlength="10" maxlength="5000" required placeholder="<?= te('feedback.messagePh') ?>"></textarea>
            <div class="feedback-grid">
                <div><label for="feedback-name"><?= te('feedback.name') ?></label><input id="feedback-name" name="name" maxlength="120"></div>
                <div><label for="feedback-email"><?= te('feedback.email') ?></label><input id="feedback-email" name="email" type="email" maxlength="190" placeholder="<?= te('feedback.emailPh') ?>"></div>
            </div>
            <input class="feedback-honeypot" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
            <p id="feedback-status" class="feedback-status" role="status"></p>
            <button class="feedback-submit" type="submit"><?= te('feedback.submit') ?></button>
        </form>
    </main>
    <script src="/js/feedback.js"></script>
</body>
</html>
