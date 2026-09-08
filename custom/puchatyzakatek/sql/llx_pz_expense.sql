CREATE TABLE llx_pz_expense (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    document_no VARCHAR(128) NULL,
    supplier VARCHAR(255) NULL,
    category VARCHAR(128) NULL,
    description TEXT NULL,
    amount_gross DECIMAL(10,2) NOT NULL DEFAULT 0,
    attachment_path VARCHAR(255) NULL,
    datec DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;