-- Seller ownership, purchase reviews, and zero-cash barter.
-- Run once against an existing TradeHub database.
USE tradehub;

INSERT INTO roles (name)
SELECT 'Seller'
WHERE NOT EXISTS (
  SELECT 1 FROM roles WHERE name = 'Seller'
);

ALTER TABLE products
  ADD COLUMN seller_id INT UNSIGNED DEFAULT NULL AFTER category_id,
  ADD KEY idx_products_seller (seller_id),
  ADD CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES users (id);

ALTER TABLE transactions
  ADD COLUMN seller_id INT UNSIGNED DEFAULT NULL AFTER product_id,
  ADD KEY idx_transactions_seller (seller_id),
  ADD CONSTRAINT fk_transactions_seller FOREIGN KEY (seller_id) REFERENCES users (id);

CREATE TABLE product_reviews (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id  INT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED NOT NULL,
  reference   VARCHAR(20) NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  comment     TEXT DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_review_order (product_id, reviewer_id, reference),
  KEY idx_product_reviews_rating (product_id, rating),
  CONSTRAINT fk_product_reviews_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT fk_product_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id)
) ENGINE=InnoDB;

CREATE TABLE seller_reviews (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_id   INT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED NOT NULL,
  reference   VARCHAR(20) NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  comment     TEXT DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seller_review_order (seller_id, reviewer_id, reference),
  KEY idx_seller_reviews_rating (seller_id, rating),
  CONSTRAINT fk_seller_reviews_seller FOREIGN KEY (seller_id) REFERENCES users (id),
  CONSTRAINT fk_seller_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id)
) ENGINE=InnoDB;

CREATE TABLE trade_listings (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  title       VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  wanted_item VARCHAR(255) NOT NULL,
  status      ENUM('open','in_trade','completed','cancelled') NOT NULL DEFAULT 'open',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_trade_listings_status_created (status, created_at),
  KEY idx_trade_listings_user (user_id),
  CONSTRAINT fk_trade_listings_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB;

CREATE TABLE trade_offers (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id             INT UNSIGNED NOT NULL,
  requester_id           INT UNSIGNED NOT NULL,
  offered_item           VARCHAR(120) NOT NULL,
  message                VARCHAR(500) DEFAULT NULL,
  status                 ENUM('pending','accepted','declined','cancelled','completed') NOT NULL DEFAULT 'pending',
  owner_confirmed_at     DATETIME DEFAULT NULL,
  requester_confirmed_at DATETIME DEFAULT NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_trade_offers_listing_status (listing_id, status),
  KEY idx_trade_offers_requester (requester_id),
  CONSTRAINT fk_trade_offers_listing FOREIGN KEY (listing_id) REFERENCES trade_listings (id),
  CONSTRAINT fk_trade_offers_requester FOREIGN KEY (requester_id) REFERENCES users (id)
) ENGINE=InnoDB;
