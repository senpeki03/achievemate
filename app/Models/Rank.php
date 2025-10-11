<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    protected $table = 'rank';
    protected $primaryKey = 'Rank_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'User_id',  // creator for rules OR owner for results
        'GWA',      // per-user result only
        'Rank',     // rank name (both rules & results)
        'min_gwa',  // rule only
        'max_gwa',  // rule only
        'is_rule',  // 1 = rule row, 0 = per-user result
    ];

    protected $casts = [
        'GWA'     => 'decimal:4',
        'min_gwa' => 'decimal:4',
        'max_gwa' => 'decimal:4',
        'is_rule' => 'boolean',
    ];

    /* Scopes */
    public function scopeRules($q)   { return $q->where('is_rule', 1); }
    public function scopeResults($q) { return $q->where('is_rule', 0); }

    /* Helpers */
    public static function ruleForGwa(float $gwa): ?self
    {
        return static::rules()
            ->where('min_gwa', '<=', $gwa)
            ->where('max_gwa', '>=', $gwa)
            ->orderBy('min_gwa')
            ->first();
    }

    public static function rankNameForGwa(float $gwa): ?string
    {
        return optional(static::ruleForGwa($gwa))->Rank;
    }

    public static function upsertUserRank(int $userId, float $gwa): ?self
    {
        $name = static::rankNameForGwa($gwa);
        if (!$name) return null;

        return static::query()->updateOrCreate(
            ['is_rule' => 0, 'User_id' => $userId],
            ['GWA' => round($gwa, 4), 'Rank' => $name]
        );
    }
}
