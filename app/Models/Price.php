<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    use HasFactory;

    protected $keyType = "string";
    protected $primary = "price_id";
    protected $guarded = [];

    public function game()
    {
        return $this->belongsTo(GameList::class, 'game_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function pricepoint()
    {
        return $this->belongsTo(PricePoint::class, 'price_point_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
