<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\TradeHubPresenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TradeHubFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.wordpress.url' => '']);
        $this->createTradeHubSchema();
    }

    public function test_product_images_use_the_shared_s3_public_url_when_configured(): void
    {
        config([
            'filesystems.disks.s3.bucket' => 'tradehub-images',
            'filesystems.disks.s3.url' => 'https://images.example.test',
        ]);

        $this->assertSame(
            'https://images.example.test/assets/uploads/products/photo.png',
            TradeHubPresenter::productImageUrl('assets/uploads/products/photo.png')
        );
        $this->assertSame(
            'https://external.example.test/photo.png',
            TradeHubPresenter::productImageUrl('https://external.example.test/photo.png')
        );

        config(['filesystems.disks.s3.bucket' => '']);
        $this->assertSame(
            asset('assets/uploads/products/photo.png'),
            TradeHubPresenter::productImageUrl('assets/uploads/products/photo.png')
        );
    }

    public function test_admin_product_image_upload_uses_the_shared_s3_disk(): void
    {
        $admin = $this->createUser(
            Role::query()->create(['name' => 'Administrator']),
            'Admin User',
            'admin@example.test'
        );
        $seller = $this->createUser(
            Role::query()->create(['name' => 'Seller']),
            'Seller User',
            'seller@example.test'
        );
        $category = $this->createCategory();
        Storage::fake('s3');
        config(['filesystems.disks.s3.bucket' => 'tradehub-images']);

        $photo = new UploadedFile(
            base_path('../assets/uploads/products/banana_chips.png'),
            'banana_chips.png',
            'image/png',
            UPLOAD_ERR_OK,
            true
        );

        $this->actingAs($admin)
            ->post('/admin/products/save', [
                'category_id' => $category->id,
                'seller_id' => $seller->id,
                'name' => 'Shared Storage Product',
                'price' => 80,
                'stock' => 5,
                'is_active' => 1,
                'image' => $photo,
            ])
            ->assertRedirect(route('admin.products'));

        $product = Product::query()->where('name', 'Shared Storage Product')->firstOrFail();
        $this->assertStringStartsWith('assets/uploads/products/', $product->image);
        Storage::disk('s3')->assertExists($product->image);
    }

    public function test_storefront_renders_and_catalog_filters_products(): void
    {
        $category = $this->createCategory();
        $mango = $this->createProduct($category, 'Sweet Mangoes (1kg)');
        $this->createProduct($category, 'Organic Kangkong Bundle');

        $this->get('/')
            ->assertOk()
            ->assertSee('Discover the best of your local market')
            ->assertSee('Sweet Mangoes (1kg)');

        $this->get('/products?q=Mango')
            ->assertOk()
            ->assertSee('Sweet Mangoes (1kg)')
            ->assertDontSee('Organic Kangkong Bundle');

        $this->get('/products/'.$mango->id)
            ->assertOk()
            ->assertSee('Add to cart')
            ->assertSee('<title>Sweet Mangoes (1kg) · TradeHub</title>', false)
            ->assertSee('<h1>Sweet Mangoes (1kg)</h1>', false);
    }

    public function test_registration_and_login_use_legacy_password_hash_column(): void
    {
        $customerRole = Role::query()->create(['name' => 'Customer']);

        $this->post('/register', [
            'full_name' => 'Test Customer',
            'email' => 'buyer@example.test',
            'phone' => '09171234567',
            'address' => '123 Test Street, Quezon City',
            'password' => 'Tradehub123',
            'password_confirmation' => 'Tradehub123',
        ])->assertRedirect('/');

        $user = User::query()->where('email', 'buyer@example.test')->firstOrFail();
        $this->assertSame($customerRole->id, $user->role_id);
        $this->assertTrue(Hash::check('Tradehub123', $user->password_hash));

        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', [
            'email' => 'buyer@example.test',
            'password' => 'Tradehub123',
        ])->assertRedirect('/');
    }

    public function test_checkout_records_order_and_decrements_stock(): void
    {
        $role = Role::query()->create(['name' => 'Customer']);
        $sellerRole = Role::query()->create(['name' => 'Seller']);
        $user = User::query()->create([
            'role_id' => $role->id,
            'full_name' => 'Test Buyer',
            'email' => 'buyer@example.test',
            'password_hash' => Hash::make('Tradehub123'),
            'status' => 'active',
            'created_at' => now(),
        ]);
        $seller = $this->createUser($sellerRole, 'Local Seller', 'seller@example.test');
        $product = $this->createProduct($this->createCategory(), 'Local Coffee', 10, 25, $seller);

        $response = $this->actingAs($user)
            ->withSession(['cart' => [$product->id => 2]])
            ->post('/checkout', [
                'full_name' => 'Test Buyer',
                'phone' => '09171234567',
                'address' => '123 Test Street, Quezon City',
                'payment_method' => 'cod',
            ]);
        $response->assertRedirect();
        $this->assertStringContainsString('/orders?ref=TH-', $response->headers->get('Location'));

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => 'Local Coffee',
            'quantity' => 2,
            'total_amount' => 50,
            'seller_id' => $seller->id,
            'status' => 'pending',
        ]);
        $reference = DB::table('transactions')
            ->where('user_id', $user->id)
            ->value('reference');
        $this->assertDatabaseHas('order_delivery_tracking', [
            'reference' => $reference,
            'delivery_address' => '123 Test Street, Quezon City',
        ]);
        $this->assertSame(8, $product->fresh()->stock);

        $this->actingAs($user)
            ->withSession(['cart' => [$product->id => 1]])
            ->post('/checkout', [
                'full_name' => 'Test Buyer',
                'phone' => '09171234567',
                'address' => '123 Test Street, Quezon City',
                'payment_method' => 'gcash',
            ])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_paypal_checkout_creates_and_captures_a_server_verified_order(): void
    {
        config([
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'test-client-id',
            'services.paypal.client_secret' => 'test-client-secret',
            'services.paypal.currency' => 'PHP',
        ]);
        $customer = $this->createUser(Role::query()->create(['name' => 'Customer']), 'PayPal Buyer', 'paypal-buyer@example.test');
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'test-access-token'], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'PAYPAL-ORDER-123'], 201),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-123' => function () use ($customer) {
                return Http::response([
                    'id' => 'PAYPAL-ORDER-123',
                    'status' => 'APPROVED',
                    'purchase_units' => [[
                        'reference_id' => json_decode(DB::table('paypal_payments')->where('paypal_order_id', 'PAYPAL-ORDER-123')->value('checkout_data'), true)['reference'],
                        'custom_id' => (string) $customer->id,
                        'amount' => ['currency_code' => 'PHP', 'value' => '50.00'],
                    ]],
                ], 200);
            },
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-123/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-123',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [['id' => 'PAYPAL-CAPTURE-123', 'status' => 'COMPLETED']]],
                ]],
            ], 201),
        ]);

        $seller = $this->createUser(Role::query()->create(['name' => 'Seller']), 'PayPal Seller', 'paypal-seller@example.test');
        $product = $this->createProduct($this->createCategory(), 'PayPal Item', 10, 25, $seller);

        $this->actingAs($customer)->withSession(['cart' => [$product->id => 2]]);
        $checkoutPage = $this->get('/checkout')
            ->assertOk()
            ->assertSeeText('Or pay online with PayPal')
            ->assertSeeText('Cash on delivery')
            ->assertSee('paypal.Buttons', false)
            ->assertDontSeeText('GCash');
        $checkoutHtml = $checkoutPage->getContent();
        $paymentFormStart = strpos($checkoutHtml, '<form class="panel" method="post" action="');
        $paymentFormEnd = strpos($checkoutHtml, '</form>', $paymentFormStart);
        $this->assertGreaterThan($paymentFormStart, strpos($checkoutHtml, 'class="paypal-option"'));
        $this->assertLessThan($paymentFormEnd, strpos($checkoutHtml, 'class="paypal-option"'));

        $this->postJson('/checkout/paypal/order', [
            'full_name' => 'PayPal Buyer',
            'phone' => '09171234567',
            'address' => '123 Test Street, Quezon City',
        ])
            ->assertOk()
            ->assertJsonPath('id', 'PAYPAL-ORDER-123');
        $this->assertDatabaseHas('paypal_payments', [
            'paypal_order_id' => 'PAYPAL-ORDER-123',
            'user_id' => $customer->id,
            'amount' => 50,
            'currency' => 'PHP',
            'status' => 'created',
        ]);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v2/checkout/orders')
            && $request['purchase_units'][0]['amount']['value'] === '50.00'
            && $request['purchase_units'][0]['amount']['currency_code'] === 'PHP');

        $captureResponse = $this->postJson('/checkout/paypal/capture', ['order_id' => 'PAYPAL-ORDER-123'])
            ->assertOk();
        $reference = DB::table('paypal_payments')->where('paypal_order_id', 'PAYPAL-ORDER-123')->value('reference');
        $captureResponse->assertJsonPath('redirect_url', route('orders', ['ref' => $reference]));
        $this->assertDatabaseHas('paypal_payments', [
            'paypal_order_id' => 'PAYPAL-ORDER-123',
            'status' => 'completed',
            'capture_id' => 'PAYPAL-CAPTURE-123',
        ]);
        $this->assertDatabaseHas('transactions', [
            'reference' => $reference,
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'payment_method' => 'paypal',
            'status' => 'paid',
        ]);
        $this->assertNotNull(DB::table('transactions')->where('reference', $reference)->value('paid_at'));
        $this->assertDatabaseHas('order_delivery_tracking', [
            'reference' => $reference,
            'delivery_address' => '123 Test Street, Quezon City',
        ]);
        $this->assertSame(8, $product->fresh()->stock);
        $captureResponse->assertSessionHas('cart', []);
    }

    public function test_paypal_refunds_if_stock_runs_out_before_capture_is_finalized(): void
    {
        config([
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'test-client-id',
            'services.paypal.client_secret' => 'test-client-secret',
            'services.paypal.currency' => 'PHP',
        ]);
        $customer = $this->createUser(Role::query()->create(['name' => 'Customer']), 'PayPal Buyer', 'paypal-buyer@example.test');
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'test-access-token'], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'PAYPAL-REFUND-123'], 201),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-REFUND-123' => function () use ($customer) {
                return Http::response([
                    'id' => 'PAYPAL-REFUND-123',
                    'status' => 'APPROVED',
                    'purchase_units' => [[
                        'reference_id' => json_decode(DB::table('paypal_payments')->where('paypal_order_id', 'PAYPAL-REFUND-123')->value('checkout_data'), true)['reference'],
                        'custom_id' => (string) $customer->id,
                        'amount' => ['currency_code' => 'PHP', 'value' => '50.00'],
                    ]],
                ], 200);
            },
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-REFUND-123/capture' => Http::response([
                'id' => 'PAYPAL-REFUND-123',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [['id' => 'PAYPAL-CAPTURE-REFUND', 'status' => 'COMPLETED']]],
                ]],
            ], 201),
            'https://api-m.sandbox.paypal.com/v2/payments/captures/PAYPAL-CAPTURE-REFUND/refund' => Http::response([
                'id' => 'PAYPAL-REFUND-RESULT',
                'status' => 'COMPLETED',
            ], 201),
        ]);

        $product = $this->createProduct($this->createCategory(), 'Limited Stock Item', 5, 25);
        $this->actingAs($customer)->withSession(['cart' => [$product->id => 2]]);
        $this->postJson('/checkout/paypal/order', [
            'full_name' => 'PayPal Buyer',
            'phone' => '09171234567',
            'address' => '123 Test Street, Quezon City',
        ])->assertOk();

        $product->update(['stock' => 1]);
        $this->postJson('/checkout/paypal/capture', ['order_id' => 'PAYPAL-REFUND-123'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'The item became unavailable after payment. PayPal has refunded your payment.');

        $this->assertDatabaseHas('paypal_payments', [
            'paypal_order_id' => 'PAYPAL-REFUND-123',
            'status' => 'refunded',
        ]);
        $this->assertSame(0, DB::table('transactions')->where('payment_method', 'paypal')->count());
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v2/payments/captures/PAYPAL-CAPTURE-REFUND/refund'));
    }

    public function test_admin_pages_render_only_for_administrators(): void
    {
        $adminRole = Role::query()->create(['name' => 'Administrator']);
        $admin = User::query()->create([
            'role_id' => $adminRole->id,
            'full_name' => 'TradeHub Admin',
            'email' => 'admin@example.test',
            'password_hash' => Hash::make('Tradehub123'),
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->get('/admin')->assertRedirect(route('login'));

        $this->actingAs($admin);
        foreach (['/admin', '/admin/products', '/admin/categories', '/admin/orders', '/admin/users', '/admin/content', '/admin/reports'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_admin_assigns_only_active_seller_accounts_to_products(): void
    {
        $adminRole = Role::query()->create(['name' => 'Administrator']);
        $sellerRole = Role::query()->create(['name' => 'Seller']);
        $customerRole = Role::query()->create(['name' => 'Customer']);
        $admin = $this->createUser($adminRole, 'Admin User', 'admin@example.test');
        $seller = $this->createUser($sellerRole, 'Seller User', 'seller@example.test');
        $customer = $this->createUser($customerRole, 'Customer User', 'customer@example.test');
        $category = $this->createCategory();
        $product = $this->createProduct($category, 'TradeHub Goods');

        $this->actingAs($admin)
            ->post('/admin/products/save', [
                'id' => $product->id,
                'category_id' => $category->id,
                'seller_id' => $seller->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => 50,
                'stock' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.products'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'seller_id' => $seller->id]);

        $this->from('/admin/users')
            ->put('/admin/users', [
                'id' => $seller->id,
                'action' => 'role',
                'role_id' => $customerRole->id,
            ])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('error');
        $this->assertSame($sellerRole->id, $seller->fresh()->role_id);

        $this->from('/admin/products')
            ->post('/admin/products/save', [
                'id' => $product->id,
                'category_id' => $category->id,
                'seller_id' => $customer->id,
                'name' => $product->name,
                'price' => 50,
                'stock' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/products')
            ->assertSessionHasErrors('seller_id');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'seller_id' => $seller->id]);
    }

    public function test_admin_can_assign_a_delivery_rider_and_start_delivery(): void
    {
        $adminRole = Role::query()->create(['name' => 'Administrator']);
        $riderRole = Role::query()->create(['name' => 'Delivery Rider']);
        $customerRole = Role::query()->create(['name' => 'Customer']);
        $admin = $this->createUser($adminRole, 'Admin User', 'admin@example.test');
        $rider = $this->createUser($riderRole, 'Rider User', 'rider@example.test');
        $customer = $this->createUser($customerRole, 'Customer User', 'customer@example.test');
        $reference = 'TH-20261117-TEST';
        $this->createOrder($reference, $customer);

        $this->actingAs($admin)
            ->put('/admin/orders', [
                'reference' => $reference,
                'status' => 'out_for_delivery',
                'rider_id' => $rider->id,
                'tracking_number' => 'TRACK-123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'reference' => $reference,
            'status' => 'out_for_delivery',
            'tracking_number' => 'TRACK-123',
        ]);
        $this->assertDatabaseHas('order_delivery_tracking', [
            'reference' => $reference,
            'rider_id' => $rider->id,
        ]);
        $this->assertNotNull(DB::table('order_delivery_tracking')
            ->where('reference', $reference)
            ->value('delivery_started_at'));

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSeeText('Out for delivery')
            ->assertSeeText($rider->full_name);

        $this->actingAs($customer)
            ->get('/orders')
            ->assertOk()
            ->assertSeeText('Out for delivery')
            ->assertSeeText($rider->full_name);
    }

    public function test_admin_cannot_start_delivery_without_assigning_a_rider(): void
    {
        $adminRole = Role::query()->create(['name' => 'Administrator']);
        $customerRole = Role::query()->create(['name' => 'Customer']);
        $admin = $this->createUser($adminRole, 'Admin User', 'admin@example.test');
        $customer = $this->createUser($customerRole, 'Customer User', 'customer@example.test');
        $reference = 'TH-20261117-NRDR';
        $this->createOrder($reference, $customer);

        $this->actingAs($admin)
            ->from('/admin/orders')
            ->put('/admin/orders', [
                'reference' => $reference,
                'status' => 'out_for_delivery',
            ])
            ->assertRedirect('/admin/orders')
            ->assertSessionHasErrors('rider_id');

        $this->actingAs($admin)
            ->from('/admin/orders')
            ->put('/admin/orders', [
                'reference' => $reference,
                'status' => 'pending',
                'rider_id' => $customer->id,
            ])
            ->assertRedirect('/admin/orders')
            ->assertSessionHasErrors('rider_id');

        $this->assertDatabaseHas('transactions', [
            'reference' => $reference,
            'status' => 'pending',
        ]);
    }

    public function test_delivery_rider_can_view_assigned_orders_and_complete_cod_delivery(): void
    {
        $riderRole = Role::query()->create(['name' => 'Delivery Rider']);
        $customerRole = Role::query()->create(['name' => 'Customer']);
        $rider = $this->createUser($riderRole, 'Rider User', 'rider@example.test');
        $customer = $this->createUser($customerRole, 'Customer User', 'customer@example.test');
        $reference = 'TH-20261117-RIDER';
        $this->createOrder($reference, $customer);
        DB::table('order_delivery_tracking')->insert([
            'reference' => $reference,
            'delivery_address' => '55 Market Road, Quezon City',
            'rider_id' => $rider->id,
        ]);

        $this->post('/login', [
            'email' => $rider->email,
            'password' => 'Tradehub123',
        ])->assertRedirect('/delivery');

        $this->get('/delivery')
            ->assertOk()
            ->assertSeeText('Delivery dashboard')
            ->assertSeeText('Assigned deliveries')
            ->assertSeeText('Delivery map')
            ->assertSeeText($reference)
            ->assertSeeText('Customer User')
            ->assertSeeText('55 Market Road, Quezon City');

        $this->post('/delivery/orders', [
            'reference' => $reference,
            'action' => 'start',
        ])->assertRedirect('/delivery');
        $this->assertDatabaseHas('transactions', [
            'reference' => $reference,
            'status' => 'out_for_delivery',
        ]);

        $this->actingAs($rider)
            ->postJson('/delivery/location', [
                'reference' => $reference,
                'latitude' => 24.0,
                'longitude' => 121.0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);

        $this->postJson('/delivery/location', [
            'reference' => $reference,
            'latitude' => 14.6,
            'longitude' => 130.0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);

        $this->postJson('/delivery/location', [
            'reference' => $reference,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertDatabaseHas('order_delivery_tracking', [
            'reference' => $reference,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        $this->actingAs($customer)
            ->getJson('/orders/delivery-location?reference='.$reference)
            ->assertOk()
            ->assertJsonPath('latitude', 14.5995)
            ->assertJsonPath('longitude', 120.9842);
        $this->get('/orders')
            ->assertOk()
            ->assertSeeText('Track delivery')
            ->assertSee('assets/js/delivery-map.js')
            ->assertSee('leaflet@1.9.4/dist/leaflet.js')
            ->assertSee('class="delivery-map"', false);

        $otherCustomer = $this->createUser($customerRole, 'Other Customer', 'other@example.test');
        $this->actingAs($otherCustomer)
            ->getJson('/orders/delivery-location?reference='.$reference)
            ->assertNotFound();

        $this->actingAs($rider);
        $this->from('/delivery')->post('/delivery/orders', [
            'reference' => $reference,
            'action' => 'collect_payment',
        ])->assertRedirect('/delivery');
        $this->assertNotNull(DB::table('transactions')->where('reference', $reference)->value('paid_at'));

        $this->from('/delivery')->post('/delivery/orders', [
            'reference' => $reference,
            'action' => 'complete',
        ])->assertRedirect('/delivery');
        $this->assertDatabaseHas('transactions', [
            'reference' => $reference,
            'status' => 'completed',
        ]);

        $this->actingAs($customer)
            ->get('/orders')
            ->assertOk()
            ->assertSeeText('Rider User')
            ->assertSeeText('Order completed');
    }

    public function test_completed_customer_can_rate_a_purchased_product_and_its_seller(): void
    {
        $customerRole = Role::query()->create(['name' => 'Customer']);
        $sellerRole = Role::query()->create(['name' => 'Seller']);
        $customer = $this->createUser($customerRole, 'Customer User', 'customer@example.test');
        $seller = $this->createUser($sellerRole, 'Local Seller', 'seller@example.test');
        $category = $this->createCategory();
        $product = $this->createProduct($category, 'Handwoven Basket', seller: $seller);
        $selfSoldProduct = $this->createProduct($category, 'Self-sold Item', seller: $customer);
        $reference = 'TH-20261117-RATE';
        $this->createOrder($reference, $customer, $product, status: 'completed');
        $this->createOrder('TH-20261117-SELF', $customer, $selfSoldProduct, status: 'completed');
        $pendingReference = 'TH-20261117-PEND';
        $this->createOrder($pendingReference, $customer, $product);

        $this->actingAs($customer)
            ->get('/orders')
            ->assertOk()
            ->assertSeeText('Rate your purchase')
            ->assertSeeText('Seller rating')
            ->assertSeeText('You cannot rate a product you sold yourself.')
            ->assertDontSee('name="product_id" value="'.$selfSoldProduct->id.'"', false);

        $this->post('/orders/reviews', [
            'reference' => $pendingReference,
            'product_id' => $product->id,
            'type' => 'product',
            'rating' => 5,
        ])->assertNotFound();

        $this->post('/orders/reviews', [
            'reference' => $reference,
            'product_id' => $product->id,
            'type' => 'product',
            'rating' => 5,
            'comment' => 'Exactly as described.',
        ])->assertRedirect();
        $this->post('/orders/reviews', [
            'reference' => $reference,
            'product_id' => $product->id,
            'type' => 'seller',
            'rating' => 4,
            'comment' => 'Friendly seller.',
        ])->assertRedirect();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'reviewer_id' => $customer->id,
            'reference' => $reference,
            'rating' => 5,
        ]);
        $this->assertDatabaseHas('seller_reviews', [
            'seller_id' => $seller->id,
            'reviewer_id' => $customer->id,
            'reference' => $reference,
            'rating' => 4,
        ]);

        $this->post('/orders/reviews', [
            'reference' => $reference,
            'product_id' => $product->id,
            'type' => 'seller',
            'rating' => 3,
        ])->assertRedirect();
        $this->assertSame(1, DB::table('seller_reviews')->where('reference', $reference)->count());

        $this->get('/products/'.$product->id)
            ->assertOk()
            ->assertSeeText('5.0 / 5')
            ->assertSeeText('Local Seller');
        $this->get('/sellers/'.$seller->id)
            ->assertOk()
            ->assertSeeText('Local Seller')
            ->assertSeeText('3.0 / 5');
    }

    public function test_users_can_offer_and_mutually_confirm_a_barter_exchange(): void
    {
        $customerRole = Role::query()->create(['name' => 'Customer']);
        $owner = $this->createUser($customerRole, 'Listing Owner', 'owner@example.test');
        $requester = $this->createUser($customerRole, 'Trade Requester', 'requester@example.test');
        $otherRequester = $this->createUser($customerRole, 'Other Requester', 'other@example.test');

        $this->actingAs($owner)
            ->post('/trades', [
                'title' => 'Wooden dining chair',
                'description' => 'Solid wood chair in good condition.',
                'wanted_item' => 'Small bookshelf',
            ])
            ->assertRedirect();
        $listingId = (int) DB::table('trade_listings')->value('id');
        $this->actingAs($owner)
            ->get('/trades')
            ->assertOk()
            ->assertSeeText('Wooden dining chair')
            ->assertSeeText('How to accept a trade offer')
            ->assertSeeText('Manage');
        $this->get('/trades/'.$listingId)
            ->assertOk()
            ->assertSeeText('Looking for:');

        $this->actingAs($requester)
            ->post('/trades/'.$listingId.'/offers', [
                'offered_item' => 'Two-shelf bookcase',
                'message' => 'Can exchange this weekend.',
            ])
            ->assertRedirect();
        $requesterOfferId = (int) DB::table('trade_offers')
            ->where('requester_id', $requester->id)
            ->value('id');

        $this->actingAs($otherRequester)
            ->post('/trades/'.$listingId.'/offers', [
                'offered_item' => 'Desk lamp',
            ])
            ->assertRedirect();
        $otherOfferId = (int) DB::table('trade_offers')
            ->where('requester_id', $otherRequester->id)
            ->value('id');
        $this->actingAs($owner)
            ->get('/trades/'.$listingId)
            ->assertOk()
            ->assertSeeText('Two-shelf bookcase')
            ->assertSeeText('How to accept an offer')
            ->assertSeeText('Accept offer');

        $this->actingAs($requester)
            ->post('/trade-offers/'.$requesterOfferId.'/accept')
            ->assertForbidden();

        $this->actingAs($owner)
            ->post('/trade-offers/'.$requesterOfferId.'/accept')
            ->assertRedirect();
        $this->assertDatabaseHas('trade_listings', ['id' => $listingId, 'status' => 'in_trade']);
        $this->assertDatabaseHas('trade_offers', ['id' => $otherOfferId, 'status' => 'declined']);

        $this->post('/trade-offers/'.$requesterOfferId.'/confirm')->assertRedirect();
        $this->assertDatabaseHas('trade_offers', ['id' => $requesterOfferId, 'status' => 'accepted']);
        $this->assertNotNull(DB::table('trade_offers')
            ->where('id', $requesterOfferId)
            ->value('owner_confirmed_at'));

        $this->actingAs($requester)
            ->post('/trade-offers/'.$requesterOfferId.'/confirm')
            ->assertRedirect();
        $this->assertDatabaseHas('trade_offers', ['id' => $requesterOfferId, 'status' => 'completed']);
        $this->assertDatabaseHas('trade_listings', ['id' => $listingId, 'status' => 'completed']);
    }

    public function test_non_rider_cannot_open_delivery_dashboard(): void
    {
        $customerRole = Role::query()->create(['name' => 'Customer']);
        $customer = $this->createUser($customerRole, 'Customer User', 'customer@example.test');

        $this->actingAs($customer)
            ->get('/delivery')
            ->assertForbidden();

        $this->postJson('/delivery/location', [
            'reference' => 'TH-DOES-NOT-MATTER',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ])->assertForbidden();
    }

    public function test_inactive_accounts_are_logged_out_on_the_next_request(): void
    {
        $role = Role::query()->create(['name' => 'Customer']);
        $user = User::query()->create([
            'role_id' => $role->id,
            'full_name' => 'Inactive Customer',
            'email' => 'inactive@example.test',
            'password_hash' => Hash::make('Tradehub123'),
            'status' => 'inactive',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/orders')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    private function createTradeHubSchema(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 50)->unique();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->string('full_name', 120);
            $table->string('email', 190)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('password_hash');
            $table->string('status', 10)->default('active');
            $table->dateTime('created_at')->nullable();
        });
        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('seller_id')->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('image', 255)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->nullable();
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('reference', 20);
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('seller_id')->nullable();
            $table->string('product_name', 150);
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method', 30)->default('cod');
            $table->string('status', 20)->default('pending');
            $table->string('tracking_number', 64)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('created_at')->nullable();
        });
        Schema::create('paypal_payments', function (Blueprint $table): void {
            $table->string('paypal_order_id', 64)->primary();
            $table->unsignedInteger('user_id');
            $table->string('reference', 20)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('status', 24)->default('created');
            $table->string('capture_id', 64)->nullable();
            $table->json('checkout_data');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('order_delivery_tracking', function (Blueprint $table): void {
            $table->string('reference', 20)->primary();
            $table->string('delivery_address', 255)->default('');
            $table->unsignedInteger('rider_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('location_updated_at')->nullable();
            $table->dateTime('delivery_started_at')->nullable();
        });
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('reviewer_id');
            $table->string('reference', 20);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['product_id', 'reviewer_id', 'reference']);
        });
        Schema::create('seller_reviews', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('seller_id');
            $table->unsignedInteger('reviewer_id');
            $table->string('reference', 20);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['seller_id', 'reviewer_id', 'reference']);
        });
        Schema::create('trade_listings', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('title', 120);
            $table->text('description');
            $table->string('wanted_item', 255);
            $table->string('status', 20)->default('open');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('trade_offers', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('listing_id');
            $table->unsignedInteger('requester_id');
            $table->string('offered_item', 120);
            $table->string('message', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->dateTime('owner_confirmed_at')->nullable();
            $table->dateTime('requester_confirmed_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('announcements', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title', 150);
            $table->text('body');
            $table->boolean('is_published')->default(true);
            $table->dateTime('created_at')->nullable();
        });
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key', 60)->primary();
            $table->text('value');
        });
        DB::table('settings')->insert([
            ['key' => 'site_name', 'value' => 'TradeHub'],
            ['key' => 'hero_title', 'value' => 'Discover the best of your local market'],
            ['key' => 'commission_rate', 'value' => '5'],
        ]);
    }

    private function createUser(Role $role, string $name, string $email): User
    {
        return User::query()->create([
            'role_id' => $role->id,
            'full_name' => $name,
            'email' => $email,
            'password_hash' => Hash::make('Tradehub123'),
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    private function createOrder(
        string $reference,
        User $customer,
        ?Product $product = null,
        ?User $seller = null,
        string $status = 'pending'
    ): void
    {
        DB::table('transactions')->insert([
            'reference' => $reference,
            'user_id' => $customer->id,
            'product_id' => $product?->id ?? 1,
            'seller_id' => $seller?->id ?? $product?->seller_id,
            'product_name' => $product?->name ?? 'Test product',
            'quantity' => 1,
            'unit_price' => 50,
            'total_amount' => 50,
            'payment_method' => 'cod',
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    private function createCategory(): Category
    {
        return Category::query()->create([
            'name' => 'Local Goods',
            'slug' => 'local-goods',
            'description' => 'Products from local sellers.',
        ]);
    }

    private function createProduct(
        Category $category,
        string $name,
        int $stock = 10,
        float $price = 50,
        ?User $seller = null
    ): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'seller_id' => $seller?->id,
            'name' => $name,
            'description' => 'A locally made product.',
            'price' => $price,
            'stock' => $stock,
            'is_active' => true,
            'created_at' => now(),
        ]);
    }
}
