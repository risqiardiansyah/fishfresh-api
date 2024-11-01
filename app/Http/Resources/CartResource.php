<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartResource extends JsonResource
{
    public function toArray($request)
    {
        if (filter_var($this->img, FILTER_VALIDATE_URL)) {
            $pict = $this->img;
        } else {
            // $pict = ($this->img == null ? asset('storage/gamesimg/profile.png') : asset('storage/gamesimg/' . $this->img));
            $pict = getImage($this->img);
        }
        $stock = DB::table('games_item_voucher')
          ->where('games_item_code', $this->code)
          ->where('is_delete', 0)
          ->where('voucher_status', 1)
          ->count();

        $memberType = Auth::user()->memberType;
        $price = $this->price;
        if ($memberType == 2) {
            $price = $this->price_reseller;
        }

        $data = [
            'code' => $this->carts_code,
            'item_code' => $this->item_code,
            'quantity' => $this->quantity,
            'price' => $price,
            'price_unipin' => $this->price_unipin,
            'total_price' => $price * $this->quantity,
            'game_code' => $this->game_code,
            'from' => $this->from,
            'title_game' => $this->games_title,
            // 'title_game' => "TT ",
            'title_item' => $this->title,
            'img' => $pict,
            'stock' => $stock
        ];

        return $data;
    }
}
