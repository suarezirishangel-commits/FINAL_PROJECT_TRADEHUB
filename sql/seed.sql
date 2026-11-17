USE tradehub;

INSERT INTO roles (id, name) VALUES
  (1, 'Administrator'),
  (2, 'Customer'),
  (3, 'Delivery Rider');

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
  (5, 'Home & Pantry', 'home-pantry', 'Preserved goods, sauces, and household staples.'),
  (6, 'Rice & Grains', 'rice-grains', 'Rice, corn grits, and locally sourced grain staples.'),
  (7, 'Seafood', 'seafood', 'Fresh and preserved fish from local markets.'),
  (8, 'Snacks & Sweets', 'snacks-sweets', 'Packaged local snacks and traditional sweets.'),
  (9, 'Home & Kitchen', 'home-kitchen', 'Reusable household goods and kitchen accessories.'),
  (10, 'Clothing & Accessories', 'clothing-accessories', 'Clothing, footwear, and wearable accessories.'),
  (11, 'Personal Care', 'personal-care', 'Everyday personal hygiene and grooming essentials.'),
  (12, 'Office & School', 'office-school', 'Stationery, school supplies, and everyday bags.');

INSERT INTO products (category_id, name, description, image, price, stock, is_active) VALUES
  (1, 'Organic Kangkong Bundle', 'Crisp, pesticide-free water spinach harvested the same morning. Approx. 500g per bundle.', 'assets/uploads/products/20261116-195705-cc0f19c6.png', 45.00, 40, 1),
  (1, 'Sweet Mangoes (1kg)', 'Naturally ripened carabao mangoes from Guimaras farms.', 'assets/uploads/products/20261116-195803-a36db4bc.png', 180.00, 25, 1),
  (1, 'Native Tomatoes (1kg)', 'Vine-ripened native tomatoes, great for salads and sauces.', 'assets/uploads/products/20261116-195634-2e9506f3.png', 95.00, 30, 1),
  (2, 'Pandesal Pack (12 pcs)', 'Soft, freshly baked classic Filipino bread rolls.', 'assets/uploads/products/20261116-195723-64a182e4.png', 90.00, 50, 1),
  (2, 'Bibingka Special', 'Rice cake baked in banana leaf with salted egg and cheese.', 'assets/uploads/products/20261116-195242-d145cb5f.png', 150.00, 15, 1),
  (2, 'Whole Wheat Loaf', 'Slow-fermented whole wheat bread with no preservatives.', 'assets/uploads/products/20261116-195815-9b4a378b.png', 130.00, 20, 1),
  (3, 'Woven Abaca Tote Bag', 'Handwoven tote made by local artisan weavers in Quezon.', 'assets/uploads/products/20261116-195829-76a026f8.png', 450.00, 10, 1),
  (3, 'Bamboo Utensil Set', 'Reusable fork, spoon, and straw carved from bamboo.', 'assets/uploads/products/20261116-194106-9a57224b.png', 220.00, 18, 1),
  (3, 'Capiz Shell Coasters (set of 4)', 'Handcrafted coasters with a pearl-like finish.', 'assets/uploads/products/20261116-195334-0c76f3fa.png', 175.00, 0, 1),
  (4, 'Barako Coffee Beans (250g)', 'Bold, full-bodied locally roasted Batangas barako coffee.', 'assets/uploads/products/20261116-195215-bab33111.png', 320.00, 12, 1),
  (4, 'Calamansi Juice (1L)', 'Freshly squeezed calamansi juice, lightly sweetened.', 'assets/uploads/products/20261116-195308-a0dc7de8.png', 140.00, 22, 1),
  (4, 'Lemongrass Herbal Tea', 'Dried lemongrass infusion, caffeine free.', 'assets/uploads/products/20261116-195614-d30d7946.png', 110.00, 8, 1),
  (5, 'Homemade Bagoong (250g)', 'Fermented shrimp paste cooked the traditional way.', 'assets/uploads/products/20261116-195543-c5df4108.png', 120.00, 26, 1),
  (5, 'Coconut Jam (Sinamak) 300g', 'Rich coconut spread slow-cooked in small batches.', 'assets/uploads/products/20261116-195506-b2873ff2.png', 165.00, 14, 1),
  (5, 'Native Vinegar (500ml)', 'Sugarcane vinegar aged for six months.', 'assets/uploads/products/20261116-195649-c54305ef.png', 100.00, 0, 1);

