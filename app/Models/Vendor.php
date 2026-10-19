<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $guarded = [];

    /** Link sosial media: URL dipakai apa adanya, @handle diarahkan ke Instagram. */
    public function getSocialUrlAttribute(): ?string
    {
        $s = trim((string) $this->social_media);
        if ($s === '') return null;
        if (str_starts_with($s, 'http')) return $s;
        return 'https://instagram.com/' . ltrim($s, '@');
    }
}