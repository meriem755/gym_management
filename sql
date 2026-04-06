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
VALUES ('ADMIN001', 'Admin', 'Système', 'admin@gym.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'actif');
