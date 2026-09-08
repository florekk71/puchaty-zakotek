CREATE TABLE llx_pz_visit_line (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_visit INTEGER NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    size_class VARCHAR(32) NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    INDEX idx_pz_visit_line_visit (fk_visit)
) ENGINE=innodb;