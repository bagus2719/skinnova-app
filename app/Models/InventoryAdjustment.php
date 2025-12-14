<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'material_id',
        'type',
        'quantity',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'float',
        'type' => 'string',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    protected static function booted(): void
    {
        static::created(function (InventoryAdjustment $adjustment) {
            $material = $adjustment->material;

            if ($adjustment->type === 'IN') {
                $material->current_stock += $adjustment->quantity;
            } elseif ($adjustment->type === 'OUT') {
                $material->current_stock -= $adjustment->quantity;
            }

            $material->save();
        });

        static::deleted(function (InventoryAdjustment $adjustment) {
            $material = $adjustment->material;

            if ($adjustment->type === 'IN') {
                $material->current_stock -= $adjustment->quantity;
            } elseif ($adjustment->type === 'OUT') {
                $material->current_stock += $adjustment->quantity;
            }

            $material->save();
        });
    }
}
