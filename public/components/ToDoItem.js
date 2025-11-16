class ToDoItem extends HTMLElement {
	constructor() {
		super();


		let URL_PATH = sessionStorage.getItem("url");
		let THEME = sessionStorage.getItem("theme");

		// 1. Crea lo Shadow DOM
		const shadow = this.attachShadow({mode: 'open'});

		// 2. Definisci la struttura interna (HTML) e lo stile (CSS)
		const template = document.createElement('template');
		template.innerHTML = `
        <style>
            a {
                text-decoration: none;
                color: inherit;
            }
            
            .icon {
                filter: invert(100%);
            }
        
            .todo {
				border: 1px solid var(--todo-border-color);
				padding: 10px;
				margin-bottom: 10px;
				border-radius: 5px;
				background-color: var(--todo-background-color);
				position: relative;
				display: flex;
				justify-content: space-between;
				
				& > .sx {
					& > .title {
						font-size: 1.2em;
						font-weight: bold;
					}
					
					& > .description {
						font-size: 0.9em;
					}
				}
				
				& > .dx {
					text-align: right;
					& > .due-date {
						font-size: 0.9em;
					}
					& > .actions {
					
					}
				}
				
			}
			
			.completed {
				border: 1px solid var(--todo-border-color);
				filter: opacity(50%);
				background-color: var(--todo-background-color);
				
			}
        </style>
       
        <div class="todo">
            <div class="sx">
                <div class="title"></div>
                <div class="description"></div>
            </div>
            <div class="dx">
                <div class="due-date">gg/mm/yy</div>
                <div class="actions">
                    <a href="#" class="edit">
                        <img class="icon" src="${URL_PATH}/svg/edit.svg" alt="">
                    </a>
                    <a href="#" class="check">
                        <img class="icon" src="${URL_PATH}/svg/check.svg" alt="">
                    </a>
                </div>
            </div>
        </div>
        `;

		// 3. Clona il contenuto del template e aggiungilo allo Shadow DOM
		shadow.appendChild(template.content.cloneNode(true));
	}

	// Passaggio 2: Gestisci gli attributi e i callback
	static get observedAttributes() {
		return ['title', "description", "due-date", 'completed'];
	}

	attributeChangedCallback(name, oldValue, newValue) {
		let title = this.shadowRoot.querySelector('.title');
		let descr = this.shadowRoot.querySelector('.description');
		let ddate = this.shadowRoot.querySelector('.due-date');

		if (name === 'title' && title) {
			title.textContent = newValue;
		}

		if (name === 'description' && descr) {
			descr.textContent = newValue;
		}

		if (name === 'due-date' && ddate) {
			ddate.textContent = newValue;
		}

		if (name === 'completed' && this.hasAttribute('completed')) {
			this.shadowRoot.querySelector('.todo').classList.add('completed');
		}
	}

	connectedCallback() {
		// Aggiungi un listener per gestire il cambio di stato
		let checkbox = this.shadowRoot.querySelector('.actions>.check');
		if (checkbox) {
			checkbox.addEventListener('click', () => {
				if (this.hasAttribute('completed')) {
					this.setAttribute('completed', '');
				} else {
					this.removeAttribute('completed');
				}
			});
		}
	}
}

// Passaggio 3: Registra il tuo elemento personalizzato
customElements.define('todo-item', ToDoItem);