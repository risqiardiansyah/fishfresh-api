<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use App\Helpers\Unipin;
use App\Helpers\Apigames;
use App\Helpers\Digiflazz;
use App\Helpers\Duitku;
use App\Helpers\Midtrans;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\RedeemCode;
use Illuminate\Database\QueryException;

class OrderRepository
{
  public function allOrder($request)
  {
    $email = $request->email;

    $data = DB::table('transaction');
    if (!empty($email)) {
      $data = $data->where('email', $email);
    }
    if (isset(Auth::user()->users_code)) {
      $users_code = Auth::user()->users_code;
      $data = $data->where('users_code', $users_code);
    }
    $data = $data->get();
    for ($i = 0; $i < count($data); $i++) {
      $select = [
        'transaction_detail.*',
        'gi.title as item_title',
        'gi.game_code',
        'gi.from'
      ];
      $detail = DB::table('transaction_detail')
        ->select($select)
        ->leftJoin('games_item as gi', 'transaction_detail.item_code', '=', 'gi.code')
        ->where('transaction_code', $data[$i]->transaction_code)
        ->get();
      $data[$i]->detail = $detail;

      $pm = DB::table('payment_method')->where('pm_code', $data[$i]->payment_method)->first();
      if (!empty($pm)) {
        $data[$i]->payment_method = $pm->pm_title;
      }
    }

    return $data;
  }

  public function detailOrder($request, $order_code)
  {
    $email = $request->email;

    $data = DB::table('transaction')->where('transaction_code', $order_code);
    // if (!empty($email)) {
    //   $data = $data->where('email', $email);
    // }
    $users = isset(Auth::user()->users_code);
    if ($users) {
      $users_code = Auth::user()->users_code;
      $data = $data->where('users_code', $users_code);
    }
    // if (str_contains($request->from, 'buy')) {
    //   $data = $data->where('type', 'order_item');
    // } else if((str_contains($request->from, 'topup'))) {
    //   $data = $data->where('type', 'topup');
    // }
    
    $data = $data->first();
    if (!empty($data)) {
      $data->from = '';
      $data->game_transaction_message = '';
      $select = [
        'transaction_detail.*',
        // 'gi.game_code',
        'g.*'
      ];
      $detail = DB::table('transaction_detail')
        ->select($select)
        // ->leftJoin('games_item as gi', 'transaction_detail.item_code', '=', 'gi.code')
        ->leftJoin('games as g', 'transaction_detail.game_code', '=', 'g.code')
        ->where('transaction_code', $order_code)
        ->groupBy('item_code')
        ->get();
      foreach ($detail as $value) {
        $value->from = '';
        $value->redeem_code = '';
      }
      $data->detail = $detail;

      $pm = DB::table('payment_method')->where('pm_code', $data->payment_method)->first();
      if (!empty($pm)) {
        $data->payment_method = $pm->pm_title;
      }

      $f = [];
      $tgameid = DB::table('transaction_game_id')
          ->where('transaction_code', $order_code)
          ->where('game_code', $data->detail[0]->code ?? '')
          ->get([
            'transaction_game_id.fields_name as name',
            'transaction_game_id.value',
            'transaction_game_id.value as userid'
          ]);
      if (isset($data->detail[0]) && !empty($tgameid)) {
        $newObj = (object) [
          'display_name' => 'username',
          'name' => 'username',
          'type' => 'string',
          'username' => $data->detail[0]->username,
          'value' => $data->detail[0]->username,
        ];

        array_push($f, $newObj);
      }
      foreach ($tgameid as $value) {
        $fl = DB::table('fields')
          ->where('game_code', $data->detail[0]->code ?? '')
          ->where('name', $value->name)
          ->first();
        $df = (object)[
          'display_name' => $fl->display_name ?? '',
          'name' => $value->name,
          'type' => $fl->type,
          'userid' => $value->value,
          'value' => $value->value,
        ];

        array_push($f, $df);
      }
      $data->fields = $f;

      if (isset($data->detail[0]) && empty($f)) {
        $fields = DB::table('fields')->select('name', 'type', 'display_name')->where('game_code', $data->detail[0]->code)->get();
        $hasDropdown = false;
        $newObj = (object) [
          'display_name' => 'username',
          'name' => 'username',
          'type' => 'string',
          'username' => $data->detail[0]->username,
          'value' => $data->detail[0]->username,
        ];

        $count = 1;
        foreach ($fields as $key => $value) {
          $userServer = $data->detail[0]->userid;

          if (isset($data->detail[0]->userid)) {
            if ($userServer[0] == '-') {
              $userServer = ltrim($userServer, '-');
            }
            $parts = explode('-', $userServer);
            $userId = $parts[0];
            $serverId = $parts[1] ?? '';

            if (count($parts) >= 3) {
              $username = $parts[count($parts) - 3];
            }

            $dropdown = DB::table('fields_data')->where('game_code', $data->detail[0]->code)->get();
            $dd = false;
            foreach ($dropdown as $drop) {
              if (isset($parts[$key]) && $parts[$key] == $drop->value && $value->type == 'dropdown') {
                $value->serverid = $drop->value;
                $value->server = $drop->name;
                $value->value = $drop->name;

                $dd = true;
                continue;
              }
              // if ($userId == $drop->value && $value->type == 'dropdown') {
              //   $value->serverid = $drop->value;
              //   $value->server = $drop->name;
              //   $value->value = $drop->name;

              //   $dd = true;
              //   continue;
              // }
              // if ($serverId == $drop->value && $value->type == 'dropdown') {
              //   $value->serverid = $drop->value;
              //   $value->server = $drop->name;
              //   $value->value = $drop->name;

              //   $dd = true;
              //   continue;
              // }
            }

            if ($dd) {
              continue;
            }

            if ($value->name == 'userid' || $value->name == 'user_id') {
              $value->userid = $userId;
              $value->value = $userId;
            } elseif ($value->name == 'username') {
              $value->username = $username;
              $value->value = $username;
            }
            // elseif ($value->type == 'dropdown') {
            //   $hasDropdown = true;
            // }
            else {
              $value->otherid = $serverId;
              $value->other = $serverId;

              // if ($count == 1) {
                $value->value = $parts[$key] ?? '';
              // } else {
              //   $value->value = $serverId ? $serverId : $userId;
              // }
            }
            $count++;
          }
        }

        // if ($hasDropdown) {
          // if (count($fields) == 2) {
            $fields[] = $newObj;
          // }
          $data->fields = $fields;
        // }
      }
    }

    $games = DB::table('games')
      ->where('code', $data->detail[0]->code ?? '')
      ->first();
    $tdid = DB::table('transaction_game_id')
      ->where('game_code', $data->detail[0]->code ?? '')
      ->where('transaction_code', $order_code)
      ->first();

    if (empty($tdid) && !empty($games)) {
      $fields = DB::table('fields')
        ->where('game_code', $games->code ?? '')
        ->first(['name', 'display_name']);
      $newObj = (object) [
        'display_name' => $fields->display_name, 
        'name' => 'userid', 
        'type' => 'string',
        'userid' => $fields->name ?? '',
        'value' => $data->detail[0]->userid ?? '',
      ];

      array_push($data->fields, $newObj); 
    }

    unset($data->users_code);
    unset($data->game_transaction_number);
    unset($data->remaining_balance);
    return $data;
  }

