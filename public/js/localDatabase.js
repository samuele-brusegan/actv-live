class LocalDatabase {
    /**
     * @param {string} dbName - Il nome del database.
     * @param {number} version - La versione del database.
     */
    constructor(dbName, version) {
        if (!window.indexedDB) {
            console.error("Il tuo browser non supporta IndexedDB. Riprova con un browser più recente.");
            return;
        }
        this.dbName = dbName;
        this.version = version;
        this.db = null;
        this.lastIds = {}; // Oggetto per tenere traccia dell'ultimo ID generato/massimo ID esistente per ogni object store
        this.objectStoreKeyPaths = {}; // Oggetto per memorizzare il keyPath di ogni object store
    }

    /**
     * Apre il database e crea un object store se non esiste.
     * Recupera anche i keyPath e i massimi ID esistenti per tutti gli object store.
     * @param {string} objectStoreName - Il nome dell'object store da aprire/creare.
     * @param {string} keyPath - La chiave primaria per l'object store.
     * @returns {Promise<IDBDatabase>} Una promessa che si risolve con l'istanza del database.
     */
    open(objectStoreName, keyPath) {
        // Memorizza il keyPath *inteso* per l'object store corrente.
        // Questo sarà sovrascritto se l'object store esiste già con un keyPath diverso.
        this.objectStoreKeyPaths[objectStoreName] = keyPath;

        return new Promise((resolve, reject) => {
            const request = window.indexedDB.open(this.dbName, this.version);

            request.onerror = (event) => {
                reject(`Errore nell'apertura del database: ${event.target.errorCode}`);
            };

            request.onsuccess = async (event) => {
                this.db = event.target.result;
                console.log("Database aperto con successo!");

                const initPromises = [];

                // Popola i keyPath e i lastIds per tutti gli object store esistenti
                for (const storeName of this.db.objectStoreNames) {
                    try {
                        const tempTransaction = this.db.transaction([storeName], "readonly");
                        const tempStore = tempTransaction.objectStore(storeName);
                        // Recupera il keyPath effettivo dall'object store
                        this.objectStoreKeyPaths[storeName] = tempStore.keyPath;

                        // Se il keyPath è presente, inizializza lastIds per questo store
                        if (this.objectStoreKeyPaths[storeName]) {
                            initPromises.push(
                                (async () => {
                                    const maxId = await this._getMaxId(this.db, storeName);
                                    this.lastIds[storeName] = maxId;
                                })()
                            );
                        }
                    } catch (e) {
                        console.warn(`Impossibile recuperare dettagli per lo store '${storeName}':`, e);
                    }
                }

                // Attendi che tutti i massimi ID siano stati recuperati
                await Promise.all(initPromises);

                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains(objectStoreName)) {
                    // Crea un nuovo object store
                    const objectStore = db.createObjectStore(objectStoreName, { keyPath: keyPath });
                    console.log(`Object Store '${objectStoreName}' creato!`);
                    // Per i nuovi store, il keyPath è quello appena definito.
                    this.objectStoreKeyPaths[objectStoreName] = keyPath;
                    this.lastIds[objectStoreName] = 0; // Inizializza a 0 per un nuovo store
                } else {
                    // L'object store esiste già. Controlla se il keyPath fornito è diverso da quello esistente.
                    // Nota: non puoi modificare il keyPath di un object store esistente in upgrade.
                    // Puoi solo eliminarlo e ricrearlo, o gestire la migrazione dei dati.
                    const existingStore = event.target.transaction.objectStore(objectStoreName);
                    if (existingStore.keyPath !== keyPath) {
                        console.warn(`Il keyPath fornito '${keyPath}' per lo store '${objectStoreName}' non corrisponde al keyPath esistente '${existingStore.keyPath}'.`);
                        // Per coerenza, si può scegliere di sovrascrivere con l'esistente o avvisare l'utente.
                        // La logica in onsuccess recupererà il keyPath effettivo dopo l'upgrade.
                    }
                }
            };
        });
    }

    /**
     * Aggiunge un record all'object store.
     * @param {string} objectStoreName - Il nome dell'object store.
     * @param {object} record - L'oggetto da aggiungere.
     * @returns {Promise<void>} Una promessa che si risolve al completamento.
     */
    add(objectStoreName, record) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([objectStoreName], "readwrite");
            const objectStore = transaction.objectStore(objectStoreName);
            const request = objectStore.add(record);

            request.onsuccess = () => {
                resolve();
            };

            request.onerror = (event) => {
                reject(`Errore nell'aggiunta del record:\n\t`, event.target.errorCode);
            };
        });
    }

    /**
     * Aggiunge un record all'object store, generando automaticamente un ID numerico.
     * @param {string} objectStoreName - Il nome dell'object store.
     * @param {object} record - L'oggetto da aggiungere.
     * @returns {Promise<number>} Una promessa che si risolve con l'ID del nuovo record.
     */
    push(objectStoreName, record) {
        return new Promise((resolve, reject) => {
            const keyPath = this.objectStoreKeyPaths[objectStoreName];
            if (!keyPath) {
                return reject(`keyPath non definito per l'object store '${objectStoreName}'. Assicurati di chiamare open() per questo object store prima di usare push().`);
            }

            // Inizializza o incrementa l'ultimo ID per questo object store
            // '|| 0' gestisce il caso in cui lastIds[objectStoreName] non sia ancora stato impostato
            this.lastIds[objectStoreName] = (this.lastIds[objectStoreName] || 0) + 1;
            const newId = this.lastIds[objectStoreName];

            // Aggiungi la chiave ID al record
            record[keyPath] = newId;

            const transaction = this.db.transaction([objectStoreName], "readwrite");
            const objectStore = transaction.objectStore(objectStoreName);
            const request = objectStore.add(record);

            request.onsuccess = () => {
                resolve(newId); // Risolve con il nuovo ID generato
            };

            request.onerror = (event) => {
                // Se c'è un errore, decrementa l'ID per evitare buchi nella sequenza
                // nel caso in cui il record non sia stato effettivamente aggiunto
                if (this.lastIds[objectStoreName] > 0) {
                    this.lastIds[objectStoreName]--;
                }
                reject(`Errore nell'aggiunta del record con ID ${newId}:\n\t`, event.target.errorCode);
            };
        });
    }

    /**
     * Recupera un record tramite la sua chiave primaria.
     * @param {string} objectStoreName - Il nome dell'object store.
     * @param {string|number} key - La chiave del record.
     * @returns {Promise<object|undefined>} Una promessa che si risolve con il record o undefined.
     */
    get(objectStoreName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([objectStoreName], "readonly");
            const objectStore = transaction.objectStore(objectStoreName);
            const request = objectStore.get(key);

            request.onsuccess = (event) => {
                resolve(event.target.result);
            };

            request.onerror = (event) => {
                reject(`Errore nella lettura del record: ${event.target.errorCode}`);
            };
        });
    }

    /**
     * Recupera tutti i record dall'object store.
     * @param {string} objectStoreName - Il nome dell'object store.
     * @returns {Promise<Array<object>>} Una promessa che si risolve con un array di tutti i record.
     */
    getAll(objectStoreName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([objectStoreName], "readonly");
            const objectStore = transaction.objectStore(objectStoreName);
            const request = objectStore.getAll();

            request.onsuccess = (event) => {
                resolve(event.target.result);
            };

            request.onerror = (event) => {
                reject(`Errore nella lettura di tutti i record: ${event.target.errorCode}`);
            };
        });
    }

    /**
     * Aggiorna un record esistente.
     * @param {string} objectStoreName - Il nome dell'object store.
     * @param {object} record - L'oggetto da aggiornare.
     * @returns {Promise<void>} Una promessa che si risolve al completamento.
     */
    put(objectStoreName, record) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([objectStoreName], "readwrite");
            const objectStore = transaction.objectStore(objectStoreName);
            const request = objectStore.put(record);

            request.onsuccess = () => {
                resolve();
            };

            request.onerror = (event) => {
                reject(`Errore nell'aggiornamento del record: ${event.target.errorCode}`);
            };
        });
    }

    /**
     * Cancella un record tramite la sua chiave primaria.
     * @param {string} objectStoreName - Il nome dell'object store.
     * @param {string|number} key - La chiave del record da cancellare.
     * @returns {Promise<void>} Una promessa che si risolve al completamento.
     */
    delete(objectStoreName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([objectStoreName], "readwrite");
            const objectStore = transaction.objectStore(objectStoreName);
            const request = objectStore.delete(key);

            request.onsuccess = () => {
                resolve();
            };

            request.onerror = (event) => {
                reject(`Errore nella cancellazione del record: ${event.target.errorCode}`);
            };
        });
    }

    /**
     * Metodo interno per recuperare il massimo ID numerico in un object store.
     * @param {IDBDatabase} db - L'istanza del database.
     * @param {string} objectStoreName - Il nome dell'object store.
     * @returns {Promise<number>} Una promessa che si risolve con il massimo ID o 0 se non ci sono ID numerici.
     * @private
     */
    _getMaxId(db, objectStoreName) {
        return new Promise((resolve) => {
            const transaction = db.transaction([objectStoreName], "readonly");
            const store = transaction.objectStore(objectStoreName);
            let maxId = 0;

            const request = store.getAllKeys(); // Ottiene tutte le chiavi in modo efficiente

            request.onsuccess = (event) => {
                const keys = event.target.result;
                if (keys && keys.length > 0) {
                    // Filtra solo le chiavi numeriche e trova il massimo
                    const numericKeys = keys.filter(key => typeof key === 'number');
                    if (numericKeys.length > 0) {
                        maxId = Math.max(...numericKeys);
                    }
                }
                resolve(maxId);
            };
            request.onerror = () => {
                console.error(`Errore durante il recupero delle chiavi per lo store '${objectStoreName}'.`);
                resolve(0); // In caso di errore, assume 0
            };
        });
    }
}