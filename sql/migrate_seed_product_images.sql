USE tradehub;

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
