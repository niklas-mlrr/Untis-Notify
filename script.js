hideLoadingAnimation();

if(window.location.href.includes("settingsSaved") || window.location.href.includes("testNotification") || window.location.href.includes("noEmailAdress") || window.location.href.includes("accountNotDeleted")) {
    scrollToBottom();
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
        case "username":
            url = "https://github.com/niklas-mlrr/Untis-Notify/wiki/Untis-Benutzernamen-sehen";
            break;
        case "password":
            url = "https://github.com/niklas-mlrr/Untis-Notify/wiki/Untis-Passwort-%C3%A4ndern";
            break;
        case "EmailAdress":
            url = "https://github.com/niklas-mlrr/Untis-Notify/wiki/Email-Adresse";
            break;
        case "dictionary":
            url = "https://github.com/niklas-mlrr/Untis-Notify/wiki/Personalisierbares-Dictionary-(optional)";
            break;
        case "TageInVoraus":
            url = "https://github.com/niklas-mlrr/Untis-Notify/wiki/Wie-viele-Tage-im-Voraus-sollen-auf-%C3%84nderungen-gepr%C3%BCft-werden%3F";
            break;

    }
    window.open(url, '_blank');
}




document.addEventListener('DOMContentLoaded', () => {
    const toggleButton = document.getElementById('toggle-theme');
    const toggleIcon = document.querySelector('.dark-mode-switch-icon');
    const loader = document.querySelector('.loader');
    const navigateBackButton = document.querySelector('.navigate-back-icon');

    if (!toggleButton || !toggleIcon || !loader) {
        console.error('Element(s) not found in the DOM');
        return;
    }



    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const savedTheme = localStorage.getItem('theme');
    const currentTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');

    if (currentTheme === 'dark') {
        document.body.classList.add('dark-mode');
        toggleIcon.classList.add('dark-mode-switch-icon-white');
        loader.classList.add('loader-white');
        if (navigateBackButton) {
            navigateBackButton.classList.add('navigate-back-icon-white');
        }
    } else {
        document.body.classList.remove('dark-mode');
        toggleIcon.classList.remove('dark-mode-switch-icon-white');
        loader.classList.remove('loader-white');
        if (navigateBackButton) {
            navigateBackButton.classList.remove('navigate-back-icon-white');
        }
    }

    toggleButton.addEventListener('click', (event) => {
        event.preventDefault(); // Prevents the default behavior of the button
        document.body.classList.toggle('dark-mode');
        const theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
        localStorage.setItem('theme', theme);
        toggleIcon.classList.toggle('dark-mode-switch-icon-white');
        loader.classList.toggle('loader-white');
        if (navigateBackButton) {
            navigateBackButton.classList.toggle('navigate-back-icon-white');
        }
    });

    if (navigateBackButton) {
        navigateBackButton.addEventListener('click', (event) => {
            event.preventDefault(); // Prevents the default behavior of the button
            if (window.location.href.includes("impressum")) {
                window.location.href = 'login';
            } else if (window.location.href.includes("admin")) {
                window.location.href = 'settings';
            }
        });
    }
});


function togglePasswordVisibility() {
    let passwordField = document.getElementById('password');
    let toggleIcon = document.getElementById('toggleIcon');
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




document.querySelectorAll('.checkbox-input').forEach(input => {
    input.addEventListener('change', function() {
        if (this.checked) {
            this.closest('.checkbox-wrapper').classList.add('selected');
        } else {
            this.closest('.checkbox-wrapper').classList.remove('selected');
        }
    });
});


function showLoadingAnimation() {
    document.querySelectorAll('form').forEach(form => {
        form.classList.add('overlay');
    });
    document.getElementById('loading-animation').style.display = 'block';
}

function hideLoadingAnimation() {
    document.querySelectorAll('form').forEach(form => {
        form.classList.remove('overlay');
    });
    document.getElementById('loading-animation').style.display = 'none';
}



function scrollToBottom() {
    document.getElementById('parent').scrollIntoView({ behavior: 'smooth', block: 'end' });
}