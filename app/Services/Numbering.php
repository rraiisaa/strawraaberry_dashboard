<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class Numbering
{
    /** Nomor dokumen unik berbasis ID baris: PREFIX-YYYYMM-0001 (tidak bisa bentrok). */
    public static function assign(Model $m, string $prefix): void
    {
        $m->forceFill(['number' => sprintf('%s-%s-%04d', $prefix, now()->format('Ym'), $m->getKey())])->saveQuietly();
    }
    public static function temp(): string { return 'TMP-' . bin2hex(random_bytes(8)); }
}