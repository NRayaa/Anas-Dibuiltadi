<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quantity',
        'production_price',
        'selling_price',
        'product_id',
        'order_id',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id', 'id');
    }

    public function product(): belongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
