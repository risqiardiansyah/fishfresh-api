<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'item_code', 'quantity', 'price', 'total_price', 'users_code'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
