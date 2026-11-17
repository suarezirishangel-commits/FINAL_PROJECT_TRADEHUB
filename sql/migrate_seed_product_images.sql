USE tradehub;

INSERT INTO categories (name, slug, description)
SELECT 'Rice & Grains', 'rice-grains', 'Rice, corn grits, and locally sourced grain staples.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'rice-grains');

INSERT INTO categories (name, slug, description)
SELECT 'Seafood', 'seafood', 'Fresh and preserved fish from local markets.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'seafood');

INSERT INTO categories (name, slug, description)
SELECT 'Snacks & Sweets', 'snacks-sweets', 'Packaged local snacks and traditional sweets.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'snacks-sweets');

INSERT INTO categories (name, slug, description)
SELECT 'Home & Kitchen', 'home-kitchen', 'Reusable household goods and kitchen accessories.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'home-kitchen');

INSERT INTO categories (name, slug, description)
SELECT 'Clothing & Accessories', 'clothing-accessories', 'Clothing, footwear, and wearable accessories.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'clothing-accessories');

INSERT INTO categories (name, slug, description)
SELECT 'Personal Care', 'personal-care', 'Everyday personal hygiene and grooming essentials.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'personal-care');

INSERT INTO categories (name, slug, description)
SELECT 'Office & School', 'office-school', 'Stationery, school supplies, and everyday bags.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'office-school');

UPDATE products
SET image = CASE name
  WHEN 'Organic Kangkong Bundle' THEN 'assets/uploads/products/20261116-195705-cc0f19c6.png'
  WHEN 'Sweet Mangoes (1kg)' THEN 'assets/uploads/products/20261116-195803-a36db4bc.png'
  WHEN 'Native Tomatoes (1kg)' THEN 'assets/uploads/products/20261116-195634-2e9506f3.png'
  WHEN 'Pandesal Pack (12 pcs)' THEN 'assets/uploads/products/20261116-195723-64a182e4.png'
  WHEN 'Bibingka Special' THEN 'assets/uploads/products/20261116-195242-d145cb5f.png'
  WHEN 'Whole Wheat Loaf' THEN 'assets/uploads/products/20261116-195815-9b4a378b.png'
  WHEN 'Woven Abaca Tote Bag' THEN 'assets/uploads/products/20261116-195829-76a026f8.png'
  WHEN 'Bamboo Utensil Set' THEN 'assets/uploads/products/20261116-194106-9a57224b.png'
  WHEN 'Capiz Shell Coasters (set of 4)' THEN 'assets/uploads/products/20261116-195334-0c76f3fa.png'
  WHEN 'Barako Coffee Beans (250g)' THEN 'assets/uploads/products/20261116-195215-bab33111.png'
  WHEN 'Calamansi Juice (1L)' THEN 'assets/uploads/products/20261116-195308-a0dc7de8.png'
  WHEN 'Lemongrass Herbal Tea' THEN 'assets/uploads/products/20261116-195614-d30d7946.png'
  WHEN 'Homemade Bagoong (250g)' THEN 'assets/uploads/products/20261116-195543-c5df4108.png'
  WHEN 'Coconut Jam (Sinamak) 300g' THEN 'assets/uploads/products/20261116-195506-b2873ff2.png'
  WHEN 'Native Vinegar (500ml)' THEN 'assets/uploads/products/20261116-195649-c54305ef.png'
  ELSE image
END
WHERE (image IS NULL OR image = '')
  AND name IN (
    'Organic Kangkong Bundle',
    'Sweet Mangoes (1kg)',
    'Native Tomatoes (1kg)',
    'Pandesal Pack (12 pcs)',
    'Bibingka Special',
    'Whole Wheat Loaf',
    'Woven Abaca Tote Bag',
    'Bamboo Utensil Set',
    'Capiz Shell Coasters (set of 4)',
    'Barako Coffee Beans (250g)',
    'Calamansi Juice (1L)',
    'Lemongrass Herbal Tea',
    'Homemade Bagoong (250g)',
    'Coconut Jam (Sinamak) 300g',
    'Native Vinegar (500ml)'
  );

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Dried Mangoes (200g)', 'Sweet dried mango slices packed for snacking.',
  'assets/uploads/products/20261116-195525-4e286e69.png', 180.00, 20, 1
