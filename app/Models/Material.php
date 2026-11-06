<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'unit',
        'current_stock',
        'minimum_stock',
        'std_cost',
    ];
    protected $casts = [
        'current_stock' => 'float',
        'minimum_stock' => 'float',
        'std_cost' => 'float',
    ];
    
    public function boms()
    {
        return $this->hasMany(BillOfMaterial::class);
    }
}
