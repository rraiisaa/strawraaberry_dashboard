<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CashFlow extends Model
{
    protected $guarded = [];
    protected $casts = ['date' => 'date'];
    public const INCOME_CATEGORIES  = ['Penjualan Langsung', 'Pelunasan Mitra', 'Lainnya'];
    public const EXPENSE_CATEGORIES = ['Bahan Baku', 'Packaging', 'RnD', 'Operasional', 'Fee Mitra'];
}
