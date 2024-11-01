<?php

namespace App\Http\Repositories;

use App\Http\Resources\CartResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartRepository
{
  public function allCart($users_code)
  {
    $select = [
      'carts.users_code',
      'carts.quantity',
      // 'carts.price',
      // 'carts.total_price',
      'carts.code as carts_code',
      'carts.item_code',
      'gi.*',
      'gm.title as games_title',
      'gm.code as game_code',
      'gm.img as img',
      'gm.id as stock'
    ];
    $resource = DB::table('carts')
      ->select($select)
      ->leftJoin('games_item as gi',  'gi.code', '=', 'carts.item_code')
      ->leftJoin('games as gm', 'gm.code', '=', 'gi.game_code')
      ->where('carts.users_code', '=', $users_code)
      ->get();
    $cartsList = CartResource::collection($resource);

    $total = 0;
    $allData = [];
    foreach ($cartsList as $value) {
      $stock = DB::table('games_item_voucher')
        ->where('games_item_code', $value->code)
        ->where('is_delete', 0)
        ->where('voucher_status', 1)
        ->count();

      if ($stock > 0) {
        $value->stock = $stock;

        $memberType = Auth::user()->memberType;
        $price = $value->price;
        if ($memberType == 2) {
            $price = $value->price_reseller;
        }

        $total += $price * $value->quantity;
        array_push($allData, $value);
      } else {
        DB::table('carts')->where('code', $value->carts_code)->limit(1)->delete();
      }
    }
    return ['data' => $allData, 'total' => $total];
  }

  public function getCartNotMember($request)
  {
    $cart = $request->cart;

    $all_code = [];
    $all_qty = [];
    $total = 0;
    if (!empty($cart)) {
      foreach ($cart as $value) {
        $code = $value['code']; // item_code
        $all_qty[$value['code']] = $value['quantity'];
        array_push($all_code, $code);
      }

      $select = [
        'games_item.*',
        'gm.title as games_title',
        'gm.code as game_code',
        'gm.img as img'
      ];
      $cartsList = DB::table('games_item')
        ->select($select)
        ->leftJoin('games as gm', 'gm.code', '=', 'games_item.game_code')
        ->whereIn('games_item.code', $all_code)
        ->where('isActive', 1)
        ->get();
      // $cartsList = CartResource::collection($resource);

      $allData = [];
      foreach ($cartsList as $key => $value) {
        $value->quantity = $all_qty[$value->code];
        $value->price = $value->price_not_member;
        $value->total_price = $value->price_not_member * $value->quantity;
        $value->title = $value->games_title . ' ' . $value->title;

        if (filter_var($value->img, FILTER_VALIDATE_URL)) {
          $pict = $value->img;
        } else {
          $pict = getImage($value->img);
        }
        $value->img = $pict;

        $stock = DB::table('games_item_voucher')
          ->where('games_item_code', $value->code)
          ->where('is_delete', 0)
          ->where('voucher_status', 1)
          ->count();

        $value->stock = $stock;

        if ($stock > 0) {
          $total += $value->total_price;

          unset($value->price_not_member);
          unset($value->price_reseller);
          unset($value->price_unipin);
          unset($value->from);
          array_push($allData, $value);
        }
      }

      return ['data' => $allData, 'total' => $total];
    }

    return ['data' => [], 'total' => 0];
  }
}
