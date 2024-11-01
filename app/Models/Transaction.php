<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    protected $primary = 'invoice';

    protected $guarded = [];

    public function game()
    {
        return $this->belongsTo(GameList::class, 'game_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'method_payment');
    }

    public function pricepoint()
    {
        return $this->belongsTo(PricePoint::class, 'price_point_id');
    }

    public function price()
    {
        return $this->belongsTo(Price::class, 'price_id', 'price_id');
    }

    public function transactionDetail()
    {
        return $this->belongsTo(TransactionDetail::class, 'invoice', 'invoice_id');
    }
}
