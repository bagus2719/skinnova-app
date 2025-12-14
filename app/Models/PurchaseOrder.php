<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'vendor_id',
        'order_date',
        'expected_receipt_date',
        'total_amount',
        'status',
        'notes',
    ];
    
    protected $casts = [
        'order_date' => 'date',
        'expected_receipt_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
    
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
    public function goodReceipts(): HasMany
    {
        return $this->hasMany(GoodReceipt::class);
    }
    public function purchaseBills(): HasMany
    {
        return $this->hasMany(PurchaseBill::class);
    }
}
