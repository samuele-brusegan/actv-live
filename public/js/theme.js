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
        document.getElementById("theme-icon").src = "/svg/dark_mode.svg";
    }
    else {
        document.documentElement.setAttribute("data-theme", "light");
        document.documentElement.setAttribute("data-bs-theme", "light");
        document.getElementById("theme-icon").src = "/svg/light_mode.svg";
    }
}