function toggleInfo(id) {
    var infoField = document.getElementById(id);
    infoField.style.display = (infoField.style.display === "block") ? "none" : "block";
}

document.addEventListener('click', function(event) {
    var infoFields = document.querySelectorAll('.info-field');
    infoFields.forEach(function(infoField) {
        if (!infoField.contains(event.target) && !event.target.classList.contains('info-icon')) {
            infoField.style.display = 'none';
        }
    });
});

function openExternInfoSite(info) {
    var url = "";
    switch(info) {
        case "BotToken":
            url = "https://niklas.craft.me/Untis-Benachrichtigungen-Anleitung/b/717766C2-54B5-48A3-8677-0BC20BB2221A/Slack-Bot-Token-generieren";
            break;
        case "TageInVorraus":
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

document.querySelector('form').addEventListener('submit', function(event) {
    var schoolUrlInput = document.getElementById('schoolUrl');
    if (schoolUrlInput.value.trim() === '') {
        schoolUrlInput.value = schoolUrlInput.placeholder;
    }
});





document.addEventListener('DOMContentLoaded', () => {
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (prefersDarkScheme) {
        document.body.classList.add('dark-mode');
    }

    const toggleButton = document.getElementById('toggle-theme');
    toggleButton.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
    });
});