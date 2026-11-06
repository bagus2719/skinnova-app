<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'movement_date' => 'datetime',
        'expired_date' => 'date',
        'quantity' => 'float',
        'stock_before' => 'float',
        'stock_after' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Helper to record stock movement conveniently.
     */
    public static function record(array $data): self
    {
        if (!isset($data['movement_date'])) {
            $data['movement_date'] = now();
        }
        if (!isset($data['created_by']) && auth()->check()) {
            $data['created_by'] = auth()->id();
        }

        return self::create($data);
    }
}

