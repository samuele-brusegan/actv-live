<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invia feedback - ACTV Live</title>
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/feedback.css">
</head>
<body>
    <header class="header-green compact">
        <a href="/" class="back-button" aria-label="Torna alla home">←</a>
        <h1 class="header-title">Invia feedback</h1>
        <p class="header-subtitle">Aiutaci a rendere ACTV Live più utile.</p>
    </header>
    <main class="feedback-page">
        <form id="feedback-form" class="feedback-card" novalidate>
            <div class="feedback-intro">Segnalaci un problema, proponi una funzione o raccontaci la tua esperienza. Non è necessario lasciare dati personali.</div>
            <label for="feedback-category">Tipo di feedback <span>*</span></label>
            <select id="feedback-category" name="category" required>
                <option value="">Seleziona una categoria</option>
                <option value="feature">Nuova funzionalità</option>
                <option value="bug">Bug o problema</option>
                <option value="improvement">Miglioramento</option>
                <option value="question">Domanda</option>
                <option value="other">Altro</option>
            </select>
            <label for="feedback-priority">Priorità percepita</label>
            <select id="feedback-priority" name="priority">
                <option value="low">Bassa</option><option value="normal" selected>Normale</option><option value="high">Alta</option>
            </select>
            <label for="feedback-subject">Titolo</label>
            <input id="feedback-subject" name="subject" maxlength="180" placeholder="In breve, di cosa si tratta?">
            <label for="feedback-message">Messaggio <span>*</span></label>
            <textarea id="feedback-message" name="message" rows="7" minlength="10" maxlength="5000" required placeholder="Descrivi cosa è successo o cosa vorresti migliorare..."></textarea>
            <div class="feedback-grid">
                <div><label for="feedback-name">Nome (facoltativo)</label><input id="feedback-name" name="name" maxlength="120"></div>
                <div><label for="feedback-email">Email (facoltativa)</label><input id="feedback-email" name="email" type="email" maxlength="190" placeholder="Per eventuale risposta"></div>
            </div>
            <input class="feedback-honeypot" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
            <p id="feedback-status" class="feedback-status" role="status"></p>
            <button class="feedback-submit" type="submit">Invia feedback</button>
        </form>
    </main>
    <script src="/js/feedback.js"></script>
</body>
</html>
