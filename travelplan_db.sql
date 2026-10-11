-- ============================================================
-- DATABASE SCHEMA : TRAVELPLAN
-- Version without separate TARIF table
-- ============================================================
CREATE DATABASE travelplan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE travelplan;

-- ========== UTILISATEUR ==========
CREATE TABLE UTILISATEUR (
    id_utilisateur     BIGINT PRIMARY KEY,
    nom                VARCHAR(120) NOT NULL,
    prenom             VARCHAR(120) NOT NULL,
    email              VARCHAR(255) NOT NULL UNIQUE,
    mot_de_passe       VARCHAR(255) NOT NULL,
    role               VARCHAR(20) NOT NULL CHECK (role IN ('client','agence')),
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ========== AGENCE ==========
CREATE TABLE AGENCE (
    id_agence          BIGINT PRIMARY KEY,
    nom                VARCHAR(150) NOT NULL,
    adresse            VARCHAR(255),
    telephone          VARCHAR(50),
    email              VARCHAR(255),
    description        TEXT,
    score_classement   DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ========== SITE VITRINE ==========
CREATE TABLE SITE_VITRINE (
    id_site            BIGINT PRIMARY KEY,
    id_agence          BIGINT NOT NULL UNIQUE,
    url                VARCHAR(255) NOT NULL,
    description        TEXT,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agence) REFERENCES AGENCE(id_agence)
);

-- ========== LOGEMENT ==========
CREATE TABLE LOGEMENT (
    id_logement        BIGINT PRIMARY KEY,
    id_agence          BIGINT NOT NULL,
    titre              VARCHAR(180) NOT NULL,
    description        TEXT,
    type               VARCHAR(20) NOT NULL CHECK (type IN ('hotel','maison','studio')),
    capacite           INT NOT NULL CHECK (capacite >= 1),
    adresse            VARCHAR(255),
    photo_url          VARCHAR(255),
    statut             VARCHAR(20) NOT NULL CHECK (statut IN ('actif','inactif')),

    -- Tarif intégré
    tarif_amount       DECIMAL(12,2) NOT NULL,
    tarif_unit         VARCHAR(10) NOT NULL CHECK (tarif_unit IN ('heure','jour','mois')),
    devise             CHAR(3) NOT NULL DEFAULT 'MAD',

    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agence) REFERENCES AGENCE(id_agence)
);

-- ========== RESERVATION ==========
CREATE TABLE RESERVATION (
    id_reservation     BIGINT PRIMARY KEY,
    id_utilisateur     BIGINT NOT NULL,
    id_logement        BIGINT NOT NULL,
    date_debut         TIMESTAMP NOT NULL,
    date_fin           TIMESTAMP NOT NULL,
    nb_personnes       INT NOT NULL CHECK (nb_personnes >= 1),
    statut             VARCHAR(20) NOT NULL CHECK (statut IN ('en_attente','confirme','annule')),
    montant_total      DECIMAL(12,2) NOT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilisateur) REFERENCES UTILISATEUR(id_utilisateur),
    FOREIGN KEY (id_logement) REFERENCES LOGEMENT(id_logement),
    CHECK (date_fin > date_debut)
);

-- ========== PAIEMENT ==========
CREATE TABLE PAIEMENT (
    id_paiement        BIGINT PRIMARY KEY,
    id_reservation     BIGINT NOT NULL UNIQUE,
    mode               VARCHAR(20) NOT NULL CHECK (mode IN ('especes','terminal','en_ligne')),
    montant            DECIMAL(12,2) NOT NULL,
    date_paiement      TIMESTAMP NOT NULL,
    statut             VARCHAR(20) NOT NULL CHECK (statut IN ('en_attente','regle','echoue')),
    reference          VARCHAR(120),
    FOREIGN KEY (id_reservation) REFERENCES RESERVATION(id_reservation)
);

-- ========== AVIS ==========
CREATE TABLE AVIS (
    id_avis            BIGINT PRIMARY KEY,
    id_utilisateur     BIGINT NOT NULL,
    id_agence          BIGINT NOT NULL,
    note               SMALLINT NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire        TEXT,
    date_avis          DATE NOT NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES UTILISATEUR(id_utilisateur),
    FOREIGN KEY (id_agence) REFERENCES AGENCE(id_agence),
    UNIQUE (id_utilisateur, id_agence, date_avis)
);
