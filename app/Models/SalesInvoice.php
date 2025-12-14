<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoice extends Model
{
    protected $fillable = [
        'reference_no', 
        'sales_order_id', 
        'invoice_date', 'due_date', 
        'total_amount', 
        'tax_amount', 
        'grand_total', 
        'status', 
        'notes'
    ];
    
    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }
    
    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }
}
