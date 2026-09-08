CREATE TABLE llx_pz_visit (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_soc INTEGER NULL,
    fk_dog INTEGER NOT NULL,
    visit_date DATETIME NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'planned',
    payment_type VARCHAR(32) NULL,
    amount_total DECIMAL(10,2) DEFAULT 0,
    notes TEXT NULL,
    datec DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pz_visit_dog (fk_dog),
    INDEX idx_pz_visit_soc (fk_soc)
) ENGINE=innodb;