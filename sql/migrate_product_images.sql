-- Add a product photo column to an existing TradeHub database.
-- Run once in phpMyAdmin / MySQL before uploading photos in the admin panel.
USE tradehub;

ALTER TABLE products
  ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER description;
