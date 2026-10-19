<?php
namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public static function actor(): string
    {
        return auth()->user()?->name ?? 'Admin (tanpa login)';
    }

    public static function log(string $action, string $description, ?Model $entity = null, ?int $storeId = null, $before = null, $after = null, ?string $reason = null): ActivityLog
    {
        return ActivityLog::create([
            'store_id' => $storeId, 'actor' => self::actor(), 'action' => $action,
            'entity_type' => $entity ? self::typeOf($entity) : null, 'entity_id' => $entity?->getKey(),
            'description' => $description, 'before' => $before, 'after' => $after, 'reason' => $reason,
        ]);
    }

    private static function typeOf(Model $m): string
    {
        return match (class_basename($m)) {
            'Restock' => 'restock', 'SalesReport' => 'report', 'Payment' => 'payment', 'Store' => 'store', 'PartnerTerm' => 'term',
            default => strtolower(class_basename($m)),
        };
    }
}