<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $editing = $request->filled('id')
            ? Product::query()->findOrFail($request->integer('id'))
            : null;

        return view('admin.products', [
            'categories' => Category::query()->orderBy('name')->get(),
            'sellers' => User::query()
                ->where('status', 'active')
                ->whereHas('role', fn ($query) => $query->where('name', 'Seller'))
                ->orderBy('full_name')
                ->get(),
            'editing' => $editing,
            'showForm' => $request->query('action') === 'new' || $editing !== null,
            'products' => Product::query()
                ->with(['category', 'seller'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:0'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'seller_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
        if (! User::query()
            ->whereKey($data['seller_id'])
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('name', 'Seller'))
            ->exists()) {
            return back()
                ->withErrors(['seller_id' => 'Select an active seller account.'])
                ->withInput();
        }

        $product = ! empty($data['id'])
            ? Product::query()->findOrFail($data['id'])
            : new Product;

        $oldImage = $product->image;
        $imagePath = $oldImage;

        if ($request->hasFile('image')) {
            $extension = $request->file('image')->extension();
            $filename = now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.'.$extension;
            $imagePath = 'assets/uploads/products/'.$filename;

            if (config('filesystems.disks.s3.bucket')) {
                $stream = fopen($request->file('image')->getRealPath(), 'rb');
                if ($stream === false) {
                    throw new \RuntimeException('The uploaded product photo could not be opened.');
                }
                try {
                    $stored = Storage::disk('s3')->put(
                        $imagePath,
                        $stream,
                        ['ContentType' => $request->file('image')->getMimeType() ?? 'application/octet-stream']
                    );
                } finally {
                    fclose($stream);
                }
                if (! $stored) {
                    throw new \RuntimeException('The product photo could not be saved to shared storage.');
                }
            } else {
                $directory = public_path('assets/uploads/products');
                File::ensureDirectoryExists($directory);
                $request->file('image')->move($directory, $filename);
            }
        } elseif ($request->boolean('remove_image')) {
            $imagePath = null;
        }

        $product->fill([
            'category_id' => $data['category_id'],
            'seller_id' => $data['seller_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'image' => $imagePath,
            'price' => $data['price'],
            'stock' => $data['stock'],
            'is_active' => $request->boolean('is_active'),
        ])->save();

        if ($oldImage !== $imagePath) {
            $this->deleteImage($oldImage);
        }

        return redirect()->route('admin.products')->with(
            'success',
            $product->wasRecentlyCreated ? 'Product added.' : 'Product updated.'
        );
    }

    public function action(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:toggle,delete'],
            'id' => ['required', 'integer', 'exists:products,id'],
        ]);
        $product = Product::query()->findOrFail($data['id']);

        if ($data['action'] === 'toggle') {
            $product->is_active = ! $product->is_active;
            $product->save();

            return back()->with('success', 'Product availability changed on the storefront.');
        }

        if ($product->transactions()->exists() || $product->reviews()->exists()) {
            $product->is_active = false;
            $product->save();

            return back()->with('info', 'This product has order history, so it was deactivated instead of deleted.');
        }

        $image = $product->image;
        $product->delete();
        $this->deleteImage($image);

        return back()->with('success', 'Product deleted.');
    }

    private function deleteImage(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'assets/uploads/products/')) {
            if (config('filesystems.disks.s3.bucket')) {
                Storage::disk('s3')->delete($path);
            } else {
                $file = public_path($path);
                if (File::isFile($file)) {
                    File::delete($file);
                }
            }
        }
    }
}
