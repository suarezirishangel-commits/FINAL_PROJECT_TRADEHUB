USE tradehub;

CREATE TABLE IF NOT EXISTS paypal_payments (
  paypal_order_id VARCHAR(64) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  reference VARCHAR(20) DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'PHP',
  status VARCHAR(24) NOT NULL DEFAULT 'created',
  capture_id VARCHAR(64) DEFAULT NULL,
  checkout_data JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (paypal_order_id),
  KEY idx_paypal_payments_user (user_id),
  KEY idx_paypal_payments_status (status),
  CONSTRAINT fk_paypal_payments_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB;
