/**
 * StopCard Component
 * A reusable component for displaying stop/station cards
 * Matches the style from stopList.php
 */

class StopCard {
    /**
     * Create a stop card HTML element
     * @param {Object} stop - Stop data object
     * @param {string|number} stop.id - Primary stop ID
     * @param {Array<string|number>} [stop.ids] - All stop IDs (for merged stops)
     * @param {string} stop.name - Stop name
     * @param {Array} [stop.lines] - Array of line objects
     * @param {boolean} [options.isFavorite=false] - Whether to show favorite icon
     * @param {Function} [options.onClick] - Click handler function
     * @param {boolean} [options.showIds=true] - Whether to show stop IDs
     * @returns {string} HTML string for the stop card
     */
    static create(stop, options = {}) {
        const {
            isFavorite = false,
            onClick = null,
            showIds = true
        } = options;

        // Generate lines HTML
        const linesHtml = stop.lines && stop.lines.length > 0
            ? stop.lines.slice(0, 3).map(line =>
                `<span class="line-badge">${line.alias || line.line}</span>`
            ).join('')
            : 'Nessuna linea';

        // Generate stop IDs badges (multiple if merged)
        let stopIdsHtml = '';
        if (showIds) {
            const ids = stop.ids && stop.ids.length > 0 ? stop.ids : [stop.id];
            stopIdsHtml = ids.map(id =>
                `<div class="stop-id-badge">${id}</div>`
            ).join('');
        }

        // Generate onclick attribute
        const onclickAttr = onClick
            ? `onclick='${onClick.name}(${JSON.stringify(stop)})'`
            : '';

        return /* html */`
            <div class="stop-card" ${onclickAttr}>
                <div class="stop-card-content">
                    ${showIds ? `<div class="stop-ids-container">${stopIdsHtml}</div>` : ''}
                    <div class="stop-info">
                        <div class="stop-name">${stop.name}</div>
                        <div class="stop-desc">${linesHtml}</div>
                    </div>
                </div>
                <div class="stop-card-action">
                    ${isFavorite ? '<span class="favorite-icon">★</span>' : '<span class="arrow-icon">›</span>'}
                </div>
            </div>
        `;
    }

    /**
     * Create multiple stop cards
     * @param {Array<Object>} stops - Array of stop objects
     * @param {Object} options - Options object (same as create method)
     * @returns {string} HTML string for all stop cards
     */
    static createMultiple(stops, options = {}) {
        return stops.map(stop => StopCard.create(stop, options)).join('');
    }

    /**
     * Render stop cards into a container
     * @param {HTMLElement|string} container - Container element or selector
     * @param {Array<Object>} stops - Array of stop objects
     * @param {Object} options - Options object (same as create method)
     */
    static render(container, stops, options = {}) {
        const element = typeof container === 'string'
            ? document.querySelector(container)
            : container;

        if (!element) {
            console.error('StopCard: Container not found');
            return;
        }

        element.innerHTML = StopCard.createMultiple(stops, options);
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = StopCard;
}
