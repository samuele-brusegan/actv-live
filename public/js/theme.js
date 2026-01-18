document.addEventListener("DOMContentLoaded", updateTheme);

function toggleTheme() {
    if (localStorage.getItem("theme") === "dark") {
        localStorage.setItem("theme", "light");
    }
    else {
        localStorage.setItem("theme", "dark");
    }
    updateTheme();
}

function updateTheme() {
    if (localStorage.getItem("theme") === "dark") {
        document.documentElement.setAttribute("data-theme", "dark");
        document.documentElement.setAttribute("data-bs-theme", "dark");
        let icon = document.getElementById("theme-icon");
        if (icon) {
            icon.src = "/svg/dark_mode.svg";
        }
    }
    else {
        document.documentElement.setAttribute("data-theme", "light");
        document.documentElement.setAttribute("data-bs-theme", "light");
        let icon = document.getElementById("theme-icon");
        if (icon) {
            icon.src = "/svg/light_mode.svg";
        }
    }
}