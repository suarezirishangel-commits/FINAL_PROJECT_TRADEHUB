-- TradeHub — Local Product Marketplace
-- MySQL 8 / MariaDB schema (WAMP)

CREATE DATABASE IF NOT EXISTS tradehub
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tradehub;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS trade_offers;
DROP TABLE IF EXISTS trade_listings;
DROP TABLE IF EXISTS seller_reviews;
DROP TABLE IF EXISTS product_reviews;
DROP TABLE IF EXISTS order_delivery_tracking;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(50)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB;

CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id       INT UNSIGNED NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  phone         VARCHAR(30)  DEFAULT NULL,
  address       VARCHAR(255) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB;

CREATE TABLE categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(120) NOT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE products (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  seller_id   INT UNSIGNED DEFAULT NULL,
  name        VARCHAR(150) NOT NULL,
  description TEXT DEFAULT NULL,
  image       VARCHAR(255) DEFAULT NULL,
  price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  stock       INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_category (category_id),
  KEY idx_products_seller (seller_id),
  KEY idx_products_price (price),
  FULLTEXT KEY ft_products_search (name, description),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id),
  CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES users (id)
) ENGINE=InnoDB;

-- One row per purchased item; rows sharing a reference form one order.
CREATE TABLE transactions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference     VARCHAR(20) NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  seller_id     INT UNSIGNED DEFAULT NULL,
  product_name  VARCHAR(150) NOT NULL,
  quantity      INT NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  total_amount  DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(30) NOT NULL DEFAULT 'cod',
  status        ENUM('pending','paid','out_for_delivery','completed','cancelled') NOT NULL DEFAULT 'pending',
  tracking_number VARCHAR(64) DEFAULT NULL,
  paid_at       DATETIME DEFAULT NULL,
  completed_at  DATETIME DEFAULT NULL,
  cancelled_at  DATETIME DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_transactions_user (user_id),
  KEY idx_transactions_seller (seller_id),
  KEY idx_transactions_reference (reference),
  KEY idx_transactions_created (created_at),
  CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_transactions_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT fk_transactions_seller FOREIGN KEY (seller_id) REFERENCES users (id)
) ENGINE=InnoDB;

CREATE TABLE paypal_payments (
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

CREATE TABLE order_delivery_tracking (
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

-- CMS content: announcements shown on the storefront.
CREATE TABLE announcements (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title        VARCHAR(150) NOT NULL,
  body         TEXT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- CMS content: editable site copy and business settings.
CREATE TABLE settings (
  `key`   VARCHAR(60) NOT NULL,
  `value` TEXT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB;
