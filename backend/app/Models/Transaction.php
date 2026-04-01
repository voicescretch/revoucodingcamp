<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    protected $fillable = [
        'transaction_number',
        'order_id',
        'payment_method',
        'total_amount',
        'paid_amount',
        'change_amount',
        'status',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => 'string',
            'status'         => 'string',
            'total_amount'   => 'decimal:2',
            'paid_amount'    => 'decimal:2',
            'change_amount'  => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function incomeEntry(): HasOne
    {
        return $this->hasOne(IncomeEntry::class);
    }
}