  public function orderAllCart($request)
  {
    DB::beginTransaction();
    $client = new Client();
    try {
      $email = $request->email;
      $pm_code = $request->pm_code;
      $voucher_code = $request->voucher_code;
      $users = auth('sanctum')->user();
      $users_code = $users->users_code;
      $carts = DB::table('carts')->where('users_code', $users_code)->get();

      if ($carts->isEmpty()) {
        return ['success' => false, 'message' => 'Keranjang masih kosong!'];
      }

      $outofstock = '';
      foreach ($carts as $value) {
        $onstok = DB::table('games_item_voucher')
            ->where('games_item_code', $value->item_code)
            ->where('voucher_status', 1)
            ->where('is_delete', 0)
            ->count();
        $onhold = getStockHold($value->item_code);
        
        $readystok = $onstok - $onhold;
        if ($value->quantity > $readystok) {
          $gitem = DB::table('games_item')->where('code', $value->item_code)->first();
          if (empty($gitem)) {
            continue;
          }

          $outofstock .= 'Jumlah keranjang pembelian '.$gitem->title.' melebihi stock yang tersedia, sisa stock='.$readystok.' <br>';
        }
      }

      if (!empty($outofstock)) {
        return [
          'success' => false,
          'msg' => $outofstock
        ];
      }

      $discount = 0;
      $paymentAmount = 0;
      $transaction_code = generateOrderCode('VGN');

      $pm = DB::table('payment_method')->where('pm_code', $pm_code)->first();
      if (empty($pm)) {
        return [
          'success' => false,
          'msg' => 'Payment method invalid',
        ];
      }
      $fee = ceil($pm->fee);
      $fee_type = $pm->fee_type;

      if ($fee_type == 'percent') {
        $fee = number_format($pm->fee / 100, 3);
      }
      $fee = (double)$fee;
      
      switch ($pm->from) {
        case 'duitku':
          foreach ($carts as $cart) {
            $game_item = DB::table('games_item')->where('code', $cart->item_code)->first();
            $item_price = $game_item->price;
            if (!is_null($users)) {
              if ($users->memberType == 2) {
                $item_price = $game_item->price_reseller;
              }
            }

            $qty = $cart->quantity;
        
            $total_price = $item_price * $qty;
            // $paymentAmount += round(($item_price_fee * $qty) - $discount);
            $paymentAmount += $total_price;
            $item_price_fee = $fee;

            if ($fee_type == 'percent') {

              $item_price_fee_percent = $paymentAmount * $fee;

              $item_price_fee =  $item_price_fee_percent;
            }
            $item_details[] = [
              'name' => $game_item->title,
              'price' => $item_price,
              'quantity' => $qty,
            ];
            $game = DB::table('games')->where('code', $game_item->game_code)->first();
            $tr_details[] = [
              'detail_code' => generateFiledCode('TRD'),
              'transaction_code' => $transaction_code,
              'game_code' => $game_item->game_code,
              'item_code' => $game_item->code,
              'price' => $item_price,
              'qty' => $cart->quantity,
              'total' => ceil(($item_price * $qty) - $discount),
              'userid' => $request->email,
              'username' => '#',
              'validation_token' => $request->validation_token,
              'game_title' => $game->title ?? '',
              'item_title' => $game_item->title,
            ];
          }

  
          $apiKey = env('DUITKU_APIKEY');
          $merchantCode = env('DUITKU_MERCHANTID');
          $URL = env('APP_URL');
          $merchantOrderId = $transaction_code;
          $paymentAmountNormal = $paymentAmount;
          // $paymentAmount = $paymentAmount - $discount + $fee;
        
          $paymentAmount = ceil(($paymentAmountNormal + $item_price_fee) - $discount);
          if (!empty($voucher_code)) {
            $voucher = checkVoucherRules($voucher_code, $pm_code, $paymentAmount);
            if ($voucher['success']) {
              $discount = $voucher['data']['vouchers_discount'];
              decreaseMaxVoucherUsed($voucher_code);
            }
          } else {
            $voucher_code = '-';
          }

          $body = (object) [
            'paymentMethod' => $pm_code,
            'merchantOrderId' => $transaction_code,
            'merchantCode' => $merchantCode,
            "paymentAmount" => $paymentAmount,
            'productDetails' => $game_item->title,
            "additionalParam" => "",
            "merchantUserInfo" => "",
            "customerVaName" => $users->name,
            "email" => $email,
            "phoneNumber" => $users->no_telp,
            // 'itemDetails' => $item_details,
            "customerDetail" => (object) [
              "firstName" => $users->name,
              "lastName" => $users->name,
              "email" => $email,
              "phone" => $users->no_telp
            ],
            "callbackUrl" => "$URL/api/v1/callback",
            "returnUrl" => "https://vogaon.com/order/voucher/" . $transaction_code,
            "signature" => md5($merchantCode . $merchantOrderId . $paymentAmount . $apiKey),
            "expiryPeriod" => 15
          ];
        
          Log::info('ORDER KE DUITKU ' . json_encode($body));
          saveLog('ORDER KE DUITKU', $transaction_code, $body, 'ORDER KE DUITKU');
          $response = $client->request('POST', 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry', [
            'body' => json_encode($body),
            'headers' => [
              'content-type' => 'application/json',
            ]
          ]);


          $dataResponse = json_decode($response->getBody()->getContents(), true);
          if (isset($dataResponse['paymentUrl'])) {
            $tr_data = [
              'transaction_code' => $transaction_code,
              'users_code' => $users_code,
              'email' => $email,
              'total_amount' => ceil($paymentAmount),
              'subtotal' => $paymentAmountNormal,
              'fee' => ceil($item_price_fee),
              'transaction_url' => $dataResponse['paymentUrl'],
              'from' => $game_item->from,
              'payment_method' => $pm->pm_code,
              // 'no_reference' => $dataResponse['reference'],
              'status' => 'waiting',
              'voucher_discount' => $discount,
              'voucher_code' => $voucher_code,
              'type' => 'order_voucher'
            ];
            // dd($tr_data);
            DB::table('transaction')->insert($tr_data);
            $tr_detail = $tr_details;
            DB::table('transaction_detail')->insert($tr_detail);
            DB::table('carts')->where('users_code', $users_code)->delete();
            DB::commit();
            return [
              'success' => true,
              'data' => (object) [
                'payment_url' => $dataResponse['paymentUrl'],
                'order_id' => $transaction_code,
                'other_data' => $dataResponse
              ]
            ];
          }
          break;
        case 'midtrans':
          $enable_payment = getEnablePayment($pm->pm_code);
          $item_details = [];
          foreach ($carts as $cart) {
            $game_item = DB::table('games_item')->where('code', $cart->item_code)->first();
            $item_price = $game_item->price;
            if (!is_null($users)) {
              if ($users->memberType == 2) {
                $item_price = $game_item->price_reseller;
              }
            }

            $qty = $cart->quantity;


            $total_price = $game_item->price * $qty;
            $paymentAmount += $total_price;


            // $paymentAmount += $item_price_fee;
            $item_details[] = [
              'id' => $game_item->code,
              'name' => $game_item->title,
              'price' => $item_price,
              'quantity' => $qty
            ];
            $item_price_fee = $fee;
            if ($fee_type == 'percent') {
              $item_price_fee_percent = $paymentAmount * $item_price_fee;

              $item_price_fee = $item_price_fee_percent;
            }
            $game = DB::table('games')->where('code', $game_item->game_code)->first();
            $tr_details[] = [
              'detail_code' => generateFiledCode('TRD'),
              'transaction_code' => $transaction_code,
              'game_code' => $game_item->game_code,
              'item_code' => $game_item->code,
              'price' => $item_price,
              'qty' => $qty,
              'total' => ceil(($item_price * $qty) - $discount),
              'userid' => $request->email,
              'username' => '#',
              'validation_token' => $request->validation_token,
              'game_title' => $game->title ?? '',
              'item_title' => $game_item->title,
            ];
          };
        
          if (!empty($voucher_code)) {
            $voucher = checkVoucherRules($voucher_code, $pm_code, $paymentAmount);
            if ($voucher['success']) {
              $discount = $voucher['data']['vouchers_discount'];
              decreaseMaxVoucherUsed($voucher_code);
              $item_details[] = [
                'id' => $voucher_code,
                'name' => 'Voucher discount',
                'price' => -$discount,
                'quantity' => 1
              ];
            }
          } else {
            $voucher_code = '-';
          }
          if ($fee != 0) {
            $item_details[] = [
              'name' => 'Fee',
              'price' => ceil($item_price_fee),
              'quantity' => 1
            ];
          }
        
        
          $paymentAmount = $paymentAmount + $item_price_fee - $discount;
       
          $body = (object) [
            "transaction_details" => (object) [
              "order_id" => $transaction_code,
              "gross_amount" => ceil($paymentAmount)
            ],
            "customer_required" => false,
            "customer_details" => (object) [
              "first_name" => $users->name,
              "last_name" => $users->name,
              "email" => $email,
              "phone" => $users->no_telp
            ],
            "item_details" => $item_details,
            "enabled_payments" => $enable_payment,
            "usage_limit" => 1,
            "expiry" => (object) [
              "duration" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time,
              "unit" => "minutes"
            ],
          ];
          Log::info('ORDER KE MIDTRANS ' . json_encode($body));
          saveLog('ORDER KE MIDTRANS', $transaction_code, $body, 'ORDER KE MIDTRANS');
          $dataResponse = Midtrans::useSnapMidtrans($body);
          if (isset($dataResponse['payment_url'])) {
            $tr_data = [
              'transaction_code' => $transaction_code,
              'users_code' => $users->users_code,
              'email' => $email,
              'total_amount' => ceil($paymentAmount),
              'subtotal' => $total_price,
              'fee' => $item_price_fee,
              'transaction_url' => $dataResponse['payment_url'],
              'from' => $game_item->from,
              'payment_method' => $pm->pm_code,
              // 'no_reference' => $dataResponse['order_id'],
              'status' => 'waiting',
              'voucher_discount' => $discount,
              'voucher_code' => $voucher_code,
              'type' => 'order_voucher'
            ];

            DB::table('transaction')->insert($tr_data);
            $tr_detail = $tr_details;
            DB::table('transaction_detail')->insert($tr_detail);
            DB::table('carts')->where('users_code', $users_code)->delete();
            DB::commit();
            return [
              'success' => true,
              'data' => $dataResponse
            ];
          }
          break;
        case 'saldo':
          foreach ($carts as $cart) {
            $game_item = DB::table('games_item')->where('code', $cart->item_code)->first();

            $item_price = $game_item->price;

            if (!is_null($users)) {
              if ($users->memberType == 2) {
                $item_price = $game_item->price_reseller;
                $fee = 0;
              }
            }
            $qty = $cart->quantity;
            $item_price_fee = $fee;
            $total_price = $item_price * $qty;
            $paymentAmount += $total_price;
            if ($fee_type == 'percent') {
              $item_price_fee_percent = $paymentAmount * $item_price_fee;
              $item_price_fee = $item_price_fee_percent;
            }
         
            $voucher_games_collect = DB::table('games_item_voucher')
              ->where('games_item_code', $cart->item_code)
              ->where('voucher_status', 1)
              ->limit($qty)
              ->get();
            $game = DB::table('games')->where('code', $game_item->game_code)->first();
            $title_games = $game->title;
            $voucher_games_count = $voucher_games_collect->count();

         
            if ($voucher_games_count < $qty) {
              DB::rollBack();
              return [
                'success' => false,
                'msg' => 'Terdapat perubahan pada stok ' . $title_games . ' !',
              ];
            }

            if ($voucher_games_collect) {
              $redeem_codes_item = $voucher_games_collect->pluck('redeem_code')->toArray();

              if ($qty > 1) {
                $redeem_codes[] = implode(',', $redeem_codes_item);
              } else {
                $redeem_codes[] = $redeem_codes_item[0];
              }

              DB::table('games_item_voucher')
                ->where('games_item_code', $cart->item_code)
                ->whereIn('redeem_code', $redeem_codes_item)
                ->update(['voucher_status' => 0]);
            }

            if (!empty($voucher_code)) {
              $voucher = checkVoucherRules($voucher_code, $pm_code, $paymentAmount);
              if ($voucher['success']) {
                $discount = $voucher['data']['vouchers_discount'];
                decreaseMaxVoucherUsed($voucher_code);
              }
            } else {
              $voucher_code = '-';
            }
     
         
            $tr_details[] = [
              'detail_code' => generateFiledCode('TRD'),
              'transaction_code' => $transaction_code,
              'game_code' => $game_item->game_code,
              'item_code' => $game_item->code,
              'price' => $item_price,
              'qty' => $qty,
              'total' => ceil($paymentAmount),
              'userid' => $request->email,
              'username' => '#',
              'validation_token' => $request->validation_token,
              'redeem_code' => implode(',', $redeem_codes_item),
              'game_title' => $game->title ?? '',
              'item_title' => $game_item->title,
            ];


            $redeem_codes = implode(',', $redeem_codes_item);
            if ($redeem_codes && strpos($redeem_codes, ',') !== false) {
              $redeem_codes_individual = explode(',', $redeem_codes);
              $redeem_codes = $redeem_codes_individual;
            } else {
              $redeem_codes = [$redeem_codes];
            }


            $redeem_codes_all[] = [
              'game' => $game->title,
              'title' => $game_item->title,
              'redeem_codes' => $redeem_codes
            ];
          }

          $paymentAmount = ceil(($paymentAmount + $item_price_fee) - $discount);
          
          $cek = checkBalanceUser($users_code, ($paymentAmount));

          if (!$cek['success']) {
            DB::rollBack();
            return [
              'success' => false,
              'msg' => 'Saldo Tidak Cukup !',
            ];
          }
          $url = env('EMAIL_DOMAIN');
          $mail_data = [
            'name' => $users->name,
            'email' => $users->email,
            'redeem_codes' => $redeem_codes_all,
            'order_id' => $transaction_code,
            'url' => $url
          ];

          $tr_data = [
            'transaction_code' => $transaction_code,
            'users_code' => $users->users_code,
            'email' => $email,
            'total_amount' => ceil($paymentAmount),
            'subtotal' => $paymentAmount,
            'fee' => $item_price_fee,
            'transaction_url' => '#',
            'from' => $game_item->from,
            'payment_method' => $pm->pm_code,
            // 'no_reference' => $transaction_code,
            'status' => 'success',
            'voucher_discount' => $discount,
            'voucher_code' => $voucher_code,
            'type' => 'order_voucher',
            'game_transaction_status' => 1,
            'remaining_balance' => $cek['data']->users_balance - ($paymentAmount - $discount)
          ];
          saveLog('ORDER SALDO', $transaction_code, $tr_data, 'TR DATA');
          DB::table('transaction')->insert($tr_data);

          $dec_data = [
            'transaction_code' => generateOrderCode('VGN'),
            'users_code' => $users_code,
            'email' => $email,
            'total_amount' => ceil(-$paymentAmount),
            'subtotal' => ceil(-$total_price),
            'fee' => 0,
            'transaction_url' => '#',
            'from' => $game_item->from,
            'payment_method' => $pm->pm_code,
            // 'no_reference' => $transaction_code,
            'status' => 'success',
            'voucher_discount' => $discount,
            'voucher_code' => '-',
            'type' => 'topup',
            'game_transaction_status' => 1,
            'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
            'remaining_balance' => $cek['data']->users_balance - ($paymentAmount - $discount)
          ];
          saveLog('ORDER SALDO', $transaction_code, $dec_data, 'dec_data');
          DB::table('transaction')->insert($dec_data);
          saveLog('ORDER SALDO', $transaction_code, $dec_data, 'tr_details');
          DB::table('transaction_detail')->insert($tr_details);

          DB::table('carts')->where('users_code', $users_code)->delete();

          DB::table('users_balance')->where('users_balance_code', $cek['data']->users_balance_code)->update(['users_balance' => ($cek['data']->users_balance - $paymentAmount)]);

          DB::commit();

          Mail::to($email)->send(new RedeemCode($mail_data));
          return [
            'success' => true,
            'data' => (object) [
              'order_id' => $transaction_code,
              'other_data' => (object) $tr_data
            ]
          ];
        default:
          return ['success' => false, 'msg' => 'Invalid payment method'];
      }
    } catch (QueryException $e) {
      DB::rollBack();
      $errorCode = $e->errorInfo[1];
      if($errorCode == 1062){
        // a duplicate entry problem, try again
        // Log::alert('DUPLICATE');
        return $this->orderAllCart($request);
      } else {
        Log::alert($errorCode);
      }

      return ['success' => false, 'message' => $e->getMessage()];
    } catch (\Exception $e) {
      DB::rollBack();
      return ['success' => false, 'message' => $e->getMessage()];
    }
    return $carts;
  }

  public function orderAllCartNonMember($request)
  {
    DB::beginTransaction();
    $client = new Client();
    try {
      $email = $request->email;
      $pm_code = $request->pm_code;
      $voucher_code = $request->voucher_code;

      $carts = $request->carts;
      if (empty($carts)) {
        return ['success' => false, 'message' => 'Keranjang masih kosong!'];
      }

      $outofstock = '';
      foreach ($carts as $value) {
        if ($value['quantity'] < 1) {
          DB::rollBack();
          return [
            'success' => false,
            'msg' => 'Stok tidak valid !'
          ];
        }
        $onstok = DB::table('games_item_voucher')
            ->where('games_item_code', $value['code'])
            ->where('voucher_status', 1)
            ->where('is_delete', 0)
            ->count();
        $onhold = getStockHold($value['code']);
        
        $readystok = $onstok - $onhold;
        if ($value['quantity'] > $readystok) {
          $gitem = DB::table('games_item')->where('code', $value['code'])->first();
          if (empty($gitem)) {
            continue;
          }

          $outofstock .= 'Jumlah keranjang pembelian '.$gitem->title.' melebihi stock yang tersedia, sisa stock='.$readystok.' <br>';
        }
      }

      if (!empty($outofstock)) {
        return [
          'success' => false,
          'msg' => $outofstock
        ];
      }

      $discount = 0;
      $paymentAmount = 0;
      $transaction_code = generateOrderCode('VGN');

      $pm = DB::table('payment_method')->where('pm_code', $pm_code)->first();
      if (empty($pm)) {
        return [
          'success' => false,
          'msg' => 'Payment method invalid',
        ];
      }
      $fee = ceil($pm->fee);
      $fee_type = $pm->fee_type;

      if ($fee_type == 'percent') {
        $fee = $pm->fee / 100;
      }
      
      switch ($pm->from) {
        case 'duitku':
          foreach ($carts as $cart) {
            $game_item = DB::table('games_item')->where('code', $cart['code'])->first();
            if (empty($game_item)) {
              continue;
            }
            if (!$game_item->isActive) {
              continue;
            }

            $game_item->price = $game_item->price_not_member;
            // $item_price_fee = $game_item->price + $fee;
            // if ($fee_type == 'percent') {
            //   $item_price_fee_percent = $game_item->price * $fee;
            //   $item_price_fee =
            //     $item_price_fee + $item_price_fee_percent;
            // }
             // set total payment amount 
            $item_price = $game_item->price_not_member;
            $qty = $cart['quantity'];
            $total_price = $item_price * $qty;
            // $paymentAmount += $total_price;

            // $total_price =
            //   $item_price_fee * $cart['quantity'];
            $item_details[] = [
              'name' => $game_item->title,
              // 'price' => $total_price,
              'price' => $item_price,
              'quantity' => $cart['quantity'],
            ];
            $paymentAmount += $total_price;
            $item_price_fee = $fee;
            // change fee percent type
            if ($fee_type == 'percent') {
              $item_price_fee_percent = $paymentAmount * $fee;
              $item_price_fee =  $item_price_fee_percent;
            }
            $game = DB::table('games')->where('code', $game_item->game_code)->first();
            $tr_details[] = [
              'detail_code' => generateFiledCode('TRD'),
              'transaction_code' => $transaction_code,
              'game_code' => $game_item->game_code,
              'item_code' => $game_item->code,
              'price' => $game_item->price,
              'qty' => $cart['quantity'],
              // 'total' =>  $item_price_fee * $cart['quantity'],
              'total' =>  ceil($game_item->price * $cart['quantity']),
              'userid' => $request->email,
              'game_title' => $game->title ?? '',
              'item_title' => $game_item->title,
            ];
          }

          // set payment amount
          $merchantOrderId = $transaction_code;
          $paymentAmountNormal = $paymentAmount;
          $paymentAmount = ceil(($paymentAmountNormal + $item_price_fee) - $discount);

          if (!empty($voucher_code)) {
            $voucher = checkVoucherRules($voucher_code, $pm_code, $paymentAmount);
            if ($voucher['success']) {
              $discount = $voucher['data']['vouchers_discount'];
              decreaseMaxVoucherUsed($voucher_code);

              $item_details[] = [
                'name' => 'Voucher',
                'price' => -$discount,
                'quantity' => 1
              ];
            }
          } else {
            $voucher_code = '-';
          }
          // $qty = $cart->quantity;
          $qty = $cart['quantity'];
          $apiKey = env('DUITKU_APIKEY');
          $merchantCode = env('DUITKU_MERCHANTID');
          $URL = env('APP_URL');
          $merchantOrderId = $transaction_code;
          $body = (object) [
            'paymentMethod' => $pm_code,
            'merchantOrderId' => $transaction_code,
            'merchantCode' => $merchantCode,
            // "paymentAmount" => ($item_price_fee * $qty) - $discount,
            "paymentAmount" => $paymentAmount,
            'productDetails' => $game_item->title,
            "additionalParam" => "",
            "merchantUserInfo" => "",
            "customerVaName" => $email,
            "email" => $email,
            "phoneNumber" => 0,
            // 'itemDetails' => $item_details,
            "customerDetail" => (object) [
              "firstName" => $email,
              "lastName" => $email,
              "email" => $email,
              "phone" => 0
            ],
            "callbackUrl" => "$URL/api/v1/callback",
            "returnUrl" => "https://vogaon.com/order/voucher/" . $transaction_code,
            "signature" => md5($merchantCode . $merchantOrderId . $paymentAmount . $apiKey),
            "expiryPeriod" => 15
          ];
          // dd($body);
          $response = $client->request('POST', 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry', [
            'body' => json_encode($body),
            'headers' => [
              'content-type' => 'application/json',
            ]
          ]);
          $dataResponse = json_decode($response->getBody()->getContents(), true);
          if (isset($dataResponse['paymentUrl'])) {
            $tr_data = [
              'transaction_code' => $transaction_code,
              'users_code' => $email,
              'email' => $email,
              'total_amount' => $paymentAmount,
              'subtotal' => $paymentAmount,
              // 'fee' => $fee,
              'fee' => $item_price_fee,
              'transaction_url' => $dataResponse['paymentUrl'],
              'from' => $game_item->from,
              'payment_method' => $pm->pm_code,
              // 'no_reference' => $dataResponse['reference'],
              'status' => 'waiting',
              'voucher_discount' => $discount,
              'voucher_code' => $voucher_code,
              'type' => 'order_voucher'
            ];
            DB::table('transaction')->insert($tr_data);
            $tr_detail = $tr_details;
            DB::table('transaction_detail')->insert($tr_detail);
            DB::commit();
            return [
              'success' => true,
              'data' => (object) [
                'payment_url' => $dataResponse['paymentUrl'],
                'order_id' => $transaction_code,
                'other_data' => $dataResponse
              ]
            ];
          }
          break;
        case 'midtrans':
          $enable_payment = getEnablePayment($pm->pm_code);

          $item_details = [];
          foreach ($carts as $cart) {
            $game_item = DB::table('games_item')->where('code', $cart['code'])->first();
            if (empty($game_item)) {
              continue;
            }
            if (!$game_item->isActive) {
              continue;
            }
            // set total payment amount
            $item_price = $game_item->price_not_member;
            $qty = $cart['quantity'];
            $total_price = $item_price * $qty;
            $paymentAmount += $total_price;

            $game_item->price = $game_item->price_not_member;
            $item_price_fee = $game_item->price_not_member + $fee;
            if ($fee_type == 'percent') {
              $item_price_fee_percent = $game_item->price_not_member * $fee;
              $item_price_fee =
                $item_price_fee + $item_price_fee_percent;
            }
            // $item_price_fee = $game_item->price + $fee;
            $total_price = $game_item->price * $cart['quantity'];
            $item_details[] = [
              'id' => $game_item->code,
              'name' => $game_item->title,
              // 'price' => $item_price_fee,
              'price' => $item_price,
              'quantity' => $cart['quantity']
            ];
            // $paymentAmount += $total_price;
            $item_price_fee = $fee;
            // change fee percent type
            if ($fee_type == 'percent') {
              $item_price_fee_percent = $paymentAmount * $item_price_fee;

              $item_price_fee = $item_price_fee_percent;
            }
            $game = DB::table('games')->where('code', $game_item->game_code)->first();
            $tr_details[] = [
              'detail_code' => generateFiledCode('TRD'),
              'transaction_code' => $transaction_code,
              'game_code' => $game_item->game_code,
              'item_code' => $game_item->code,
              'price' => $game_item->price,
              'qty' => $cart['quantity'],
              'total' => $item_price_fee * $cart['quantity'],
              'total' => ceil($game_item->price * $cart['quantity']),
              'userid' => $request->email,
              'game_title' => $game->title ?? '',
              'item_title' => $game_item->title,
            ];
          }

          if (!empty($voucher_code)) {
            $voucher = checkVoucherRules($voucher_code, $pm_code, $paymentAmount);
            if ($voucher['success']) {
              $discount = $voucher['data']['vouchers_discount'];
              decreaseMaxVoucherUsed($voucher_code);

              $item_details[] = [
                'id' => $voucher_code,
                'name' => 'Voucher Discount',
                'price' => -$discount,
                'quantity' => 1
              ];
            }
          } else {
            $voucher_code = '-';
          }

          if ($fee != 0) {
            $item_details[] = [
              'name' => 'Fee',
              // 'price' => $fee,
              'price' => ceil($item_price_fee), // add fee
              'quantity' => 1
            ];
          }

          // set total payment amount
          $paymentAmount = $paymentAmount + $item_price_fee - $discount;
          $body = (object) [
            "transaction_details" => (object) [
              "order_id" => $transaction_code,
              // "gross_amount" => ($item_price_fee * $cart['quantity']) - $discount
              "gross_amount" => ceil($paymentAmount)
            ],
            "customer_required" => false,
            "customer_details" => (object) [
              // "first_name" => $email,
              // "last_name" => $email,
              "email" => $email,
              // "phone" => "12345"
            ],
            "item_details" => $item_details,
            "enabled_payments" => $enable_payment,
            "usage_limit" => 1,
            "expiry" => (object) [
              "duration" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time,
              "unit" => "minutes"
            ],
          ];

          // return ['success' => false, 'msg' => $body];
          // $response = $client->request('POST', 'https://api.sandbox.midtrans.com/v1/payment-links', [
          //     'body' => json_encode($body),
          //     'headers' => [
          //         'accept' => 'application/json',
          //         'authorization' => 'Basic ' . env('MIDTRANS_KEY'),
          //         'content-type' => 'application/json',
          //     ]
          // ]);
          // $dataResponse = json_decode($response->getBody()->getContents(), true);

          $dataResponse = Midtrans::useSnapMidtrans($body);
          if (isset($dataResponse['payment_url'])) {
            $tr_data = [
              'transaction_code' => $transaction_code,
              'users_code' => $email,
              'email' => $email,
              // 'total_amount' => ($item_price_fee * $cart['quantity']) - $discount,
              'total_amount' => ceil($paymentAmount),
              'subtotal' => $paymentAmount,
              // 'fee' => $fee,
              'fee' => $item_price_fee,
              'transaction_url' => $dataResponse['payment_url'],
              'from' => $game_item->from,
              'payment_method' => $pm->pm_code,
              // 'no_reference' => $dataResponse['order_id'],
              'status' => 'waiting',
              'voucher_discount' => $discount,
              'voucher_code' => $voucher_code,
              'type' => 'order_voucher'
            ];
            DB::table('transaction')->insert($tr_data);
            $tr_detail = $tr_details;
            DB::table('transaction_detail')->insert($tr_detail);
            DB::commit();

            return [
              'success' => true,
              'data' => $dataResponse
            ];
          }
          break;

        default:
          return ['success' => false, 'msg' => 'Invalid payment method'];
      }
    } catch (QueryException $e) {
      DB::rollBack();
      $errorCode = $e->errorInfo[1];
      if($errorCode == 1062){
        // a duplicate entry problem, try again
        // Log::alert('DUPLICATE');
        return $this->orderAllCartNonMember($request);
      } else {
        Log::alert($errorCode);
      }

      return ['success' => false, 'message' => $e->getMessage()];
    } catch (\Exception $e) {
      DB::rollBack();
      return ['success' => false, 'message' => $e->getMessage()];
    }
    return $carts;
  }

  public function statusFromGameVendor($request)
  {
    $order_id = $request->order_id;
    $transaction = DB::table('transaction')->where('transaction_code', $order_id)->first();
    $transaction_detail = DB::table('transaction_detail')->where('transaction_code', $order_id)->first();
    $vendor = $transaction->from;
    if ($vendor == 'unipin') {
      $result = Unipin::orderInquiry($order_id);
      return $result;
    } else if ($vendor == 'apigames') {
      $result = Apigames::checkStatus($order_id);
      return $result;
    } else if ($vendor == 'digiflazz') {
      $game_item = DB::table('games_item')->where('code', $transaction_detail->item_code)->first();
      $digi_code = $game_item->digi_code;
      $user_id = $transaction_detail->userid;
      $result = Digiflazz::order($order_id, $digi_code, $user_id);
      return $result;
    }
  }

  public function statusPaymentGetUpdates($request)
  {
    try {
      $transactions = DB::table('transaction')
        ->whereNotIn('status', ['success', 'expired'])
        ->get();
      if ($transactions->isEmpty()) {
        return ['success' => true, 'data' => (object) ['message' => 'Status pembayaran sudah terbaru!']];
      }
      foreach ($transactions as $transaction) {
        $payment = DB::table('payment_method')->where('pm_code', $transaction->payment_method)->first();
        $order_id = $transaction->transaction_code;
        switch ($payment->from) {
          case 'midtrans':
            $result = Midtrans::getTransactionStatus($order_id);
            if ($result['status_code'] == 404) {
              Log::info('Transaksi dari MIDTRANS tidak ditemukan ORDER ID: ' . $order_id);
              break;
            }
            $status = $result['transaction_status'];
            $status = $this->filterStatusTransaction($status);
            DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
            break;
          case 'duitku':
            $result = Duitku::getTransactionStatus($order_id);
            if (!empty($result['error'])) {
              Log::info('Transaksi dari duitku tidak ditemukan: ' . $order_id);
              break;
            }
            $statusMessage = $result['statusMessage'];
            $status = $this->filterStatusTransaction($statusMessage);
            DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
            break;
          default:
            break;
        }
      }
      return ['success' => true, 'data' => (object) ['message' => 'Status pembayaran berhasil diperbarui!']];
    } catch (\Exception $e) {
      return ['success' => false, 'data' => $e->getMessage()];
    }
  }

  private function filterStatusTransaction($status)
  {
    switch ($status) {
      case 'settlement':
        return 'success';
      case 'capture':
        return 'success';
      case 'pending':
        return 'waiting';
      case 'cancel':
        return 'cancel';
      case 'failure':
        return 'failed';
      case 'deny':
        return 'failed';
      case 'expire':
        return 'expired';
      case 'SUCCESS':
        return 'success';
      case 'EXPIRED':
        return 'expired';
      case 'PROCESS':
        return 'processing';
      case 'FAILED':
        return 'failed';
      default:
        return '';
    }
  }

  public function statusPaymentCheck($request)
  {
    try {
      $transaction = DB::table('transaction')
        ->where('transaction_code', $request->order_id)
        ->first();
      $payment = DB::table('payment_method')->where('pm_code', $transaction->payment_method)->first();
      $order_id = $transaction->transaction_code;
      switch ($payment->from) {
        case 'midtrans':
          $result = Midtrans::getTransactionStatus($order_id);
          $status = $this->filterStatusTransaction($result['transaction_status']);
          if ($result['status_code'] == 404) {
            Log::info('Transaksi dari MIDTRANS tidak ditemukan ORDER ID: ' . $order_id);
            return ['success' => false, 'status payment' => 'Not found', 'data' => $result];
          }
          return ['success' => true, 'status payment' => $status, 'data' => $result];
        case 'duitku':
          $result = Duitku::getTransactionStatus($order_id);
          if (!empty($result['error'])) {
            Log::info('Transaksi dari duitku tidak ditemukan: ' . $order_id);
            return ['success' => false, 'status payment' => 'Not found', 'data' => $result];
          }
          $status = $this->filterStatusTransaction($result['statusMessage']);
          return ['success' => true, 'status payment' => $status, 'data' => $result];
      }
    } catch (\Exception $e) {
      return ['success' => false, 'data' => $e->getMessage()];
    }
  }
}
