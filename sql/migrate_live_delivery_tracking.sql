-- Live COD delivery tracking. Safe for existing orders and user records.
USE tradehub;

INSERT INTO roles (name)
SELECT 'Delivery Rider'
WHERE NOT EXISTS (
  SELECT 1 FROM roles WHERE name = 'Delivery Rider'
);

ALTER TABLE transactions
  MODIFY COLUMN status ENUM('pending','paid','out_for_delivery','completed','cancelled')
  NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS order_delivery_tracking (
  reference           VARCHAR(20) NOT NULL,
  delivery_address    VARCHAR(255) NOT NULL DEFAULT '',
  rider_id            INT UNSIGNED DEFAULT NULL,
  latitude            DECIMAL(10,7) DEFAULT NULL,
  longitude           DECIMAL(10,7) DEFAULT NULL,
  location_updated_at DATETIME DEFAULT NULL,
  delivery_started_at DATETIME DEFAULT NULL,
  PRIMARY KEY (reference),
  KEY idx_delivery_tracking_rider (rider_id),
  CONSTRAINT fk_delivery_tracking_rider FOREIGN KEY (rider_id) REFERENCES users (id)
) ENGINE=InnoDB;
