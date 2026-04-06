<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

$db = new Database();
$conn = $db->getConnection();
$message = '';
$messageType = '';

// Generate Member ID Function (e.g., MEMB-2026-0001)
function generateMemberId($conn) {
    $year = date('Y');
    $stmt = $conn->query("SELECT MAX(id) as max_id FROM membres");
    $row = $stmt->fetch();
    $nextId = ($row['max_id'] ?? 0) + 1;
    return "MEMB-" . $year . "-" . str_pad($nextId, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $membre_id = generateMemberId($conn);
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $tel = $_POST['telephone'];
    $password = 'password123'; // Default password, member changes it later
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        $conn->beginTransaction();

        // 1. Create User
        $stmt = $conn->prepare("INSERT INTO utilisateurs (membre_id, nom, prenom, email, telephone, mot_de_passe, role, statut) 
                                VALUES (:id, :nom, :prenom, :email, :tel, :pass, 'membre', 'actif')");
        $stmt->bindParam(':id', $membre_id);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':prenom', $prenom);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':tel', $tel);
        $stmt->bindParam(':pass', $hashedPassword);
        $stmt->execute();
        $userId = $conn->lastInsertId();

        // 2. Create Member Profile
        $stmt = $conn->prepare("INSERT INTO membres (utilisateur_id, numero_adherent, date_inscription) 
                                VALUES (:uid, :adherent, NOW())");
        $stmt->bindParam(':uid', $userId);
        $stmt->bindParam(':adherent', $membre_id);
        $stmt->execute();

        $conn->commit();
        $message = "Membre ajouté avec succès! Identifiant: <strong>$membre_id</strong>";
        $messageType = "success";
        
    } catch(PDOException $e) {
        $conn->rollBack();
        $message = "Erreur: " . $e->getMessage();
        $messageType = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrire Membre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .sidebar { min-height: 100vh; background: var(--primary-color); color: white; }
        .sidebar a { color: white; text-decoration: none; padding: 10px; display: block; border-bottom: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-0">
            <div class="p-3 text-center"><h5><i class="fas fa-dumbbell me-2"></i>Gym Admin</h5></div>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a href="add_member.php" class="bg-white text-dark"><i class="fas fa-user-plus me-2"></i>Inscrire Membre</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a>
        </div>

        <!-- Form -->
        <div class="col-md-10 p-5">
            <h2><i class="fas fa-user-plus me-2"></i>Inscrire un nouveau membre</h2>
            <hr>
            
            <?php if($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="w-50">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Nom</label>
                        <input type="text" name="nom" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Prénom</label>
                        <input type="text" name="prenom" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Téléphone</label>
                    <input type="text" name="telephone" class="form-control">
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Un ID sera généré automatiquement. Le mot de passe par défaut sera <strong>password123</strong>.
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Enregistrer</button>
                <a href="dashboard.php" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
