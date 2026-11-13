USE tradehub;

INSERT INTO roles (id, name) VALUES
  (1, 'Administrator'),
  (2, 'Customer');

-- Password: Admin@123 / Customer@123 (bcrypt)
INSERT INTO users (id, role_id, full_name, email, phone, address, password_hash, status) VALUES
  (1, 1, 'Maria Santos', 'admin@tradehub.test', '09171234567', '12 Mabini St., Quezon City',
      '$2y$10$bBSs5EurPyZF9NEp.X1hteO0WQLSzmfRn4NaR3uKOMW8UR157epGu', 'active'),
  (2, 2, 'Juan Dela Cruz', 'customer@tradehub.test', '09181234567', '45 Rizal Ave., Caloocan',
      '$2y$10$LirFTpSPC0bhyAFmkhl3AOWRY3n3fvxbHngPz4NZmef.dB3FLY9i6', 'active');

INSERT INTO categories (id, name, slug, description) VALUES
  (1, 'Fresh Produce', 'fresh-produce', 'Farm-fresh vegetables, fruits, and herbs from nearby growers.'),
  (2, 'Baked Goods', 'baked-goods', 'Breads, pastries, and native kakanin baked daily.'),
  (3, 'Handicrafts', 'handicrafts', 'Woven bags, home decor, and handmade souvenirs.'),
  (4, 'Beverages', 'beverages', 'Locally roasted coffee, juices, and herbal teas.'),
  (5, 'Home & Pantry', 'home-pantry', 'Preserved goods, sauces, and household staples.');

INSERT INTO products (category_id, name, description, price, stock, is_active) VALUES
  (1, 'Organic Kangkong Bundle', 'Crisp, pesticide-free water spinach harvested the same morning. Approx. 500g per bundle.', 45.00, 40, 1),
  (1, 'Sweet Mangoes (1kg)', 'Naturally ripened carabao mangoes from Guimaras farms.', 180.00, 25, 1),
  (1, 'Native Tomatoes (1kg)', 'Vine-ripened native tomatoes, great for salads and sauces.', 95.00, 30, 1),
  (2, 'Pandesal Pack (12 pcs)', 'Soft, freshly baked classic Filipino bread rolls.', 90.00, 50, 1),
  (2, 'Bibingka Special', 'Rice cake baked in banana leaf with salted egg and cheese.', 150.00, 15, 1),
  (2, 'Whole Wheat Loaf', 'Slow-fermented whole wheat bread with no preservatives.', 130.00, 20, 1),
  (3, 'Woven Abaca Tote Bag', 'Handwoven tote made by local artisan weavers in Quezon.', 450.00, 10, 1),
  (3, 'Bamboo Utensil Set', 'Reusable fork, spoon, and straw carved from bamboo.', 220.00, 18, 1),
  (3, 'Capiz Shell Coasters (set of 4)', 'Handcrafted coasters with a pearl-like finish.', 175.00, 0, 1),
  (4, 'Barako Coffee Beans (250g)', 'Bold, full-bodied locally roasted Batangas barako coffee.', 320.00, 12, 1),
  (4, 'Calamansi Juice (1L)', 'Freshly squeezed calamansi juice, lightly sweetened.', 140.00, 22, 1),
  (4, 'Lemongrass Herbal Tea', 'Dried lemongrass infusion, caffeine free.', 110.00, 8, 1),
  (5, 'Homemade Bagoong (250g)', 'Fermented shrimp paste cooked the traditional way.', 120.00, 26, 1),
  (5, 'Coconut Jam (Sinamak) 300g', 'Rich coconut spread slow-cooked in small batches.', 165.00, 14, 1),
  (5, 'Native Vinegar (500ml)', 'Sugarcane vinegar aged for six months.', 100.00, 0, 1);

INSERT INTO announcements (title, body, is_published) VALUES
  ('Free delivery within Metro Manila', 'Order a minimum of PHP 1,000 and get free same-day delivery within Metro Manila. Promo runs until the end of the month.', 1),
  ('New weekend market schedule', 'Our partner merchants now deliver every Saturday and Sunday, 8:00 AM to 5:00 PM.', 1);

INSERT INTO settings (`key`, `value`) VALUES
  ('site_name', 'TradeHub'),
  ('tagline', 'The local marketplace for community merchants'),
  ('hero_title', 'Discover the best of your local market'),
  ('hero_text', 'TradeHub connects neighborhood growers, bakers, and artisans with buyers nearby. Browse fresh goods, compare prices, and order in minutes.'),
  ('about_text', 'TradeHub is a startup marketplace built to help local micro-entrepreneurs reach nearby customers online. We handle product discovery, pricing transparency, and order tracking so sellers can focus on making great goods.'),
  ('contact_email', 'hello@tradehub.test'),
  ('contact_phone', '+63 917 000 0000'),
  ('commission_rate', '5');

INSERT INTO transactions (reference, user_id, product_id, product_name, quantity, unit_price, total_amount, payment_method, status, created_at) VALUES
  ('TH-20261001-A1B2', 2, 2, 'Sweet Mangoes (1kg)', 2, 180.00, 360.00, 'cod', 'completed', '2026-10-01 09:15:00'),
  ('TH-20261001-A1B2', 2, 11, 'Calamansi Juice (1L)', 1, 140.00, 140.00, 'cod', 'completed', '2026-10-01 09:15:00'),
  ('TH-20261018-C3D4', 2, 4, 'Pandesal Pack (12 pcs)', 2, 90.00, 180.00, 'gcash', 'paid', '2026-10-18 07:40:00'),
  ('TH-20261025-E5F6', 2, 7, 'Woven Abaca Tote Bag', 1, 450.00, 450.00, 'gcash', 'completed', '2026-10-25 14:02:00');
