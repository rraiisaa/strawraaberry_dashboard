<?php
namespace App\Http\Controllers\Concerns;

use App\Exceptions\BusinessRuleException;

trait RunsBusinessActions
{
    /** Jalankan aksi bisnis; pelanggaran aturan ditampilkan sebagai pesan, bukan error 500. */
    protected function attempt(callable $fn, string $success, callable|string|null $redirect = null)
    {
        try {
            $result = $fn();
        } catch (BusinessRuleException $e) {
            return back()->withInput()->withErrors(['bisnis' => $e->getMessage()]);
        }
        $to = is_callable($redirect) ? $redirect($result) : $redirect;
        return ($to ? redirect($to) : back())->with('success', $success);
    }

    protected function storeProof($request, string $folder): ?string
    {
        return $request->hasFile('proof') ? $request->file('proof')->store("partner-proofs/{$folder}") : null; // disk privat, bukan URL publik
    }
}