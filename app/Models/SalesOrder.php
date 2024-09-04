<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'sales_id',
        'customer_id'
    ];

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'order_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sales_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($salesOrder) {
            if (empty($salesOrder->reference_no)) {
                $salesOrder->reference_no = self::generateUniqueReferenceNo();
            }
        });
    }

    private static function generateUniqueReferenceNo()
    {
        do {
            $referenceNo = 'INV' . strtoupper(Str::random(15)); // Generate a unique reference number
        } while (self::where('reference_no', $referenceNo)->exists()); // Ensure it's unique

        return $referenceNo;
    }
}
