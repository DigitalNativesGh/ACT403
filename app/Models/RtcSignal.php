<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One WebRTC offer or answer waiting to be picked up. Rows live for seconds.
 */
class RtcSignal extends Model
{
    public const UPDATED_AT = null;

    /** Signals older than this are ignored and purged. */
    public const TTL_SECONDS = 60;

    protected $fillable = ['attempt_id', 'viewer', 'ref', 'type', 'sdp'];

    public function scopeFresh(Builder $query): void
    {
        $query->where('created_at', '>=', now()->subSeconds(self::TTL_SECONDS));
    }

    public static function purgeStale(): void
    {
        static::query()->where('created_at', '<', now()->subSeconds(self::TTL_SECONDS))->delete();
    }

    /**
     * Return the given signals and delete them, so each is delivered once.
     */
    public static function take(Builder $query): array
    {
        $rows = $query->fresh()->get(['id', 'attempt_id', 'viewer', 'ref', 'sdp']);
        if ($rows->isNotEmpty()) {
            static::query()->whereIn('id', $rows->pluck('id'))->delete();
        }

        return $rows->all();
    }
}
