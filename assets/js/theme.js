// Theme Switcher
document.addEventListener('DOMContentLoaded', function() {
    const themeSwitcher = document.getElementById('themeSwitcher');
    const body = document.body;
    const icon = themeSwitcher.querySelector('i');

    // Check for saved theme preference or default to light theme
    const currentTheme = localStorage.getItem('theme') || 'light';
    body.classList.remove('light-theme', 'dark-theme');
    body.classList.add(currentTheme + '-theme');
    updateIcon(currentTheme);

    themeSwitcher.addEventListener('click', function() {
        if (body.classList.contains('light-theme')) {
            body.classList.remove('light-theme');
            body.classList.add('dark-theme');
            localStorage.setItem('theme', 'dark');
            updateIcon('dark');
        } else {
            body.classList.remove('dark-theme');
            body.classList.add('light-theme');
            localStorage.setItem('theme', 'light');
            updateIcon('light');
        }
    });

    function updateIcon(theme) {
        if (theme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    }
});
