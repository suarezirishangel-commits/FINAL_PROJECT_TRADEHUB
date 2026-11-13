-- TradeHub — Local Product Marketplace
-- MySQL 8 / MariaDB schema (WAMP)

CREATE DATABASE IF NOT EXISTS tradehub
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tradehub;

SET FOREIGN_KEY_CHECKS = 0;
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
  name        VARCHAR(150) NOT NULL,
  description TEXT DEFAULT NULL,
  image       VARCHAR(255) DEFAULT NULL,
  price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  stock       INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_category (category_id),
  KEY idx_products_price (price),
  FULLTEXT KEY ft_products_search (name, description),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB;

-- One row per purchased item; rows sharing a reference form one order.
CREATE TABLE transactions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference     VARCHAR(20) NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  product_name  VARCHAR(150) NOT NULL,
  quantity      INT NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  total_amount  DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(30) NOT NULL DEFAULT 'cod',
  status        ENUM('pending','paid','completed','cancelled') NOT NULL DEFAULT 'pending',
  tracking_number VARCHAR(64) DEFAULT NULL,
  paid_at       DATETIME DEFAULT NULL,
  completed_at  DATETIME DEFAULT NULL,
  cancelled_at  DATETIME DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_transactions_user (user_id),
  KEY idx_transactions_reference (reference),
  KEY idx_transactions_created (created_at),
  CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_transactions_product FOREIGN KEY (product_id) REFERENCES products (id)
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
