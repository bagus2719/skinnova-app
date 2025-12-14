<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'customer_id', // <<< Foreign Key
        'order_date',
        'expected_shipment_date',
        'total_amount',
        'status',
        'shipping_address',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_shipment_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }
}
