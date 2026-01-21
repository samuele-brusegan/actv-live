/**
 * Gestione del tema (chiaro/scuro) dell'applicazione.
 * Utilizza il localStorage per persistere la preferenza dell'utente.
 */

// Inizializza il tema appena il DOM è pronto
document.addEventListener("DOMContentLoaded", updateTheme);

/**
 * Alterna tra il tema chiaro e quello scuro.
 */
function toggleTheme() {
    const currentTheme = localStorage.getItem("theme");
    const newTheme = currentTheme === "dark" ? "light" : "dark";
    
    localStorage.setItem("theme", newTheme);
    updateTheme();
}

/**
 * Applica il tema corrente leggendolo dal localStorage.
 * Aggiorna gli attributi del documento e l'icona del selettore.
 */
function updateTheme() {
    const theme = localStorage.getItem("theme") || "light";
    const isDark = theme === "dark";

    // Imposta gli attributi per CSS e Bootstrap
    document.documentElement.setAttribute("data-theme", theme);
    document.documentElement.setAttribute("data-bs-theme", theme);

    // Aggiorna l'icona del tema se presente nella pagina
    const icon = document.getElementById("theme-icon");
    if (icon) {
        icon.src = isDark ? "/svg/dark_mode.svg" : "/svg/light_mode.svg";
    }
}