/*
 * Copyright (c) 2025. Brusegan Samuele, Davanzo Andrea
 * Questo file fa parte di GradeCraft ed è rilasciato
 * sotto la licenza MIT. Vedere il file LICENSE per i dettagli.
 */

class StopListItem extends HTMLElement {

	static watchedList = [
		{
			attribute: "",
			htmlElement: "",
			innerAttribute: "",
		},
	];
	watchedList;

	constructor() {
		super();

		/*
		// 0. Richiedo le variabili globali da sessionStorage
		let URL_PATH = sessionStorage.getItem("url");
		let THEME = sessionStorage.getItem("theme");
		*/

		// 1. Crea lo Shadow DOM
		const shadow = this.attachShadow({mode: 'open'});

		// 2. Definisci la struttura interna (HTML) e lo stile (CSS)
		const template = document.createElement('template');
		template.innerHTML = `<HTML>`;

		// 3. Clona il contenuto del template e aggiungilo allo Shadow DOM
		shadow.appendChild(template.content.cloneNode(true));
	}

	// Passaggio 2: Gestisci gli attributi e i callback
	static get observedAttributes() {
		let array = [];

		StopListItem.watchedList.forEach(element => {
			array.push(element.attribute);
		})

		return array;
	}

	attributeChangedCallback(name, oldValue, newValue) {

		StopListItem.watchedList.forEach(element => {
			if (element.attribute === name) {
				let htmlElement = this.shadowRoot.querySelector(element.htmlElement);
				console.log(htmlElement)
				if (htmlElement) {
					if (element.innerAttribute !== "") {
						let tag = element.innerAttribute;
						htmlElement[tag] = newValue;
					} else {
						htmlElement.textContent = newValue;
					}
				}
			}
		})
	}

	connectedCallback() {
	}
}

// Passaggio 3: Registra il tuo elemento personalizzato
customElements.define('StopListItem', StopListItem);