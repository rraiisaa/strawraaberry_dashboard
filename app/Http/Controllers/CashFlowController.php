<?php
namespace App\Http\Controllers;

use App\Models\CashFlow;
use Illuminate\Http\Request;

class CashFlowController extends Controller
{
    public function index()
    {
        return view('cashflow.index', [
            'flows' => CashFlow::latest('date')->latest('id')->paginate(20),
            'incomeCats' => CashFlow::INCOME_CATEGORIES,
            'expenseCats' => CashFlow::EXPENSE_CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        CashFlow::create($request->validate([
            'type' => 'required|in:income,expense',
            'category' => 'required|string|max:60',
            'amount' => 'required|integer|min:1',
            'description' => 'nullable|string|max:200',
            'date' => 'required|date',
        ]));
        return back()->with('success', 'Tercatat!');
    }

    public function destroy(CashFlow $cashFlow)
    {
        $cashFlow->delete();
        return back()->with('success', 'Catatan dihapus.');
    }
}