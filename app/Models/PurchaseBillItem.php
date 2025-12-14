<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseBillItem extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];

    public function purchaseBill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class);
    }
    
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
    
    public function purchaseOrderItem(): BelongsTo 
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
