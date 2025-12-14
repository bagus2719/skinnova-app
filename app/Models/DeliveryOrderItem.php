<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderItem extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'delivery_order_id',
        'product_id',
        'sales_order_quantity',
        'delivered_quantity',
    ];

    protected $casts = [
        'sales_order_quantity' => 'decimal:4',
        'delivered_quantity' => 'decimal:4',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
