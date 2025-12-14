<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesPayment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }
    
    protected static function booted()
    {
        static::saved(function (SalesPayment $payment) {
            $invoice = $payment->salesInvoice;

            if ($invoice) {
                $invoice->checkPaymentStatus();
            }
        });
    }
}