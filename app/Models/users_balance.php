<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class users_balance extends Model
{
    use HasFactory;
     public $timestamps = true;
     protected $fillable = [
        'users_balance_code',
        'users_code',
        'users_balance'
    ];
}
