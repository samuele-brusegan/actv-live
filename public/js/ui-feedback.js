(function () {
    function openDialog(title, message, confirmMode) {
        return new Promise(resolve => {
            const backdrop = document.createElement('div'); backdrop.className = 'actv-dialog-backdrop';
            backdrop.innerHTML = `<section class="actv-dialog" role="dialog" aria-modal="true" aria-labelledby="actv-dialog-title"><h2 id="actv-dialog-title"></h2><p class="actv-dialog-message"></p><div class="actv-dialog-actions"><button type="button" class="btn-secondary actv-dialog-cancel">Annulla</button><button type="button" class="btn-primary actv-dialog-ok">${confirmMode ? 'Conferma' : 'Chiudi'}</button></div></section>`;
            backdrop.querySelector('h2').textContent = title; backdrop.querySelector('.actv-dialog-message').textContent = message;
            const close = value => { backdrop.remove(); resolve(value); };
            backdrop.querySelector('.actv-dialog-ok').addEventListener('click', () => close(true));
            backdrop.querySelector('.actv-dialog-cancel').addEventListener('click', () => close(false));
            if (!confirmMode) backdrop.querySelector('.actv-dialog-cancel').remove();
            backdrop.addEventListener('click', event => { if (event.target === backdrop) close(false); });
            document.body.appendChild(backdrop); backdrop.querySelector('.actv-dialog-ok').focus();
        });
    }
    window.actvAlert = (message, title = 'Attenzione') => openDialog(title, message, false);
    window.actvConfirm = (message, title = 'Conferma operazione') => openDialog(title, message, true);
})();
