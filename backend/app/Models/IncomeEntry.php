<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeEntry extends Model
{
    protected $fillable = [
        'transaction_id',
        'date',
        'amount',
        'category',
        'description',
        'source',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source' => 'string',
            'status' => 'string',
            'amount' => 'decimal:2',
            'date'   => 'date',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
