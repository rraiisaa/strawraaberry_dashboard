<?php
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private function rules(): array
    {
        return [
            'name'          => 'required|string|max:120',
            'category'      => ['required', Rule::in(Product::CATEGORIES)],
            'cost_price'    => 'required|integer|min:0',
            'selling_price' => 'required|integer|min:0',
            'stock_qty'     => 'required|integer|min:0',
            'note'          => 'nullable|string|max:500',
        ];
    }

    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->orderBy('category')->orderBy('name')->get();

        return view('products.index', [
            'products'   => $products,
            'categories' => Product::CATEGORIES,
            'stockValue' => $products->sum(fn ($p) => $p->stock_qty * $p->cost_price),
            'defectLoss' => $products->sum(fn ($p) => $p->defective_qty * $p->cost_price),
        ]);
    }

    public function store(Request $request)
    {
        Product::create($request->validate($this->rules()));
        return back()->with('success', 'Produk baru berhasil ditambahkan');
    }

    public function update(Request $request, Product $product)
    {
        $product->update($request->validate($this->rules()));
        return back()->with('success', "{$product->name} diperbarui.");
    }

    /** Pindahkan qty dari stok aktif ke stok cacat agar tidak tercampur. */
    public function reject(Request $request, Product $product)
    {
        $data = $request->validate(['qty' => 'required|integer|min:1|max:' . $product->stock_qty]);
        $product->decrement('stock_qty', $data['qty']);
        $product->increment('defective_qty', $data['qty']);
        return back()->with('success', "{$data['qty']} pcs {$product->name} dipindah ke stok cacat.");
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return back()->with('success', 'Produk dihapus.');
    }
}