<?php
namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    private function rules(): array
    {
        return [
            'name'         => 'required|string|max:120',
            'contact'      => 'nullable|string|max:120',
            'social_media' => 'nullable|string|max:160',
            'cost_price'   => 'required|integer|min:0',
            'detail'       => 'nullable|string|max:1000',
            'location'     => 'nullable|string|max:160',
        ];
    }

    public function index(Request $request)
    {
        $vendors = Vendor::query()
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('location', 'like', "%{$s}%")
                ->orWhere('detail', 'like', "%{$s}%")))
            ->orderBy('name')->get();

        return view('vendors.index', [
            'vendors' => $vendors,
            'total'   => Vendor::count(),
            'avgCost' => (int) round(Vendor::avg('cost_price') ?? 0),
        ]);
    }

    public function store(Request $request)
    {
        Vendor::create($request->validate($this->rules()));
        return back()->with('success', 'Vendor baru berhasil ditambahkan.');
    }

    public function update(Request $request, Vendor $vendor)
    {
        $vendor->update($request->validate($this->rules()));
        return back()->with('success', "{$vendor->name} diperbarui.");
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();
        return back()->with('success', 'Vendor dihapus.');
    }
}