FROM categories AS c
WHERE c.name = 'Home & Pantry'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Dried Mangoes (200g)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Pili Nuts (250g)', 'Roasted pili nuts packed in a 250g pouch.',
  'assets/uploads/products/20261116-195739-f34934ad.png', 250.00, 15, 1
FROM categories AS c
WHERE c.name = 'Home & Pantry'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Pili Nuts (250g)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Purple Yam (Ube) 500g', 'Locally grown purple yam (ube), approximately 500g.',
  'assets/uploads/products/20261116-200234-8d67c0dd.png', 120.00, 20, 1
FROM categories AS c
WHERE c.name = 'Fresh Produce'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Purple Yam (Ube) 500g');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Peanut Butter (250g)', 'Creamy peanut butter in a 250g jar.',
  'assets/uploads/products/20261116-200335-cf47e516.png', 150.00, 20, 1
FROM categories AS c
WHERE c.name = 'Home & Pantry'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Peanut Butter (250g)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Bananas (1kg)', 'Fresh ripe bananas, approximately 1kg.',
  'assets/uploads/products/20261116-200418-e135dc3d.png', 80.00, 25, 1
FROM categories AS c
WHERE c.name = 'Fresh Produce'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Bananas (1kg)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Bamboo Mat', 'Woven bamboo mat for home use.',
  'assets/uploads/products/20261116-200543-b91f5d92.png', 300.00, 10, 1
FROM categories AS c
WHERE c.name = 'Handicrafts'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Bamboo Mat');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Banana Ketchup (320g)', 'Sweet Filipino banana ketchup in a 320g bottle.',
  'assets/uploads/products/20261116-200626-e3782df8.png', 80.00, 20, 1
FROM categories AS c
WHERE c.name = 'Home & Pantry'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Banana Ketchup (320g)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'ST25 Rice (1kg)', 'Uncooked ST25 rice, packed in a 1kg bag.',
  'assets/uploads/products/rice_st25.jpg', 180.00, 20, 1
FROM categories AS c
WHERE c.slug = 'rice-grains'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'ST25 Rice (1kg)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Basmati Rice (1kg)', 'Uncooked basmati rice, packed in a 1kg bag.',
  'assets/uploads/products/basmati_rice.jpg', 220.00, 20, 1
FROM categories AS c
WHERE c.slug = 'rice-grains'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Basmati Rice (1kg)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Corn Grits (1kg)', 'Coarse corn grits for porridge and other dishes.',
  'assets/uploads/products/corn_grits.jpg', 90.00, 20, 1
FROM categories AS c
WHERE c.slug = 'rice-grains'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Corn Grits (1kg)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Fresh Bangus (1kg)', 'Milkfish (bangus), sold by approximate 1kg weight.',
  'assets/uploads/products/bangus.jpg', 280.00, 15, 1
FROM categories AS c
WHERE c.slug = 'seafood'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Fresh Bangus (1kg)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Fresh Tilapia (1kg)', 'Tilapia, sold by approximate 1kg weight.',
  'assets/uploads/products/tilapia.jpg', 220.00, 15, 1
FROM categories AS c
WHERE c.slug = 'seafood'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Fresh Tilapia (1kg)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Dried Fish (250g)', 'Salted dried fish, packed in a 250g portion.',
  'assets/uploads/products/dried_fish.jpg', 180.00, 15, 1
FROM categories AS c
WHERE c.slug = 'seafood'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Dried Fish (250g)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Banana Chips (200g)', 'Crispy banana chips packed in a 200g pouch.',
  'assets/uploads/products/banana_chips.png', 120.00, 20, 1
