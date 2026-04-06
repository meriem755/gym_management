// Form Validation
(function() {
    'use strict';
    
    const form = document.getElementById('loginForm');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
})();

// Toggle Password Visibility
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

// Remember me functionality
document.addEventListener('DOMContentLoaded', function() {
    const rememberMe = document.getElementById('rememberMe');
    const membreIdInput = document.getElementById('membre_id');
    
    // Load saved member ID
    const savedMemberId = localStorage.getItem('rememberedMemberId');
    if (savedMemberId) {
        membreIdInput.value = savedMemberId;
        rememberMe.checked = true;
    }
    
    // Save member ID when form is submitted
    document.getElementById('loginForm').addEventListener('submit', function() {
        if (rememberMe.checked) {
            localStorage.setItem('rememberedMemberId', membreIdInput.value);
        } else {
            localStorage.removeItem('rememberedMemberId');
        }
    });
});

// Add enter key support
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('#loginForm input');
    inputs.forEach(function(input) {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('#loginForm button[type="submit"]').click();
            }
        });
    });
});
