// Chart Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Registrations Chart
    const ctx = document.getElementById('registrationsChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                    label: 'Nouvelles inscriptions',
                    data: [12, 19, 15, 22, 28, 35, 42, 38, 45, 52, 48, 55],
                    borderColor: '#4A90E2',
                    backgroundColor: 'rgba(74, 144, 226, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#4A90E2',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            color: getComputedStyle(document.body).getPropertyValue('--text-muted')
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: getComputedStyle(document.body).getPropertyValue('--text-muted')
                        }
                    }
                }
            }
        });
    }

    // Mobile Sidebar Toggle
    window.toggleSidebar = function() {
        document.querySelector('.admin-sidebar').classList.toggle('open');
    };

    // Delete Confirmation
    let deleteId = null;
    window.confirmDelete = function(id) {
        deleteId = id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    };

    document.getElementById('confirmDeleteBtn')?.addEventListener('click', function() {
        if (deleteId) {
            // AJAX delete request here
            console.log('Deleting member:', deleteId);
            // window.location.href = `delete_member.php?id=${deleteId}`;
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        }
    });

    // Auto-hide success banner after 5 seconds
    setTimeout(() => {
        const banner = document.querySelector('.success-banner');
        if (banner) {
            banner.style.transition = 'opacity 0.5s, transform 0.5s';
            banner.style.opacity = '0';
            banner.style.transform = 'translateY(-10px)';
            setTimeout(() => banner.remove(), 500);
        }
    }, 5000);
});
