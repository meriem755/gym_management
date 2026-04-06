<?php
session_start();
require_once '../config/database.php';

// 🔐 Security: Admin access only
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// 📊 Fetch Dashboard Statistics
$stats = [];

// Total Members
$stmt = $conn->query("SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'membre'");
$stats['total_members'] = $stmt->fetch()['total'];

// Active Members (logged in last 7 days)
$stmt = $conn->query("SELECT COUNT(*) as active FROM utilisateurs WHERE role = 'membre' AND dernier_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['active_members'] = $stmt->fetch()['active'];

// Total Staff
$stmt = $conn->query("SELECT COUNT(*) as total FROM utilisateurs WHERE role IN ('admin','gerant','receptionniste','coach')");
$stats['total_staff'] = $stmt->fetch()['total'];

// Pending Registrations (if you have a pending status)
$stmt = $conn->query("SELECT COUNT(*) as pending FROM utilisateurs WHERE statut = 'inactif'");
$stats['pending'] = $stmt->fetch()['pending'];

// Recent Activity
$stmt = $conn->query("
    SELECT u.nom, u.prenom, u.role, u.dernier_login, u.statut 
    FROM utilisateurs u 
    ORDER BY u.dernier_login DESC 
    LIMIT 5
");
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue This Month (if you have a payments table)
// $stmt = $conn->query("SELECT SUM(montant) as total FROM paiements WHERE MONTH(date_paiement) = MONTH(NOW())");
// $stats['revenue'] = $stmt->fetch()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛡️ Dashboard Admin - Gym Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="light-theme">

<!-- Theme Toggle -->
<div class="theme-toggle">
    <button id="themeSwitcher" class="btn theme-btn" title="Changer de thème">
        <i class="fas fa-moon"></i>
    </button>
</div>

<div class="admin-wrapper">
    
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                <span>ADMIN PANEL</span>
            </div>
            <div class="admin-info">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1)); ?>
                </div>
                <div class="admin-details">
                    <strong><?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?></strong>
                    <small class="text-muted">Administrateur</small>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
            <a href="members.php" class="nav-link">
                <i class="fas fa-users"></i> Gestion des membres
                <?php if($stats['pending'] > 0): ?>
                    <span class="badge badge-danger"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
            <a href="staff.php" class="nav-link">
                <i class="fas fa-user-tie"></i> Équipe & Staff
            </a>
            <a href="subscriptions.php" class="nav-link">
                <i class="fas fa-credit-card"></i> Abonnements
            </a>
            <a href="schedule.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i> Planning
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-line"></i> Rapports
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i> Paramètres
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        
        <!-- Top Header -->
        <header class="admin-header">
            <div class="header-left">
                <button class="btn-menu-mobile d-lg-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h2 class="page-title">
                    <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                    Tableau de bord
                </h2>
            </div>
            <div class="header-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher un membre...">
                </div>
                <div class="header-actions">
                    <button class="btn-action" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if($stats['pending'] > 0): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </button>
                    <button class="btn-action" title="Messages">
                        <i class="fas fa-envelope"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Success Banner -->
        <div class="success-banner">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Bienvenue, <?php echo $_SESSION['prenom']; ?>!</strong> 
            Vous êtes connecté en tant qu'administrateur.
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_members']); ?></h3>
                    <p>Membres totaux</p>
                    <small class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> +12% ce mois
                    </small>
                </div>
            </div>

            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['active_members']); ?></h3>
                    <p>Membres actifs</p>
                    <small class="text-muted">Derniers 7 jours</small>
                </div>
            </div>

            <div class="stat-card stat-warning">
                <div class="stat-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['pending']); ?></h3>
                    <p>En attente</p>
                    <small class="text-muted">Comptes à activer</small>
                </div>
            </div>

            <div class="stat-card stat-info">
                <div class="stat-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_staff']); ?></h3>
                    <p>Staff & Coachs</p>
                    <small class="text-muted">Équipe active</small>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h5 class="section-title"><i class="fas fa-bolt me-2"></i>Actions rapides</h5>
            <div class="actions-grid">
                <a href="add_member.php" class="action-card">
                    <div class="action-icon bg-primary">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <span>Ajouter un membre</span>
                </a>
                <a href="add_subscription.php" class="action-card">
                    <div class="action-icon bg-success">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <span>Nouvel abonnement</span>
                </a>
                <a href="schedule.php" class="action-card">
                    <div class="action-icon bg-warning">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <span>Planifier cours</span>
                </a>
                <a href="reports.php" class="action-card">
                    <div class="action-icon bg-info">
                        <i class="fas fa-file-export"></i>
                    </div>
                    <span>Exporter rapport</span>
                </a>
            </div>
        </div>

        <!-- Main Grid: Charts + Recent Activity -->
        <div class="dashboard-grid">
            
            <!-- Chart Section -->
            <div class="card chart-card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-area me-2"></i>Évolution des inscriptions</h5>
                    <select class="form-select form-select-sm w-auto ms-auto">
                        <option>Cette année</option>
                        <option>Ce mois</option>
                        <option>Cette semaine</option>
                    </select>
                </div>
                <div class="card-body">
                    <canvas id="registrationsChart" height="250"></canvas>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card activity-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-history me-2"></i>Activité récente</h5>
                    <a href="activity-log.php" class="btn btn-sm btn-link">Voir tout</a>
                </div>
                <div class="card-body p-0">
                    <div class="activity-list">
                        <?php foreach($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-avatar">
                                <?php echo strtoupper(substr($activity['prenom'], 0, 1) . substr($activity['nom'], 0, 1)); ?>
                            </div>
                            <div class="activity-info">
                                <strong><?php echo $activity['prenom'] . ' ' . $activity['nom']; ?></strong>
                                <small class="text-muted"><?php echo ucfirst($activity['role']); ?></small>
                            </div>
                            <div class="activity-time">
                                <?php 
                                    $login = strtotime($activity['dernier_login']);
                                    echo $login ? date('H:i', $login) : 'Jamais';
                                ?>
                            </div>
                            <span class="badge <?php echo $activity['statut'] === 'actif' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo ucfirst($activity['statut']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Member Table Preview -->
        <div class="card members-preview">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-users me-2"></i>Derniers membres inscrits</h5>
                <a href="members.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-list me-1"></i>Voir tous
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Membre</th>
                                <th>Email</th>
                                <th>Date d'inscription</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $conn->query("
                                SELECT id, membre_id, nom, prenom, email, date_creation, statut 
                                FROM utilisateurs 
                                WHERE role = 'membre' 
                                ORDER BY date_creation DESC 
                                LIMIT 5
                            ");
                            while($member = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($member['membre_id']); ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="member-avatar-sm">
                                            <?php echo strtoupper(substr($member['prenom'], 0, 1) . substr($member['nom'], 0, 1)); ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($member['prenom'] . ' ' . $member['nom']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($member['date_creation'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $member['statut'] === 'actif' ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo ucfirst($member['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn btn-outline-primary" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-outline-danger" title="Supprimer" onclick="confirmDelete(<?php echo $member['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="admin-footer">
            <div class="footer-content">
                <span>© <?php echo date('Y'); ?> Gym Management System</span>
                <span class="text-muted">v1.0.0 • Connecté en tant qu'Administrateur</span>
            </div>
        </footer>

    </main>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">⚠️ Confirmation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer ce membre ? Cette action est irréversible.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
<script src="../assets/js/theme.js"></script>
<script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>
