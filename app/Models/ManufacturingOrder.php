<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Exception;
class ManufacturingOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'product_id',
        'quantity',
        'status',
        'total_cost',
        'notes',
        'planned_start_date',
        'finished_at',
    ];

    protected $casts = [
        'quantity' => 'float',
        'planned_start_date' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    protected static function booted(): void
    {
        static::saving(function (ManufacturingOrder $order) {
            if ($order->product) {
                $totalCost = 0;

                $boms = $order->product->boms;

                foreach ($boms as $bom) {
                    if ($bom->material && $bom->material->std_cost !== null) {
                        $materialCost = $bom->quantity * $bom->material->std_cost;
                        $totalCost += $materialCost;
                    }
                }
                $order->total_cost = $totalCost * $order->quantity;
            }
        });

        static::updating(function (ManufacturingOrder $order) {
            if ($order->isDirty('status')) {
                $oldStatus = $order->getOriginal('status');
                $newStatus = $order->status;

                $product = $order->product;
                $boms = $product->boms;

                if ($newStatus === 'Done' && $oldStatus !== 'Done') {
                    foreach ($boms as $bom) {
                        $material = $bom->material;
                        $consumption = $bom->quantity * $order->quantity;

                        if ($material->current_stock < $consumption) {
                            throw new Exception("Stok material '{$material->name}' ({$material->current_stock} {$material->unit}) tidak mencukupi untuk konsumsi {$consumption} {$material->unit}.");
                        }
                    }

                    foreach ($boms as $bom) {
                        $material = $bom->material;
                        $consumption = $bom->quantity * $order->quantity;
                        $material->current_stock -= $consumption;
                        $material->save();
                    }

                    $product->current_stock += $order->quantity;
                    $product->save();
                    $order->finished_at = now();
                } elseif ($oldStatus === 'Done' && $newStatus !== 'Done') {
                    foreach ($boms as $bom) {
                        $material = $bom->material;
                        $consumption = $bom->quantity * $order->quantity;
                        $material->current_stock += $consumption;
                        $material->save();
                    }

                    $product->current_stock -= $order->quantity;
                    $product->save();
                    $order->finished_at = null;
                }
            }
        });
    }
}
