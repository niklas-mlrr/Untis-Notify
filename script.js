function toggleInfo(id) {
    let infoField = document.getElementById(id);
    infoField.style.display = (infoField.style.display === "block") ? "none" : "block";
}

document.addEventListener('click', function(event) {
    let infoFields = document.querySelectorAll('.info-field');
    infoFields.forEach(function(infoField) {
        if (!infoField.contains(event.target) && !event.target.classList.contains('info-icon')) {
            infoField.style.display = 'none';
        }
    });
});

function openExternInfoSite(info) {
    let url = "";
    switch(info) {
        case "BotToken":
            url = "https://github.com/TechNikFuture/Untis-Notify/wiki/Slack-Bot-Token-generieren";
            break;
        case "TageInVoraus":
            url = "https://github.com/TechNikFuture/Untis-Notify/wiki/Wie-viele-Tage-im-Voraus-sollen-auf-%C3%84nderungen-gepr%C3%BCft-werden%3F";
            break;
        case "username":
            url = "https://github.com/TechNikFuture/Untis-Notify/wiki/Untis-Benutzernamen-sehen";
            break;
        case "password":
            url = "https://github.com/TechNikFuture/Untis-Notify/wiki/Untis-Passwort-%C3%A4ndern";
            break;
        case "dictionary":
            url = "https://github.com/TechNikFuture/Untis-Notify/wiki/Personalisierbares-Dictionary";
            break;

    }
    window.open(url, '_blank');
}

document.querySelector('form').addEventListener('submit', function() {
    let schoolUrlInput = document.getElementById('schoolUrl');
    if (schoolUrlInput.value.trim() === '') {
        schoolUrlInput.value = schoolUrlInput.placeholder;
    }
});






document.addEventListener('DOMContentLoaded', () => {
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const currentTheme = localStorage.getItem('theme');

    const toggleButton = document.getElementById('toggle-theme');
    const toggleIcon = toggleButton.querySelector('.dark-mode-switch-icon');

    if (currentTheme === 'dark' || (prefersDarkScheme && currentTheme === null)) {
        document.body.classList.add('dark-mode');
        toggleIcon.classList.add('dark-mode-switch-icon-white');
    } else if (currentTheme === 'light') {
        document.body.classList.remove('dark-mode');
        toggleIcon.classList.remove('dark-mode-switch-icon-white');
    }

    toggleButton.addEventListener('click', () => {
        event.preventDefault(); // Verhindert das Standardverhalten des Buttons
        console.log("test");
        if(!window.location.href.includes("impressum.php")) {
            document.body.classList.toggle('dark-mode');
            const theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
            localStorage.setItem('theme', theme);
            toggleIcon.classList.toggle('dark-mode-switch-icon-white');
        } else {
            window.location.replace("index.php");
        }
    });
});


function togglePasswordVisibility() {
    var passwordField = document.getElementById('password');
    var toggleIcon = document.getElementById('toggleIcon');
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    }
}