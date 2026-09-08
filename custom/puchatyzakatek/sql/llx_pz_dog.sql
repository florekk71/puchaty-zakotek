CREATE TABLE llx_pz_dog (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_soc INTEGER NULL,
    name VARCHAR(128) NOT NULL,
    breed VARCHAR(128) NULL,
    sex VARCHAR(16) NULL,
    birthdate DATE NULL,
    weight DECIMAL(10,2) NULL,
    size_class VARCHAR(32) NULL,
    allergies TEXT NULL,
    health_notes TEXT NULL,
    grooming_notes TEXT NULL,
    behavior_notes TEXT NULL,
    active TINYINT DEFAULT 1,
    datec DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;