-- =============================================
-- CREATION DE LA BASE DE DONNEES - MySQL
-- Projet BD n°9 — Insertion professionnelle
-- =============================================

DROP DATABASE IF EXISTS projet_ip;
CREATE DATABASE projet_ip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE projet_ip;

-- Table REGION
CREATE TABLE REGION (
   id_region INT AUTO_INCREMENT,
   nom VARCHAR(50) NOT NULL,
   code_insee VARCHAR(10) NOT NULL,
   PRIMARY KEY(id_region)
) ENGINE=InnoDB;

-- Table ETABLISSEMENT
CREATE TABLE ETABLISSEMENT (
   id_etab INT AUTO_INCREMENT,
   nom VARCHAR(100) NOT NULL,
   type VARCHAR(50),
   ville VARCHAR(50),
   id_region INT NOT NULL,
   PRIMARY KEY(id_etab),
   FOREIGN KEY(id_region) REFERENCES REGION(id_region)
) ENGINE=InnoDB;

-- Table DISCIPLINE
CREATE TABLE DISCIPLINE (
   id_disc INT AUTO_INCREMENT,
   nom VARCHAR(50) NOT NULL,
   domaine VARCHAR(50),
   PRIMARY KEY(id_disc)
) ENGINE=InnoDB;

-- Table DIPLOME
CREATE TABLE DIPLOME (
   id_diplome INT AUTO_INCREMENT,
   niveau VARCHAR(50),
   intitule VARCHAR(150) NOT NULL,
   id_disc INT NOT NULL,
   id_etab INT NOT NULL,
   PRIMARY KEY(id_diplome),
   FOREIGN KEY(id_disc) REFERENCES DISCIPLINE(id_disc),
   FOREIGN KEY(id_etab) REFERENCES ETABLISSEMENT(id_etab)
) ENGINE=InnoDB;

-- Table ANNEE_ENQUETE
CREATE TABLE ANNEE_ENQUETE (
   id_annee INT AUTO_INCREMENT,
   annee INT NOT NULL,
   delai_mois INT NOT NULL,
   PRIMARY KEY(id_annee),
   UNIQUE(annee, delai_mois)
) ENGINE=InnoDB;

-- Table RESULTAT_IP (table centrale)
CREATE TABLE RESULTAT_IP (
   id_res INT AUTO_INCREMENT,
   taux_emploi DECIMAL(5,1),
   taux_cdi DECIMAL(5,1),
   taux_cadre DECIMAL(5,1),
   salaire_median INT,
   delai_emploi TINYINT,
   nb_repondants INT,
   id_annee INT NOT NULL,
   id_diplome INT NOT NULL,
   PRIMARY KEY(id_res),
   CONSTRAINT chk_taux CHECK (taux_emploi BETWEEN 0 AND 100),
   CONSTRAINT chk_sal CHECK (salaire_median IS NULL OR salaire_median > 0),
   CONSTRAINT uq_res UNIQUE (id_diplome, id_annee),
   FOREIGN KEY(id_annee) REFERENCES ANNEE_ENQUETE(id_annee),
   FOREIGN KEY(id_diplome) REFERENCES DIPLOME(id_diplome)
) ENGINE=InnoDB;

CREATE INDEX idx_res_diplome ON RESULTAT_IP(id_diplome);
CREATE INDEX idx_res_annee ON RESULTAT_IP(id_annee);
