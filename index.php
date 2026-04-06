<?php
session_start();

if(isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'membre/dashboard.php'));
    exit();
}

require_once 'config/database.php';

$error = '';
$success = '';

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $membre_id = trim($_POST['membre_id']);
    $password = $_POST['password'];

    if(empty($membre_id) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();

            $query = "SELECT id, membre_id, nom, prenom, email, mot_de_passe, role, statut, echec_connexion, verrouillage_jusqu_a 
                      FROM utilisateurs WHERE membre_id = :membre_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":membre_id", $membre_id);
            $stmt->execute();

            if($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if($user['verrouillage_jusqu_a'] && strtotime($user['verrouillage_jusqu_a']) > time()) {
                    $lock_time = ceil((strtotime($user['verrouillage_jusqu_a']) - time()) / 60);
                    $error = "Compte verrouillé. Réessayez dans $lock_time min.";
                } elseif($user['statut'] == 'suspendu') {
                    $error = "Compte désactivé. Contactez l'administration.";
                } elseif($user['statut'] == 'inactif') {
                    $error = "Compte inactif.";
                } elseif(password_verify($password, $user['mot_de_passe'])) {
                    $reset_stmt = $db->prepare("UPDATE utilisateurs SET echec_connexion = 0, verrouillage_jusqu_a = NULL WHERE id = :id");
                    $reset_stmt->bindParam(":id", $user['id']);
                    $reset_stmt->execute();

                    $login_stmt = $db->prepare("UPDATE utilisateurs SET dernier_login = NOW() WHERE id = :id");
                    $login_stmt->bindParam(":id", $user['id']);
                    $login_stmt->execute();

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['membre_id'] = $user['membre_id'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['prenom'] = $user['prenom'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];

                    header("Location: " . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'membre/dashboard.php'));
                    exit();
                } else {
                    $failed_attempts = $user['echec_connexion'] + 1;
                    $lock_until = $failed_attempts >= 3 ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
                    $error = $failed_attempts >= 3 ? "Trop de tentatives. Compte verrouillé 15 min." : "Identifiant ou mot de passe incorrect";
                    
                    $update_stmt = $db->prepare("UPDATE utilisateurs SET echec_connexion = :att, verrouillage_jusqu_a = :lock WHERE id = :id");
                    $update_stmt->bindParam(":att", $failed_attempts);
                    $update_stmt->bindParam(":lock", $lock_until);
                    $update_stmt->bindParam(":id", $user['id']);
                    $update_stmt->execute();
                }
            } else {
                $error = "Identifiant ou mot de passe incorrect";
            }
        } catch(PDOException $e) {
            $error = "Erreur de connexion. Réessayez.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gym Management - Connexion & Atlas Musculaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --map-bg: #f4f6f9;
            --body-fill: #e2e8f0;
            --body-stroke: #cbd5e1;
            --muscle-fill: rgba(74, 144, 226, 0.35);
            --muscle-stroke: #4A90E2;
            --muscle-hover: rgba(74, 144, 226, 0.65);
            --muscle-active: rgba(80, 200, 120, 0.7);
            --panel-bg: #ffffff;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-heavy: 0 10px 30px rgba(0,0,0,0.12);
        }
        .dark-theme {
            --map-bg: #151a27;
            --body-fill: #2d3748;
            --body-stroke: #4a5568;
            --muscle-fill: rgba(91, 163, 245, 0.3);
            --muscle-stroke: #5BA3F5;
            --muscle-hover: rgba(91, 163, 245, 0.6);
            --muscle-active: rgba(80, 200, 120, 0.6);
            --panel-bg: #1e2533;
            --shadow: 0 4px 20px rgba(0,0,0,0.3);
            --shadow-heavy: 0 10px 30px rgba(0,0,0,0.4);
        }
        .landing-wrapper {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            min-height: 100vh;
            background: var(--bg-color);
        }
        .map-section {
            background: var(--map-bg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        .map-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 20%, rgba(74, 144, 226, 0.08) 0%, transparent 50%);
            pointer-events: none;
        }
        .map-header {
            text-align: center;
            margin-bottom: 15px;
            z-index: 2;
            position: relative;
        }
        .map-header h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-color);
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .map-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
            max-width: 400px;
            margin: 0 auto;
        }
        .body-container {
            position: relative;
            width: 100%;
            max-width: 440px;
            height: 620px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        .body-svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 6px 16px rgba(0,0,0,0.06));
        }
        .body-base {
            fill: var(--body-fill);
            stroke: var(--body-stroke);
            stroke-width: 1.5;
        }
        .mz {
            fill: var(--muscle-fill);
            stroke: var(--muscle-stroke);
            stroke-width: 1.8;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .mz:hover {
            fill: var(--muscle-hover);
            stroke-width: 2.5;
            filter: drop-shadow(0 0 10px rgba(74, 144, 226, 0.4));
            transform: scale(1.01);
        }
        .mz.active {
            fill: var(--muscle-active);
            stroke: #2d7a4f;
            stroke-width: 2.5;
        }
        .muscle-separator {
            stroke: rgba(0,0,0,0.12);
            stroke-width: 1.2;
            pointer-events: none;
            opacity: 0.7;
        }
        .dark-theme .muscle-separator { stroke: rgba(255,255,255,0.1); }
        .view-toggles {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            z-index: 2;
        }
        .view-btn {
            padding: 8px 18px;
            border: 2px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-color);
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .view-btn.active, .view-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .login-section {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 30px;
            background: var(--bg-color);
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            background: var(--card-bg);
            padding: 32px;
            border-radius: 16px;
            box-shadow: var(--shadow-heavy);
            border: 1px solid var(--border-color);
        }
        .login-container h3 {
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.15);
            border-color: var(--primary-color);
        }
        .new-member-info {
            margin-top: 20px;
            padding: 14px;
            background: rgba(74, 144, 226, 0.06);
            border: 1px dashed rgba(74, 144, 226, 0.4);
            border-radius: 10px;
            text-align: center;
        }
        .new-member-info i {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 6px;
        }
        .new-member-info p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-color);
            line-height: 1.5;
        }
        .new-member-info a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
        }
        .new-member-info a:hover { text-decoration: underline; }
        .exercise-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--panel-bg);
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            box-shadow: var(--shadow-heavy);
            transform: translateY(100%);
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1050;
            max-height: 80vh;
            overflow-y: auto;
            padding: 24px;
        }
        .exercise-panel.open { transform: translateY(0); }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        .panel-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary-color);
            margin: 0;
        }
        .close-panel {
            background: var(--bg-color);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .close-panel:hover { background: #fee2e2; color: #dc2626; }
        .exercise-card {
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 10px;
            transition: 0.2s;
        }
        .exercise-card:hover { border-color: var(--primary-color); transform: translateY(-2px); }
        .ex-name { font-weight: 700; color: var(--text-color); margin-bottom: 6px; font-size: 0.95rem; }
        .ex-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
        .ex-chip {
            background: rgba(74, 144, 226, 0.1);
            color: var(--primary-color);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .ex-tip {
            font-size: 0.8rem;
            color: var(--text-muted);
            border-left: 3px solid var(--primary-color);
            padding-left: 10px;
            line-height: 1.4;
        }
        @media (max-width: 992px) {
            .landing-wrapper { grid-template-columns: 1fr; }
            .map-section { min-height: 55vh; padding: 20px; }
            .body-container { height: 480px; max-width: 360px; }
        }
    </style>
</head>
<body class="light-theme">

<div class="theme-toggle">
    <button id="themeSwitcher" class="btn theme-btn" title="Changer de thème"><i class="fas fa-moon"></i></button>
</div>

<div class="landing-wrapper">
    <!-- LEFT: Interactive Muscle Map -->
    <div class="map-section">
        <div class="map-header">
            <h2><i class="fas fa-bone me-2"></i>Atlas Musculaire</h2>
            <p>Cliquez sur un muscle pour découvrir les exercices ciblés et leurs recommandations.</p>
        </div>

        <div class="body-container">
            <!-- FRONT VIEW -->
            <svg id="svg-front" class="body-svg" viewBox="0 0 320 700">
                <defs>
                    <radialGradient id="bodyGrad" cx="50%" cy="30%" r="60%">
                        <stop offset="0%" stop-color="#e9ecef"/>
                        <stop offset="100%" stop-color="#ced4da"/>
                    </radialGradient>
                    <filter id="muscleGlow">
                        <feGaussianBlur stdDeviation="2" result="blur"/>
                        <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                    </filter>
                </defs>

                <!-- Base Body -->
                <ellipse cx="160" cy="685" rx="65" ry="9" fill="rgba(0,0,0,.06)"/>
                <ellipse cx="160" cy="48" rx="34" ry="40" class="body-base"/>
                <rect x="147" y="84" width="26" height="24" rx="5" class="body-base"/>
                <path d="M105,105 Q82,112 78,138 L70,225 Q67,242 75,252 L245,252 Q253,242 250,225 L242,138 Q238,112 215,105 Z" class="body-base"/>
                <path d="M120,205 L200,205 L203,285 L117,285 Z" class="body-base"/>
                <path d="M117,285 L203,285 Q222,295 228,315 L218,365 L102,365 L92,315 Q98,295 117,285 Z" class="body-base"/>
                <!-- Arms -->
                <path d="M78,112 Q56,118 53,152 L56,192 Q64,208 78,202 L88,162 L88,115 Z" class="body-base"/>
                <path d="M242,112 Q264,118 267,152 L264,192 Q256,208 242,202 L232,162 L232,115 Z" class="body-base"/>
                <path d="M53,195 Q41,215 38,252 L44,292 Q53,302 62,298 L70,258 L57,197 Z" class="body-base"/>
                <path d="M267,195 Q279,215 282,252 L276,292 Q267,302 258,298 L250,258 L263,197 Z" class="body-base"/>
                <ellipse cx="40" cy="308" rx="15" ry="19" class="body-base"/>
                <ellipse cx="280" cy="308" rx="15" ry="19" class="body-base"/>
                <!-- Legs -->
                <path d="M102,365 L152,363 L155,485 L94,485 L88,412 Z" class="body-base"/>
                <path d="M218,365 L168,363 L165,485 L226,485 L232,412 Z" class="body-base"/>
                <path d="M94,485 L155,485 L152,582 L98,580 L90,528 Z" class="body-base"/>
                <path d="M226,485 L165,485 L168,582 L222,580 L230,528 Z" class="body-base"/>
                <ellipse cx="126" cy="588" rx="30" ry="11" class="body-base"/>
                <ellipse cx="194" cy="588" rx="30" ry="11" class="body-base"/>

                <!-- MUSCLE ZONES FRONT -->
                <path class="mz" data-muscle="pec" d="M118,112 Q96,118 89,134 L86,164 Q97,180 118,182 L142,174 L144,116 Z"/>
                <path class="mz" data-muscle="pec" d="M202,112 Q224,118 231,134 L234,164 Q223,180 202,182 L178,174 L176,116 Z"/>
                
                <path class="mz" data-muscle="deltoid" d="M82,106 Q73,112 69,126 L71,150 Q80,158 90,152 L93,122 Z"/>
                <path class="mz" data-muscle="deltoid" d="M238,106 Q247,112 251,126 L249,150 Q240,158 230,152 L227,122 Z"/>
                
                <path class="mz" data-muscle="bicep" d="M60,155 Q51,162 51,184 L55,205 Q64,213 73,208 L81,184 L77,155 Z"/>
                <path class="mz" data-muscle="bicep" d="M260,155 Q269,162 269,184 L265,205 Q256,213 247,208 L239,184 L243,155 Z"/>
                
                <path class="mz" data-muscle="forearm" d="M45,208 Q36,224 36,258 L42,288 Q52,298 62,294 L69,264 L53,210 Z"/>
                <path class="mz" data-muscle="forearm" d="M275,208 Q284,224 284,258 L278,288 Q268,298 258,294 L251,264 L267,210 Z"/>
                
                <path class="mz" data-muscle="abs" d="M125,188 L195,188 L198,272 L122,272 Z"/>
                <path class="mz" data-muscle="oblique" d="M115,188 L125,188 L122,272 L110,282 L104,252 L108,208 Z"/>
                <path class="mz" data-muscle="oblique" d="M205,188 L195,188 L198,272 L210,282 L216,252 L212,208 Z"/>
                
                <path class="mz" data-muscle="serratus" d="M108,162 L118,180 L115,205 L103,196 L100,172 Z"/>
                <path class="mz" data-muscle="serratus" d="M212,162 L202,180 L205,205 L217,196 L220,172 Z"/>
                
                <path class="mz" data-muscle="quad" d="M105,368 L152,366 L155,468 L98,468 L94,418 Z"/>
                <path class="mz" data-muscle="quad" d="M215,368 L168,366 L165,468 L222,468 L226,418 Z"/>
                
                <path class="mz" data-muscle="adductor" d="M156,368 L156,465 L164,465 L164,368 Z"/>
                
                <path class="mz" data-muscle="tibial" d="M99,472 L142,472 L139,565 L102,563 L97,518 Z"/>
                <path class="mz" data-muscle="tibial" d="M221,472 L178,472 L181,565 L218,563 L223,518 Z"/>

                <!-- Separators -->
                <line x1="160" y1="195" x2="160" y2="270" class="muscle-separator"/>
                <line x1="125" y1="210" x2="195" y2="210" class="muscle-separator"/>
                <line x1="125" y1="228" x2="195" y2="228" class="muscle-separator"/>
                <line x1="125" y1="246" x2="195" y2="246" class="muscle-separator"/>
                <line x1="128" y1="375" x2="123" y2="462" class="muscle-separator"/>
                <line x1="148" y1="372" x2="145" y2="464" class="muscle-separator"/>
                <line x1="192" y1="375" x2="197" y2="462" class="muscle-separator"/>
                <line x1="172" y1="372" x2="175" y2="464" class="muscle-separator"/>
            </svg>

            <!-- BACK VIEW -->
            <svg id="svg-back" class="body-svg" viewBox="0 0 320 700" style="display:none">
                <defs>
                    <radialGradient id="bodyGradBack" cx="50%" cy="30%" r="60%">
                        <stop offset="0%" stop-color="#e2e6ea"/>
                        <stop offset="100%" stop-color="#c8cdd4"/>
                    </radialGradient>
                </defs>
                <ellipse cx="160" cy="685" rx="65" ry="9" fill="rgba(0,0,0,.06)"/>
                <ellipse cx="160" cy="48" rx="34" ry="40" class="body-base"/>
                <rect x="147" y="84" width="26" height="24" rx="5" class="body-base"/>
                <path d="M105,105 Q82,112 78,138 L70,225 Q67,242 75,252 L245,252 Q253,242 250,225 L242,138 Q238,112 215,105 Z" class="body-base"/>
                <path d="M120,205 L200,205 L203,285 L117,285 Z" class="body-base"/>
                <path d="M117,285 L203,285 Q222,295 228,315 L218,365 L102,365 L92,315 Q98,295 117,285 Z" class="body-base"/>
                <path d="M78,112 Q56,118 53,152 L56,192 Q64,208 78,202 L88,162 L88,115 Z" class="body-base"/>
                <path d="M242,112 Q264,118 267,152 L264,192 Q256,208 242,202 L232,162 L232,115 Z" class="body-base"/>
                <path d="M53,195 Q41,215 38,252 L44,292 Q53,302 62,298 L70,258 L57,197 Z" class="body-base"/>
                <path d="M267,195 Q279,215 282,252 L276,292 Q267,302 258,298 L250,258 L263,197 Z" class="body-base"/>
                <ellipse cx="40" cy="308" rx="15" ry="19" class="body-base"/>
                <ellipse cx="280" cy="308" rx="15" ry="19" class="body-base"/>
                <path d="M102,365 L152,363 L155,485 L94,485 L88,412 Z" class="body-base"/>
                <path d="M218,365 L168,363 L165,485 L226,485 L232,412 Z" class="body-base"/>
                <path d="M94,485 L155,485 L152,582 L98,580 L90,528 Z" class="body-base"/>
                <path d="M226,485 L165,485 L168,582 L222,580 L230,528 Z" class="body-base"/>
                <ellipse cx="126" cy="588" rx="30" ry="11" class="body-base"/>
                <ellipse cx="194" cy="588" rx="30" ry="11" class="body-base"/>

                <!-- MUSCLE ZONES BACK -->
                <path class="mz" data-muscle="trapeze" d="M125,88 L195,88 L222,112 L216,152 L160,162 L104,152 L98,112 Z"/>
                <path class="mz" data-muscle="deltoid" d="M80,108 Q69,116 65,132 L68,156 L83,156 L92,126 Z"/>
                <path class="mz" data-muscle="deltoid" d="M240,108 Q251,116 255,132 L252,156 L237,156 L228,126 Z"/>
                
                <path class="mz" data-muscle="tricep" d="M56,156 Q47,168 47,190 L51,212 Q60,219 70,214 L77,190 L73,156 Z"/>
                <path class="mz" data-muscle="tricep" d="M264,156 Q273,168 273,190 L269,212 Q260,219 250,214 L243,190 L247,156 Z"/>
                
                <path class="mz" data-muscle="lat" d="M98,115 L134,120 L131,208 L102,203 L89,168 L86,138 Z"/>
                <path class="mz" data-muscle="lat" d="M222,115 L186,120 L189,208 L218,203 L231,168 L234,138 Z"/>
                
                <path class="mz" data-muscle="rhomboid" d="M134,120 L186,120 L189,168 L131,168 Z"/>
                <path class="mz" data-muscle="erector" d="M131,170 L189,170 L192,272 L128,272 Z"/>
                
                <path class="mz" data-muscle="glute" d="M102,288 L154,286 L154,368 L96,366 L88,328 Z"/>
                <path class="mz" data-muscle="glute" d="M218,288 L166,286 L166,368 L224,366 L232,328 Z"/>
                
                <path class="mz" data-muscle="hamstring" d="M96,370 L154,368 L152,472 L90,470 L88,418 Z"/>
                <path class="mz" data-muscle="hamstring" d="M224,370 L166,368 L168,472 L230,470 L232,418 Z"/>
                
                <path class="mz" data-muscle="calf" d="M94,475 L152,475 L148,562 L98,560 L92,518 Z"/>
                <path class="mz" data-muscle="calf" d="M226,475 L168,475 L172,562 L222,560 L228,518 Z"/>

                <!-- Separators -->
                <line x1="160" y1="95" x2="160" y2="160" class="muscle-separator"/>
                <line x1="134" y1="125" x2="186" y2="125" class="muscle-separator"/>
                <line x1="134" y1="145" x2="186" y2="145" class="muscle-separator"/>
                <line x1="160" y1="175" x2="160" y2="268" class="muscle-separator"/>
                <line x1="120" y1="375" x2="118" y2="465" class="muscle-separator"/>
                <line x1="140" y1="372" x2="138" y2="468" class="muscle-separator"/>
                <line x1="200" y1="375" x2="202" y2="465" class="muscle-separator"/>
                <line x1="180" y1="372" x2="182" y2="468" class="muscle-separator"/>
                <line x1="121" y1="480" x2="121" y2="558" class="muscle-separator"/>
                <line x1="199" y1="480" x2="199" y2="558" class="muscle-separator"/>
            </svg>
        </div>

        <div class="view-toggles">
            <button class="view-btn active" onclick="setView('front')"><i class="fas fa-user me-2"></i>Avant</button>
            <button class="view-btn" onclick="setView('back')"><i class="fas fa-user-alt me-2"></i>Arrière</button>
        </div>
    </div>

    <!-- RIGHT: Login Form -->
    <div class="login-section">
        <div class="login-container">
            <div class="text-center mb-4">
                <i class="fas fa-dumbbell text-primary display-5 mb-3"></i>
                <h3 class="fw-bold">Espace Membres</h3>
                <p >Connectez-vous pour accéder à votre tableau de bord</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show py-2 mb-3">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-medium">Identifiant Membre</label>
                    <input type="text" class="form-control" name="membre_id" placeholder="Ex: MEMB-2026-0001" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-medium">Mot de passe</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" name="password" placeholder="••••••••" required>
                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted" onclick="this.previousElementSibling.type = this.previousElementSibling.type === 'password' ? 'text' : 'password'">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                </button>
            </form>

            <!-- NOTE POUR NOUVEAUX MEMBRES -->
            <div class="new-member-info">
                <i class="fas fa-info-circle d-block"></i>
                <p>
                    <strong>Première visite ?</strong><br>
                    Présentez-vous à l'accueil avec une pièce d'identité. Notre équipe vous créera votre compte et vous remettra votre identifiant unique.
                </p>
                <a href="contact.php" class="d-inline-block mt-2"><i class="fas fa-map-marker-alt me-1"></i>Nous trouver / Contact</a>
            </div>
        </div>
    </div>
</div>

<!-- Exercise Detail Panel -->
<div id="exercise-panel" class="exercise-panel">
    <div class="panel-header">
        <h4 class="panel-title" id="panel-muscle-name">Muscle</h4>
        <button class="close-panel" onclick="closePanel()">×</button>
    </div>
    <div id="panel-exercises"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/theme.js"></script>
<script>
const EXERCISES_DB = {
    pec: { name: "Pectoraux", exercises: [
        { name: "Développé Couché", sets: "4×8-12", rest: "90s", tip: "Omoplates rétractées, descente contrôlée sur 3s." },
        { name: "Développé Incliné Haltères", sets: "3×10-12", rest: "75s", tip: "Banc à 30°, cible le haut des pecs." },
        { name: "Écarté Poulie", sets: "3×12-15", rest: "60s", tip: "Coudes souples, étirement maximal en ouverture." }
    ]},
    deltoid: { name: "Deltoïdes", exercises: [
        { name: "Développé Militaire", sets: "4×8-10", rest: "90s", tip: "Dos gainé, expirez en poussant." },
        { name: "Élévations Latérales", sets: "4×12-15", rest: "60s", tip: "Bras légèrement pliés, pouce vers le bas." },
        { name: "Oiseau (Arrière)", sets: "3×12-15", rest: "60s", tip: "Buste penché, cible le faisceau postérieur." }
    ]},
    bicep: { name: "Biceps", exercises: [
        { name: "Curl Barre Droite", sets: "4×8-12", rest: "75s", tip: "Coudes collés au corps, pas de balancement." },
        { name: "Curl Marteau", sets: "3×10-12", rest: "60s", tip: "Prise neutre, épaissit le bras." },
        { name: "Curl Concentré", sets: "3×12-15", rest: "60s", tip: "Coude sur cuisse, contraction maximale." }
    ]},
    forearm: { name: "Avant-Bras", exercises: [
        { name: "Curl Poignet", sets: "3×15-20", rest: "45s", tip: "Mouvement lent, sentez l'étirement." },
        { name: "Farmer's Walk", sets: "3×30m", rest: "90s", tip: "Haltères lourds, dos droit, marche contrôlée." },
        { name: "Dead Hang", sets: "3×30-60s", rest: "90s", tip: "Suspendu à la barre, décompresse et renforce." }
    ]},
    abs: { name: "Abdominaux", exercises: [
        { name: "Crunch", sets: "4×20-25", rest: "45s", tip: "Flexion du tronc, ne tirez pas sur la nuque." },
        { name: "Gainage", sets: "3×45-90s", rest: "60s", tip: "Corps aligné, fessiers contractés." },
        { name: "Relevés Jambes", sets: "3×12-15", rest: "75s", tip: "Suspendu, contrôlez la descente." }
    ]},
    oblique: { name: "Obliques", exercises: [
        { name: "Crunch Oblique", sets: "3×20/côté", rest: "45s", tip: "Coude vers genou opposé, rotation max." },
        { name: "Gainage Latéral", sets: "3×40-60s", rest: "60s", tip: "Hanches stables, corps en ligne." },
        { name: "Russian Twist", sets: "3×20", rest: "45s", tip: "Pieds décollés, rotation complète." }
    ]},
    serratus: { name: "Grand Dentelé", exercises: [
        { name: "Push-up Protraction", sets: "3×12-15", rest: "60s", tip: "Poussez les omoplates vers l'avant en haut." },
        { name: "Serratus Crunch", sets: "3×15-20", rest: "60s", tip: "En planche, poussez le sol pour arrondir le dos." },
        { name: "Cable Punch", sets: "3×15/bras", rest: "60s", tip: "Mouvement de boxe, muscle du puncheur." }
    ]},
    quad: { name: "Quadriceps", exercises: [
        { name: "Squat", sets: "4×8-12", rest: "120s", tip: "Genoux dans l'axe, descente parallèle." },
        { name: "Leg Press", sets: "4×10-15", rest: "90s", tip: "Pieds bas, ne verrouillez pas les genoux." },
        { name: "Fentes Avant", sets: "3×12/jambe", rest: "75s", tip: "Grand pas, genou arrière à 5cm du sol." }
    ]},
    adductor: { name: "Adducteurs", exercises: [
        { name: "Adduction Machine", sets: "3×15-20", rest: "60s", tip: "Résistez à l'ouverture, mouvement lent." },
        { name: "Sumo Squat", sets: "3×12-15", rest: "75s", tip: "Pieds larges, orteils vers l'extérieur." },
        { name: "Cossack Squat", sets: "3×10/côté", rest: "75s", tip: "Étirement latéral profond, mobilité ++." }
    ]},
    tibial: { name: "Tibial Antérieur", exercises: [
        { name: "Flexion Dorsale", sets: "3×20-25", rest: "45s", tip: "Debout, levez les pointes. Prévient les blessures." },
        { name: "Marche sur Talons", sets: "3×20m", rest: "45s", tip: "Simple mais efficace pour l'endurance." }
    ]},
    trapeze: { name: "Trapèze", exercises: [
        { name: "Shrugs", sets: "4×12-15", rest: "60s", tip: "Haussement vertical, tenir 1s en haut." },
        { name: "Rowing Barre", sets: "4×8-10", rest: "90s", tip: "Buste à 45°, tirage vers le nombril." },
        { name: "Face Pull", sets: "3×15-20", rest: "60s", tip: "Poulie hauteur yeux, corde vers visage." }
    ]},
    lat: { name: "Grand Dorsal", exercises: [
        { name: "Traction", sets: "4×6-12", rest: "120s", tip: "Prise large, coudes vers hanches." },
        { name: "Tirage Vertical", sets: "4×10-12", rest: "75s", tip: "Vers clavicule, dos légèrement arqué." },
        { name: "Rowing Câble", sets: "3×10-12", rest: "75s", tip: "Poitrine au pad, coudes en arrière." }
    ]},
    tricep: { name: "Triceps", exercises: [
        { name: "Dips Banc", sets: "4×10-15", rest: "75s", tip: "Coudes vers l'arrière, descente 90°." },
        { name: "Extension Poulie", sets: "4×12-15", rest: "60s", tip: "Coudes fixes, extension complète." },
        { name: "Skull Crushers", sets: "3×10-12", rest: "75s", tip: "Derrière la tête, coudes fixes en haut." }
    ]},
    rhomboid: { name: "Rhomboïdes", exercises: [
        { name: "Face Pull", sets: "4×15-20", rest: "60s", tip: "Essentiel posture, écartez la corde." },
        { name: "Rowing Un Bras", sets: "3×12-15/côté", rest: "60s", tip: "Rétractez l'omoplate à chaque rep." },
        { name: "Band Pull-Apart", sets: "3×20-25", rest: "45s", tip: "Élastique, écartez à hauteur épaules." }
    ]},
    erector: { name: "Érecteurs du Rachis", exercises: [
        { name: "Hyperextensions", sets: "3×15-20", rest: "60s", tip: "Extension contrôlée, pas d'hyper-extension." },
        { name: "Good Morning", sets: "3×10-12", rest: "90s", tip: "Barre trapèzes, dos neutre absolu." },
        { name: "Deadlift Roumain", sets: "4×8-10", rest: "90s", tip: "Hanches en arrière, étirement ischios/érecteurs." }
    ]},
    glute: { name: "Fessiers", exercises: [
        { name: "Hip Thrust", sets: "4×12-15", rest: "75s", tip: "Extension max, tenir 1s en haut. LE meilleur." },
        { name: "Soulevé Roumain", sets: "3×10-12", rest: "90s", tip: "Hanches en arrière, dos neutre." },
        { name: "Fentes Bulgares", sets: "3×10/jambe", rest: "90s", tip: "Pied arrière sur banc, descente verticale." }
    ]},
    hamstring: { name: "Ischio-Jambiers", exercises: [
        { name: "Leg Curl", sets: "4×12-15", rest: "60s", tip: "Contraction haut, descente lente." },
        { name: "Deadlift Roumain", sets: "4×10-12", rest: "90s", tip: "Étirement profond, dos droit." },
        { name: "Nordic Curl", sets: "3×5-8", rest: "120s", tip: "Excentrique intense, prévention blessures." }
    ]},
    calf: { name: "Mollets", exercises: [
        { name: "Élévation Debout", sets: "4×15-20", rest: "45s", tip: "Amplitude max, descente en 3s." },
        { name: "Élévation Assis", sets: "4×15-25", rest: "45s", tip: "Cible le soléaire, mouvement lent." },
        { name: "Corde à Sauter", sets: "3×3 min", rest: "60s", tip: "Endurance et définition ++." }
    ]}
};

let currentView = 'front';

function setView(view) {
    currentView = view;
    document.getElementById('svg-front').style.display = view === 'front' ? 'block' : 'none';
    document.getElementById('svg-back').style.display = view === 'back' ? 'block' : 'none';
    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
    event.currentTarget.classList.add('active');
}

function openPanel(muscleKey) {
    const data = EXERCISES_DB[muscleKey];
    if (!data) return;
    
    document.querySelectorAll('.mz').forEach(m => m.classList.remove('active'));
    document.querySelectorAll(`.mz[data-muscle="${muscleKey}"]`).forEach(m => m.classList.add('active'));
    
    document.getElementById('panel-muscle-name').textContent = data.name;
    const container = document.getElementById('panel-exercises');
    container.innerHTML = '';
    
    data.exercises.forEach(ex => {
        container.innerHTML += `
            <div class="exercise-card">
                <div class="ex-name">${ex.name}</div>
                <div class="ex-meta">
                    <span class="ex-chip">${ex.sets}</span>
                    <span class="ex-chip">⏱ ${ex.rest}</span>
                </div>
                <div class="ex-tip">💡 ${ex.tip}</div>
            </div>
        `;
    });
    
    document.getElementById('exercise-panel').classList.add('open');
}

function closePanel() {
    document.getElementById('exercise-panel').classList.remove('open');
    document.querySelectorAll('.mz').forEach(m => m.classList.remove('active'));
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.mz').forEach(zone => {
        zone.addEventListener('click', () => openPanel(zone.dataset.muscle));
    });
});
</script>
</body>
</html>
