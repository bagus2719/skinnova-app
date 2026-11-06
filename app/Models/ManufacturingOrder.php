<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Exception;
use App\Models\StockMovement;

class ManufacturingOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'batch_number',
        'product_id',
        'quantity',
        'status',
        'total_cost',
        'notes',
        'planned_start_date',
        'finished_at',
        'expired_date',
    ];

    protected $casts = [
        'quantity' => 'float',
        'planned_start_date' => 'datetime',
        'finished_at' => 'datetime',
        'expired_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::creating(function (ManufacturingOrder $order) {
            if (empty($order->batch_number)) {
                $lastMO = ManufacturingOrder::query()->orderByDesc('id')->first();
                $nextId = $lastMO ? $lastMO->id + 1 : 1;
                $order->batch_number = 'BATCH-' . date('Ym') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }
        });

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

                    // Potong stok bahan baku & catat mutasi keluar (OUT)
                    foreach ($boms as $bom) {
                        $material = $bom->material;
                        $consumption = $bom->quantity * $order->quantity;
                        $stockBefore = (float) $material->current_stock;
                        $material->current_stock -= $consumption;
                        $material->save();

                        StockMovement::record([
                            'type' => 'OUT',
                            'item_type' => 'Material',
                            'material_id' => $material->id,
                            'quantity' => $consumption,
                            'stock_before' => $stockBefore,
                            'stock_after' => (float) $material->current_stock,
                            'reference_type' => 'Manufacturing Order (BOM Consumption)',
                            'reference_no' => $order->reference_no,
                            'batch_number' => $order->batch_number,
                            'notes' => "Konsumsi bahan untuk produksi {$order->product->name} (Batch: {$order->batch_number})",
                        ]);
                    }

                    // Tambah stok produk jadi & hitung expired date
                    $finishedAt = now();
                    $order->finished_at = $finishedAt;
                    $shelfLife = $product->shelf_life_months ?? 24;
                    $expiredDate = $finishedAt->copy()->addMonths($shelfLife)->toDateString();
                    $order->expired_date = $expiredDate;

                    $prodStockBefore = (float) $product->current_stock;
                    $product->current_stock += $order->quantity;
                    $product->save();

                    // Catat mutasi masuk (IN) produk jadi
                    StockMovement::record([
                        'type' => 'IN',
                        'item_type' => 'Product',
                        'product_id' => $product->id,
                        'batch_number' => $order->batch_number,
                        'expired_date' => $expiredDate,
                        'quantity' => $order->quantity,
                        'stock_before' => $prodStockBefore,
                        'stock_after' => (float) $product->current_stock,
                        'reference_type' => 'Manufacturing Order (Selesai Produksi)',
                        'reference_no' => $order->reference_no,
                        'notes' => "Hasil produksi batch {$order->batch_number}, kadaluarsa: {$expiredDate}",
                    ]);
                } elseif ($oldStatus === 'Done' && $newStatus !== 'Done') {
                    foreach ($boms as $bom) {
                        $material = $bom->material;
                        $consumption = $bom->quantity * $order->quantity;
                        $stockBefore = (float) $material->current_stock;
                        $material->current_stock += $consumption;
                        $material->save();

                        StockMovement::record([
                            'type' => 'IN',
                            'item_type' => 'Material',
                            'material_id' => $material->id,
                            'quantity' => $consumption,
                            'stock_before' => $stockBefore,
                            'stock_after' => (float) $material->current_stock,
                            'reference_type' => 'Manufacturing Order (Rollback/Batal)',
                            'reference_no' => $order->reference_no,
                            'batch_number' => $order->batch_number,
                            'notes' => "Pengembalian stok konsumsi bahan karena status MO diubah dari Done",
                        ]);
                    }

                    $prodStockBefore = (float) $product->current_stock;
                    $product->current_stock -= $order->quantity;
                    $product->save();

                    StockMovement::record([
                        'type' => 'OUT',
                        'item_type' => 'Product',
                        'product_id' => $product->id,
                        'batch_number' => $order->batch_number,
                        'quantity' => $order->quantity,
                        'stock_before' => $prodStockBefore,
                        'stock_after' => (float) $product->current_stock,
                        'reference_type' => 'Manufacturing Order (Rollback/Batal)',
                        'reference_no' => $order->reference_no,
                        'notes' => "Pengurangan stok produk jadi karena status MO dibatalkan dari Done",
                    ]);

                    $order->finished_at = null;
                }
            }
        });
    }
}