FROM categories AS c
WHERE c.slug = 'snacks-sweets'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Banana Chips (200g)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Cassava Chips (200g)', 'Tapioca chips packed in a 200g pouch.',
  'assets/uploads/products/cassava_chips.jpg', 100.00, 20, 1
FROM categories AS c
WHERE c.slug = 'snacks-sweets'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cassava Chips (200g)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Coconut Candy (200g)', 'Coconut candy packed in a 200g pouch.',
  'assets/uploads/products/coconut_candy.jpg', 150.00, 20, 1
FROM categories AS c
WHERE c.slug = 'snacks-sweets'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Coconut Candy (200g)');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Banana Fiber Woven Basket', 'Handwoven banana-fiber basket for home storage.',
  'assets/uploads/products/woven_basket.jpg', 350.00, 10, 1
FROM categories AS c
WHERE c.slug = 'home-kitchen'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Banana Fiber Woven Basket');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Handmade Pottery Mug', 'Handmade ceramic mug for hot or cold drinks.',
  'assets/uploads/products/pottery_mug.jpg', 280.00, 12, 1
FROM categories AS c
WHERE c.slug = 'home-kitchen'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Handmade Pottery Mug');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Wooden Cutting Board', 'Reusable wooden board for kitchen preparation.',
  'assets/uploads/products/cutting_board.jpg', 320.00, 10, 1
FROM categories AS c
WHERE c.slug = 'home-kitchen'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Wooden Cutting Board');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Printed Cotton Scarf', 'Lightweight printed scarf for everyday wear.',
  'assets/uploads/products/printed_scarf.jpg', 250.00, 12, 1
FROM categories AS c
WHERE c.slug = 'clothing-accessories'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Printed Cotton Scarf');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Tie-Dye T-Shirt', 'Colorful tie-dye cotton T-shirt.',
  'assets/uploads/products/tie_dye_tshirt.jpg', 450.00, 10, 1
FROM categories AS c
WHERE c.slug = 'clothing-accessories'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Tie-Dye T-Shirt');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Leather Sandals', 'Pair of leather sandals for casual wear.',
  'assets/uploads/products/leather_sandals.jpg', 700.00, 8, 1
FROM categories AS c
WHERE c.slug = 'clothing-accessories'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Leather Sandals');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Handmade Soap Bar', 'Handmade soap bar for everyday washing.',
  'assets/uploads/products/handmade_soap.jpg', 120.00, 20, 1
FROM categories AS c
WHERE c.slug = 'personal-care'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Handmade Soap Bar');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Bamboo Toothbrush', 'Reusable bamboo-handle toothbrush.',
  'assets/uploads/products/bamboo_toothbrush.jpg', 90.00, 25, 1
FROM categories AS c
WHERE c.slug = 'personal-care'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Bamboo Toothbrush');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Shampoo Bottle', 'Shampoo in a reusable-size bottle.',
  'assets/uploads/products/shampoo_bottle.jpg', 180.00, 15, 1
FROM categories AS c
WHERE c.slug = 'personal-care'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Shampoo Bottle');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Notebook Set', 'Set of notebooks for notes and schoolwork.',
  'assets/uploads/products/notebooks.jpg', 150.00, 20, 1
FROM categories AS c
WHERE c.slug = 'office-school'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Notebook Set');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'Colored Pencil Set', 'Colored pencils for drawing and school projects.',
  'assets/uploads/products/pencil_set.jpg', 130.00, 18, 1
FROM categories AS c
WHERE c.slug = 'office-school'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Colored Pencil Set');

INSERT INTO products (category_id, name, description, image, price, stock, is_active)
SELECT c.id, 'School Backpack', 'Everyday backpack for books and school supplies.',
  'assets/uploads/products/school_bag.jpg', 650.00, 10, 1
FROM categories AS c
WHERE c.slug = 'office-school'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'School Backpack');
