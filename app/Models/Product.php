<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'unit',
        'selling_price',
        'is_active',
        'description',
        'current_stock',
        'minimum_stock',
        'bpom_number',
        'shelf_life_months',
    ];

    protected $casts = [
        'selling_price' => 'float',
        'is_active' => 'boolean',
        'current_stock' => 'float',
        'minimum_stock' => 'float',
        'shelf_life_months' => 'integer',
    ];

    public function boms(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class);
    }
}
