<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pin extends Model
{
    use HasFactory;
    protected $table = 'users_pin';
     protected $fillable = [
        'users_pin_code', 'users_code', 'users_pin', 'users_pin_attempts'
    ];

     public function user()
    {
        return $this->belongsTo(User::class);
    }


}
