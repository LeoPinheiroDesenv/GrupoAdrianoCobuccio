<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'wallet_id',
        'target_wallet_id',
        'type',
        'amount',
        'reversed_transaction_id',
        'is_reversed',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_reversed' => 'boolean',
        ];
    }

    /**
     * Get the wallet that owns the transaction.
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the target wallet for transfers.
     */
    public function targetWallet()
    {
        return $this->belongsTo(Wallet::class, 'target_wallet_id');
    }

    /**
     * Get the reversed transaction.
     */
    public function reversedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'reversed_transaction_id');
    }
}
