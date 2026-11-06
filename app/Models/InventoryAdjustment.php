<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\StockMovement;

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
            $stockBefore = (float) $material->current_stock;

            if ($adjustment->type === 'IN') {
                $material->current_stock += $adjustment->quantity;
            } elseif ($adjustment->type === 'OUT') {
                $material->current_stock -= $adjustment->quantity;
            }

            $material->save();

            StockMovement::record([
                'type' => $adjustment->type,
                'item_type' => 'Material',
                'material_id' => $material->id,
                'quantity' => $adjustment->quantity,
                'stock_before' => $stockBefore,
                'stock_after' => (float) $material->current_stock,
                'reference_type' => 'Penyesuaian Stok (Inventory Adjustment)',
                'reference_no' => $adjustment->reference_no,
                'notes' => $adjustment->reason ?? 'Penyesuaian stok manual',
            ]);
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