INSERT INTO products (category_id, name, description, image, price, stock, is_active) VALUES
  (5, 'Dried Mangoes (200g)', 'Sweet dried mango slices packed for snacking.', 'assets/uploads/products/20261116-195525-4e286e69.png', 180.00, 20, 1),
  (5, 'Pili Nuts (250g)', 'Roasted pili nuts packed in a 250g pouch.', 'assets/uploads/products/20261116-195739-f34934ad.png', 250.00, 15, 1),
  (1, 'Purple Yam (Ube) 500g', 'Locally grown purple yam (ube), approximately 500g.', 'assets/uploads/products/20261116-200234-8d67c0dd.png', 120.00, 20, 1),
  (5, 'Peanut Butter (250g)', 'Creamy peanut butter in a 250g jar.', 'assets/uploads/products/20261116-200335-cf47e516.png', 150.00, 20, 1),
  (1, 'Bananas (1kg)', 'Fresh ripe bananas, approximately 1kg.', 'assets/uploads/products/20261116-200418-e135dc3d.png', 80.00, 25, 1),
  (3, 'Bamboo Mat', 'Woven bamboo mat for home use.', 'assets/uploads/products/20261116-200543-b91f5d92.png', 300.00, 10, 1),
  (5, 'Banana Ketchup (320g)', 'Sweet Filipino banana ketchup in a 320g bottle.', 'assets/uploads/products/20261116-200626-e3782df8.png', 80.00, 20, 1);

INSERT INTO products (category_id, name, description, image, price, stock, is_active) VALUES
  (6, 'ST25 Rice (1kg)', 'Uncooked ST25 rice, packed in a 1kg bag.', 'assets/uploads/products/rice_st25.jpg', 180.00, 20, 1),
  (6, 'Basmati Rice (1kg)', 'Uncooked basmati rice, packed in a 1kg bag.', 'assets/uploads/products/basmati_rice.jpg', 220.00, 20, 1),
  (6, 'Corn Grits (1kg)', 'Coarse corn grits for porridge and other dishes.', 'assets/uploads/products/corn_grits.jpg', 90.00, 20, 1),
  (7, 'Fresh Bangus (1kg)', 'Milkfish (bangus), sold by approximate 1kg weight.', 'assets/uploads/products/bangus.jpg', 280.00, 15, 1),
  (7, 'Fresh Tilapia (1kg)', 'Tilapia, sold by approximate 1kg weight.', 'assets/uploads/products/tilapia.jpg', 220.00, 15, 1),
  (7, 'Dried Fish (250g)', 'Salted dried fish, packed in a 250g portion.', 'assets/uploads/products/dried_fish.jpg', 180.00, 15, 1),
  (8, 'Banana Chips (200g)', 'Crispy banana chips packed in a 200g pouch.', 'assets/uploads/products/banana_chips.png', 120.00, 20, 1),
  (8, 'Cassava Chips (200g)', 'Tapioca chips packed in a 200g pouch.', 'assets/uploads/products/cassava_chips.jpg', 100.00, 20, 1),
  (8, 'Coconut Candy (200g)', 'Coconut candy packed in a 200g pouch.', 'assets/uploads/products/coconut_candy.jpg', 150.00, 20, 1);

INSERT INTO products (category_id, name, description, image, price, stock, is_active) VALUES
  (9, 'Banana Fiber Woven Basket', 'Handwoven banana-fiber basket for home storage.', 'assets/uploads/products/woven_basket.jpg', 350.00, 10, 1),
  (9, 'Handmade Pottery Mug', 'Handmade ceramic mug for hot or cold drinks.', 'assets/uploads/products/pottery_mug.jpg', 280.00, 12, 1),
  (9, 'Wooden Cutting Board', 'Reusable wooden board for kitchen preparation.', 'assets/uploads/products/cutting_board.jpg', 320.00, 10, 1),
  (10, 'Printed Cotton Scarf', 'Lightweight printed scarf for everyday wear.', 'assets/uploads/products/printed_scarf.jpg', 250.00, 12, 1),
  (10, 'Tie-Dye T-Shirt', 'Colorful tie-dye cotton T-shirt.', 'assets/uploads/products/tie_dye_tshirt.jpg', 450.00, 10, 1),
  (10, 'Leather Sandals', 'Pair of leather sandals for casual wear.', 'assets/uploads/products/leather_sandals.jpg', 700.00, 8, 1),
  (11, 'Handmade Soap Bar', 'Handmade soap bar for everyday washing.', 'assets/uploads/products/handmade_soap.jpg', 120.00, 20, 1),
  (11, 'Bamboo Toothbrush', 'Reusable bamboo-handle toothbrush.', 'assets/uploads/products/bamboo_toothbrush.jpg', 90.00, 25, 1),
  (11, 'Shampoo Bottle', 'Shampoo in a reusable-size bottle.', 'assets/uploads/products/shampoo_bottle.jpg', 180.00, 15, 1),
  (12, 'Notebook Set', 'Set of notebooks for notes and schoolwork.', 'assets/uploads/products/notebooks.jpg', 150.00, 20, 1),
  (12, 'Colored Pencil Set', 'Colored pencils for drawing and school projects.', 'assets/uploads/products/pencil_set.jpg', 130.00, 18, 1),
  (12, 'School Backpack', 'Everyday backpack for books and school supplies.', 'assets/uploads/products/school_bag.jpg', 650.00, 10, 1);

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
