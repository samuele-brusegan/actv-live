/**
 * Widget condivisibile per fermate ACTV.
 * Genera codice embed (iframe) per incorporare i passaggi
 * di una fermata su siti esterni.
 */

/**
 * Genera l'URL del widget per una fermata
 * @param {string} stopId - ID della fermata
 * @param {string} stopName - Nome della fermata
 * @param {Object} options - Opzioni aggiuntive
 * @returns {string} URL del widget
 */
function getWidgetUrl(stopId, stopName, options) {
    options = options || {};
    var params = new URLSearchParams();
    params.set('stop', stopId);
    if (stopName) params.set('name', stopName);
    if (options.max) params.set('max', String(options.max));
    if (options.theme) params.set('theme', options.theme);

    var base = options.baseUrl || window.location.origin;
    return base + '/widget?' + params.toString();
}

/**
 * Genera il codice embed (iframe) per una fermata
 * @param {string} stopId - ID della fermata
 * @param {string} stopName - Nome della fermata
 * @param {Object} options - Opzioni aggiuntive
 * @returns {string} Codice HTML iframe
 */
function getEmbedCode(stopId, stopName, options) {
    options = options || {};
    var url = getWidgetUrl(stopId, stopName, options);
    var width = options.width || '100%';
    var height = options.height || '400';

    return '<iframe src="' + url + '" width="' + width + '" height="' + height + '" ' +
        'frameborder="0" style="border-radius:12px;border:none;" ' +
        'title="ACTV Live - ' + (stopName || stopId) + '"></iframe>';
}

/**
 * Mostra il dialog di condivisione widget
 * @param {string} stopId - ID della fermata
 * @param {string} stopName - Nome della fermata
 */
function showWidgetDialog(stopId, stopName) {
    var existing = document.getElementById('widget-share-modal');
    if (existing) existing.remove();

    var embedCode = getEmbedCode(stopId, stopName);
    var widgetUrl = getWidgetUrl(stopId, stopName);

    var modal = document.createElement('div');
    modal.id = 'widget-share-modal';
    modal.className = 'widget-modal-overlay';
    modal.innerHTML =
        '<div class="widget-modal">' +
            '<div class="widget-modal-header">' +
                '<span>Condividi Widget</span>' +
                '<button class="widget-modal-close" onclick="closeWidgetDialog()">&times;</button>' +
            '</div>' +
            '<div class="widget-modal-body">' +
                '<p class="widget-modal-desc">Incorpora i passaggi in tempo reale di questa fermata nel tuo sito web.</p>' +
                '<label class="widget-label">Codice embed:</label>' +
                '<textarea class="widget-code" id="widget-embed-code" readonly rows="3">' + embedCode.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</textarea>' +
                '<button class="widget-copy-btn" onclick="copyWidgetCode()">Copia codice</button>' +
                '<label class="widget-label" style="margin-top:12px;">Link diretto:</label>' +
                '<input class="widget-url-input" id="widget-url" readonly value="' + widgetUrl + '">' +
                '<button class="widget-copy-btn widget-copy-link" onclick="copyWidgetLink()">Copia link</button>' +
                '<div class="widget-preview-label">Anteprima:</div>' +
                '<div class="widget-preview">' +
                    '<iframe src="' + widgetUrl + '" width="100%" height="350" frameborder="0" style="border-radius:12px;border:none;"></iframe>' +
                '</div>' +
            '</div>' +
        '</div>';

    document.body.appendChild(modal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeWidgetDialog();
    });
}

/** Chiude il dialog widget */
function closeWidgetDialog() {
    var modal = document.getElementById('widget-share-modal');
    if (modal) modal.remove();
}

/**
 * Mostra il dialog con il codice QR di un URL
 * @param {string} url - URL da codificare nel QR
 */
function showQrDialog(url) {
    var existing = document.getElementById('qr-share-modal');
    if (existing) existing.remove();

    var safeUrl = String(url).replace(/&/g, '&amp;').replace(/"/g, '&quot;')
        .replace(/</g, '&lt;').replace(/>/g, '&gt;');

    var modal = document.createElement('div');
    modal.id = 'qr-share-modal';
    modal.className = 'widget-modal-overlay';
    modal.innerHTML =
        '<div class="widget-modal">' +
            '<div class="widget-modal-header">' +
                '<span>Codice QR</span>' +
                '<button class="widget-modal-close" onclick="closeQrDialog()">&times;</button>' +
            '</div>' +
            '<div class="widget-modal-body">' +
                '<p class="widget-modal-desc">Inquadra il codice per aprire questa pagina su un altro dispositivo.</p>' +
                '<div class="qr-container" id="qr-code-container"></div>' +
                '<label class="widget-label" style="margin-top:12px;">Link:</label>' +
                '<input class="widget-url-input" id="qr-url" readonly value="' + safeUrl + '">' +
                '<button class="widget-copy-btn widget-copy-link" onclick="copyQrLink()">Copia link</button>' +
            '</div>' +
        '</div>';

    document.body.appendChild(modal);

    var container = document.getElementById('qr-code-container');
    if (container) {
        if (typeof qrcode === 'function') {
            try {
                var qr = qrcode(0, 'M');
                qr.addData(String(url));
                qr.make();
                container.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 8, scalable: true });
            } catch (e) {
                container.textContent = 'Impossibile generare il codice QR.';
            }
        } else {
            container.textContent = 'Generatore QR non disponibile.';
        }
    }

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeQrDialog();
    });
}

/** Chiude il dialog QR */
function closeQrDialog() {
    var modal = document.getElementById('qr-share-modal');
    if (modal) modal.remove();
}

/** Copia il link mostrato nel dialog QR */
function copyQrLink() {
    var input = document.getElementById('qr-url');
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(function() {
        showCopyFeedback('Link copiato!');
    });
}

/** Copia il codice embed */
function copyWidgetCode() {
    var textarea = document.getElementById('widget-embed-code');
    if (!textarea) return;
    navigator.clipboard.writeText(textarea.value).then(function() {
        showCopyFeedback('Codice copiato!');
    });
}

/** Copia il link del widget */
function copyWidgetLink() {
    var input = document.getElementById('widget-url');
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(function() {
        showCopyFeedback('Link copiato!');
    });
}

/** Mostra feedback di copia */
function showCopyFeedback(message) {
    var existing = document.getElementById('copy-feedback');
    if (existing) existing.remove();

    var feedback = document.createElement('div');
    feedback.id = 'copy-feedback';
    feedback.className = 'copy-feedback';
    feedback.textContent = message;
    document.body.appendChild(feedback);

    setTimeout(function() { feedback.remove(); }, 2000);
}

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { getWidgetUrl, getEmbedCode, showQrDialog, closeQrDialog, copyQrLink };
}
