<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TradeHubFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.wordpress.url' => '']);
        $this->createTradeHubSchema();
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
            ->assertSee('Add to cart');
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
        $user = User::query()->create([
            'role_id' => $role->id,
            'full_name' => 'Test Buyer',
            'email' => 'buyer@example.test',
            'password_hash' => Hash::make('Tradehub123'),
            'status' => 'active',
            'created_at' => now(),
        ]);
        $product = $this->createProduct($this->createCategory(), 'Local Coffee', 10, 25);

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
            'status' => 'pending',
        ]);
        $this->assertSame(8, $product->fresh()->stock);
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

    private function createCategory(): Category
    {
        return Category::query()->create([
            'name' => 'Local Goods',
            'slug' => 'local-goods',
            'description' => 'Products from local sellers.',
        ]);
    }

    private function createProduct(Category $category, string $name, int $stock = 10, float $price = 50): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'description' => 'A locally made product.',
            'price' => $price,
            'stock' => $stock,
            'is_active' => true,
            'created_at' => now(),
        ]);
    }
}
