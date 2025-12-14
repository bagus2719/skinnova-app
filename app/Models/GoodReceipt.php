<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'purchase_order_id',
        'receipt_date',
        'status',
        'notes',
        'received_items',
    ];
    
    protected $casts = [
        'receipt_date' => 'date',
        'received_items' => 'array',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
