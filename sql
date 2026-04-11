-- Create database
CREATE DATABASE gym_management;
USE gym_management;

-- Users table (based on your class diagram)
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membre_id VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    role ENUM('admin', 'gerant', 'receptionniste', 'coach', 'membre') NOT NULL,
    statut ENUM('actif', 'inactif', 'suspendu') DEFAULT 'actif',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dernier_login TIMESTAMP NULL,
    echec_connexion INT DEFAULT 0,
    verrouillage_jusqu_a TIMESTAMP NULL
);

-- Members table (extends utilisateurs)
CREATE TABLE membres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    numero_adherent VARCHAR(50) UNIQUE NOT NULL,
    date_inscription DATE NOT NULL,
    date_naissance DATE,
    adresse TEXT,
    photo VARCHAR(255),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

-- Insert default admin (password: admin123)
INSERT INTO utilisateurs (membre_id, nom, prenom, email, mot_de_passe, role, statut) 
VALUES ('ADMIN001', 'Admin', 'Système', 'admin@gym.com', '$2y$10$p6dcY33IvRTCO6bIUAd8/eYljyt3ZVhim.SHZBxg.VIf5Iv16GezW', 'admin', 'actif');
-- Coaches table
CREATE TABLE coaches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    specialite VARCHAR(100),
    date_embauche DATE NOT NULL,
    certification VARCHAR(255),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

-- Subscription formulas
CREATE TABLE formules_abonnement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_formule VARCHAR(100) NOT NULL,
    duree_jours INT NOT NULL,
    tarif DECIMAL(10,2) NOT NULL,
    description TEXT,
    actif BOOLEAN DEFAULT TRUE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Subscriptions
CREATE TABLE abonnements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membre_id INT NOT NULL,
    formule_id INT NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut ENUM('actif', 'suspendu', 'expire', 'resilie') DEFAULT 'actif',
    date_resiliation DATE NULL,
    motif_suspension VARCHAR(255),
    FOREIGN KEY (membre_id) REFERENCES membres(id) ON DELETE CASCADE,
    FOREIGN KEY (formule_id) REFERENCES formules_abonnement(id)
);

-- Payments
CREATE TABLE paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membre_id INT NOT NULL,
    abonnement_id INT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date_paiement DATETIME NOT NULL,
    mode_paiement ENUM('especes', 'carte', 'virement') NOT NULL,
    motif VARCHAR(255) NOT NULL,
    statut ENUM('en_attente', 'valide', 'refuse') DEFAULT 'en_attente',
    reference_recu VARCHAR(50) UNIQUE,
    FOREIGN KEY (membre_id) REFERENCES membres(id) ON DELETE CASCADE,
    FOREIGN KEY (abonnement_id) REFERENCES abonnements(id)
);

-- Classes/Courses
CREATE TABLE cours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_id INT NOT NULL,
    type_cours VARCHAR(100) NOT NULL,
    date_cours DATE NOT NULL,
    heure_debut TIME NOT NULL,
    duree_minutes INT NOT NULL,
    salle VARCHAR(50) NOT NULL,
    capacite_max INT NOT NULL,
    places_disponibles INT NOT NULL,
    statut ENUM('publie', 'annule', 'termine') DEFAULT 'publie',
    FOREIGN KEY (coach_id) REFERENCES coaches(id)
);

-- Reservations
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membre_id INT NOT NULL,
    cours_id INT NOT NULL,
    date_reservation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('confirmee', 'annulee', 'liste_attente') DEFAULT 'confirmee',
    FOREIGN KEY (membre_id) REFERENCES membres(id) ON DELETE CASCADE,
    FOREIGN KEY (cours_id) REFERENCES cours(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reservation (membre_id, cours_id)
);

-- Waitlist
CREATE TABLE liste_attente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membre_id INT NOT NULL,
    cours_id INT NOT NULL,
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    position INT NOT NULL,
    FOREIGN KEY (membre_id) REFERENCES membres(id) ON DELETE CASCADE,
    FOREIGN KEY (cours_id) REFERENCES cours(id) ON DELETE CASCADE
);

-- Performance tracking
CREATE TABLE suivi_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membre_id INT NOT NULL,
    coach_id INT NOT NULL,
    date_mesure DATE NOT NULL,
    poids_kg DECIMAL(5,2),
    imc DECIMAL(5,2),
    tour_taille_cm DECIMAL(5,2),
    observations TEXT,
    FOREIGN KEY (membre_id) REFERENCES membres(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES coaches(id)
);

-- Training programs
CREATE TABLE programmes_entraînement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_id INT NOT NULL,
    membre_id INT NOT NULL,
    titre VARCHAR(150) NOT NULL,
    description TEXT,
    date_creation DATE NOT NULL,
    FOREIGN KEY (coach_id) REFERENCES coaches(id),
    FOREIGN KEY (membre_id) REFERENCES membres(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    type_notification VARCHAR(30) NOT NULL,
    canal ENUM('email', 'sms') NOT NULL,
    contenu TEXT NOT NULL,
    date_envoi DATETIME NOT NULL,
    statut ENUM('en_attente', 'envoyee', 'echec') DEFAULT 'en_attente',
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

-- Audit log
CREATE TABLE journal_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    table_affectee VARCHAR(100),
    date_action DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_utilisateur VARCHAR(45),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);
