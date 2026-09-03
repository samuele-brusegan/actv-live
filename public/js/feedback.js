document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('feedback-form');
    const status = document.getElementById('feedback-status');
    if (!form) return;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true; status.className = 'feedback-status'; status.textContent = 'Invio in corso…';
        try {
            const response = await fetch('/api/feedback', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(Object.fromEntries(new FormData(form))) });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.error || 'Invio non riuscito.');
            status.className = 'feedback-status success'; status.textContent = data.message;
            form.reset();
        } catch (error) {
            status.className = 'feedback-status error'; status.textContent = error.message || 'Impossibile inviare il feedback.';
        } finally { button.disabled = false; }
    });
});
