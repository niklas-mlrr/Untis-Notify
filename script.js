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
            url = "https://niklas.craft.me/Untis-Benachrichtigungen-Anleitung/b/717766C2-54B5-48A3-8677-0BC20BB2221A/Slack-Bot-Token-generieren";
            break;
        case "TageInVoraus":
            url = "https://niklas.craft.me/Untis-Benachrichtigungen-Anleitung/b/594ADF73-6DE4-40AB-9B9A-A668C4A53721/F%C3%BCr-wie-viele-Tage-im-Voraus-m%C3%B6chtes";
            break;
        case "username":
            url = "https://niklas.craft.me/Untis-Benachrichtigungen-Anleitung/b/F4DD6474-D984-4B0C-87FC-F7C9D6CE5E11/Untis-Username-sehen";
            break;
        case "password":
            url = "https://niklas.craft.me/Untis-Benachrichtigungen-Anleitung/b/880025AB-BCD2-428C-A36E-6FF4540F6E41/Untis-Passwort-%C3%A4ndern";
            break;
        case "dictionary":
            url = "https://niklas.craft.me/Untis-Benachrichtigungen-Anleitung/b/E86C4748-2AA7-42E7-98EA-9903F9144F77/Personalisierbares-Dictionary";
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
        document.body.classList.toggle('dark-mode');
        const theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
        localStorage.setItem('theme', theme);
        toggleIcon.classList.toggle('dark-mode-switch-icon-white');
    });
});