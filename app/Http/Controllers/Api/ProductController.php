<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category', 'prices');

        if ($request->filled('search')) {
            $query->where('name', 'ILIKE', '%'.$request->search.'%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // កែប្រែត្រង់នេះ៖ បោះទិន្នន័យ $products ទៅចំៗ និងបន្ថែម UNICODE options
        $products = $query->orderBy('name')->get();

        return response()->json($products, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product->load('category', 'prices'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'note' => 'nullable|string',
            'prices' => 'required|array|min:1',
            'prices.*.unit' => 'required|string|max:50',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.currency' => 'nullable|in:KHR,USD',
        ]);

        $product = DB::transaction(function () use ($data) {
            $product = Product::create([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'note' => $data['note'] ?? null,
            ]);
            $product->prices()->createMany($data['prices']);

            return $product;
        });

        return response()->json([
            'success' => true,
            'data' => $product->load('category', 'prices'),
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:150',
            'note' => 'nullable|string',
            'prices' => 'sometimes|array|min:1',
            'prices.*.unit' => 'required_with:prices|string|max:50',
            'prices.*.price' => 'required_with:prices|numeric|min:0',
            'prices.*.currency' => 'nullable|in:KHR,USD',
        ]);

        DB::transaction(function () use ($data, $product) {
            $product->update(collect($data)->only(['category_id', 'name', 'note'])->toArray());

            if (isset($data['prices'])) {
                $product->prices()->delete();
                $product->prices()->createMany($data['prices']);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $product->fresh()->load('category', 'prices'),
        ]);
    }

    public function uploadImage(Request $request, Product $product)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // លុបរូបចាស់ (បើមាន) កុំឱ្យសល់សំរាមក្នុង folder
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $path = $request->file('image')->store('products', 'public');

        $product->update(['image_path' => $path]);

        return response()->json([
            'success' => true,
            'data' => $product->load('category', 'prices'),
        ]);
    }

    public function destroy(Product $product)
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted',
        ]);
    }
}
