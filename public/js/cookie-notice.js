/**
 * Cookie Notice Component
 * Easily reusable cookie consent utility.
 */
class CookieNotice {
    constructor(options = {}) {
        this.options = Object.assign({
            cookieName: 'cookie_consent_accepted',
            title: 'Cookie & Privacy',
            message: 'Utilizziamo i cookie per migliorare la tua esperienza e analizzare il traffico via Google Analytics.',
            acceptLabel: 'Accetta',
            moreLabel: 'Scopri di più',
            moreUrl: '/privacy', // Default privacy path
        }, options);

        this.init();
    }

    init() {
        if (this.hasConsented()) {
            return;
        }

        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.render());
        } else {
            this.render();
        }
    }

    hasConsented() {
        return localStorage.getItem(this.options.cookieName) === 'true';
    }

    setConsent() {
        localStorage.setItem(this.options.cookieName, 'true');
        this.hide();
    }

    render() {
        const container = document.createElement('div');
        container.className = 'cookie-notice-container';
        container.id = 'cookie-notice';

        container.innerHTML = `
            <div class="cookie-notice-content">
                <h4>${this.options.title}</h4>
                <p>${this.options.message}</p>
            </div>
            <div class="cookie-notice-actions">
                <button class="btn btn-primary" id="cookie-accept">${this.options.acceptLabel}</button>
            </div>
        `;

        document.body.appendChild(container);

        // Animate in
        setTimeout(() => container.classList.add('show'), 100);

        // Bind events
        document.getElementById('cookie-accept').addEventListener('click', () => this.setConsent());
    }

    hide() {
        const container = document.getElementById('cookie-notice');
        if (container) {
            container.classList.remove('show');
            setTimeout(() => container.remove(), 500);
        }
    }
}

// Auto-initialize if not already defined
window.addEventListener('load', () => {
    new CookieNotice();
});
