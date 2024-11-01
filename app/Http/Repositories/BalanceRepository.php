<?php

namespace App\Http\Repositories;

use App\Helpers\Apigames;
use App\Helpers\Digiflazz;
use App\Helpers\LapakGaming;
use App\Helpers\Unipin;
use App\Helpers\Midtrans;
use App\Http\Resources\BalanceResource;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\RedeemCode;
use App\Mail\SuccessOrderItem;
use App\Mail\SuccessTopUp;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class BalanceRepository
{
  public function getBalance($users_code)
  {
    $dataBalance = DB::table('users_balance')->where('users_code', $users_code)->first();
    $result = (collect($dataBalance)->count()) ? new BalanceResource($dataBalance) : false;
    return $result;
  }

  public function listBank($request)
  {
    $on = $request->on;
    $total_amount = $request->total_amount;
    $user = auth('api')->user();
    $data = DB::table('payment_method');
    if (empty($user) || $on == 'saldo') {
      $data = $data->where('pm_code', '!=', 'saldo');
    }

    $data = $data->orderBy('position', 'ASC')->get();

    foreach ($data as $value) {
      $value->pm_logo_ori = $value->pm_logo;
      $value->fee_detail = $value->fee;
      if (filter_var($value->pm_logo, FILTER_VALIDATE_URL)) {
        $logo = $value->pm_logo;
      } else {
        // $logo = ($value->pm_logo == null ? asset('storage/bank/default_bank.png') : asset('storage/bank/' . $value->pm_logo));
        $logo = getImage($value->pm_logo);
      }
      $value->pm_logo = $logo;

      if ($total_amount > 0 && $value->min_order > $total_amount) {
        $value->status = 0;
      }

      if ($value->max_order != 0 && $total_amount > 0 && $value->max_order < $total_amount) {
        $value->status = 0;
      }

      if (isset($user->memberType) && $user->memberType == 2 && $value->from != 'saldo' && $on != 'saldo') {
        $value->status = 0;
      }

      if ($value->fee_type == 'percent' && $on != 'saldo') {
        $fee = $total_amount * ($value->fee / 100);
        $value->fee = $fee;
      }

      if ($on == 'saldo') {
        if ($value->fee_type_saldo == 'percent') {
          $fee = $total_amount * ($value->fee_saldo / 100);
          $value->fee = $fee;
        } elseif ($value->fee_type_saldo == 'nominal') {
          $value->fee = $value->fee_saldo;
        }
      }

      if (isset($user->memberType) && $user->memberType == 2 && $value->from == 'saldo' && $on != 'saldo') {
        $value->fee = 0;
        $value->fee_detail = $value->fee;
      }

      unset($value->from);
    }

    return $data;
  }

  // public function payTransaction($request)
  // {
  //   DB::beginTransaction();
  //   try {
  //     $pm_code = $request->pm_code; // Payment method
  //     $users_code = '';
  //     $pm = DB::table('payment_method')->where('pm_code', $pm_code)->first();
  //     // dd($pm);

  //     if (empty($pm)) {
  //       return [
  //         'success' => false,
  //         'msg' => 'Payment method invalid',
  //       ];
  //     }

  //     $client = new Client();
  //     $transaction_code = generateOrderCode('VGN');
  //     $game_item = DB::table('games_item')->where('code', $request->item_code)->first();
  //     if (empty($game_item)) {
  //       return [
  //         'success' => false,
  //         'msg' => 'Game selected not valid',
  //       ];
  //     }

  //     $user_detail = (object) [
  //       'name' => $request->email,
  //       'email' => $request->email,
  //       'phone' => 0
  //     ];

  //     $users = auth('sanctum')->user();

  //     if (!$users) {
  //       $game_item->price = $game_item->price_not_member;
  //     }

  //     if (!is_null($users)) {
  //       $users_code = $users->users_code;
  //       if ($users->memberType == 2) {
  //         $game_item->price = $game_item->price_reseller;
  //       }

  //       $user = DB::table('users')->where('users_code', $users_code)->first();
  //       if (!empty($user)) {
  //         $user_detail->name = $user->name;
  //         $user_detail->email = $user->email;
  //         $user_detail->phone = $user->no_telp;
  //       }
  //     }

  //     $voucher_code = $request->voucher_code;
  //     $discount = 0;
  //     if (!empty($voucher_code)) {
  //       $voucher = checkVoucherRules($voucher_code, $pm_code, $game_item->price);
  //       if ($voucher['success']) {
  //         $discount = $voucher['data']['vouchers_discount'];
  //         decreaseMaxVoucherUsed($voucher_code);
  //       }
  //     } else {
  //       $voucher_code = '-';
  //     }

  //     $fee = $pm->fee;
  //     if ($pm->fee_type == 'percent') {
  //       $fee = ($game_item->price - $discount) * ($pm->fee / 100);
  //     }
  //     $fee = ceil($fee);



  //     if ($pm->from == 'midtrans') {
  //       $URL = env('APP_URL');
  //       $enable_payment = getEnablePayment($pm->pm_code);

  //       if (!is_null($users)) {
  //         $body = (object) [
  //           "transaction_details" => (object) [
  //             "order_id" => $transaction_code,
  //             "gross_amount" => $game_item->price + $fee - $discount
  //           ],
  //           "customer_required" => false,
  //           "customer_details" => (object) [
  //             "first_name" => $user_detail->name,
  //             "last_name" => $user_detail->name,
  //             "email" => $request->email,
  //             "phone" => $user_detail->phone
  //           ],
  //           "enabled_payments" => $enable_payment,
  //           'item_details' => [
  //             [
  //               "id" => $transaction_code,
  //               "name" => $game_item->title,
  //               "price" => $game_item->price,
  //               "quantity" => 1,
  //             ],
  //             [
  //               "id" => $voucher_code,
  //               "name" => "Voucher discount",
  //               "price" => -$discount,
  //               "quantity" => 1,
  //             ],
  //             [
  //               "name" => "Fee",
  //               "price" => $fee,
  //               "quantity" => 1,
  //             ]
  //           ],
  //           "callbackUrl" => "$URL/api/v1/callback",
  //           "usage_limit" => 1,
  //           "expiry" => (object) [
  //             // "start_time" => date('Y-m-d H:i:s Z', strtotime('+1 minutes')),
  //             "duration" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time,
  //             "unit" => "minutes"
  //           ],
  //         ];
  //       } else if (!$users) {
  //         $body = (object) [
  //           "transaction_details" => (object) [
  //             "order_id" => $transaction_code,
  //             "gross_amount" => $game_item->price + $fee - $discount
  //           ],
  //           "customer_required" => false,
  //           // "customer_details" => (object)[
  //           //     // "first_name" => $user_detail->name,
  //           //     // "last_name" => $user_detail->name,
  //           //     "email" => $request->email,
  //           //     // "phone" => $user_detail->phone
  //           // ],
  //           "enabled_payments" => $enable_payment,
  //           "callbackUrl" => "$URL/api/v1/callback",
  //           'item_details' => [
  //             [
  //               "id" => $transaction_code,
  //               "name" => $game_item->title,
  //               "price" => $game_item->price,
  //               "quantity" => 1,
  //             ],
  //             [
  //               "id" => $voucher_code,
  //               "name" => "Voucher discount",
  //               "price" => -$discount,
  //               "quantity" => 1,
  //             ],
  //             [
  //               "name" => "Fee",
  //               "price" => $fee,
  //               "quantity" => 1,
  //             ]
  //           ],
  //           "usage_limit" => 1,
  //           "expiry" => (object) [
  //             // "start_time" => date('Y-m-d H:i:s Z', strtotime('+1 minutes')),
  //             "duration" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time,
  //             "unit" => "minutes"
  //           ],
  //         ];
  //       }

  //       $dataResponse = Midtrans::useSnapMidtrans($body);

  //       // $dataResponse = $this->usePaymentLinkMidtrans($body);
  //       if (!is_null($users)) {
  //         if (isset($dataResponse['payment_url'])) {
  //           $tr_data = [
  //             'transaction_code' => $transaction_code,
  //             'users_code' => $users_code,
  //             'email' => $request->email,
  //             'total_amount' => $game_item->price - $discount + $fee,
  //             'subtotal' => $game_item->price,
  //             'fee' => $fee,
  //             'transaction_url' => $dataResponse['payment_url'],
  //             'from' => $game_item->from,
  //             'payment_method' => $pm->pm_code,
  //             // 'no_reference' => $dataResponse['order_id'],
  //             'status' => 'waiting',
  //             'voucher_discount' => $discount,
  //             'voucher_code' => $voucher_code,
  //             'transaction_token' => $dataResponse['token'],
  //           ];
  //           DB::table('transaction')->insert($tr_data);

  //           $tr_detail = [
  //             'detail_code' => generateFiledCode('TRD'),
  //             'transaction_code' => $transaction_code,
  //             'game_code' => $game_item->game_code,
  //             'item_code' => $game_item->code,
  //             'price' => $game_item->price - $discount,
  //             'qty' => 1,
  //             'total' => $game_item->price - $discount,
  //             'userid' => $request->userid,
  //             'username' => $request->username,
  //             'validation_token' => $request->validation_token
  //           ];
  //           DB::table('transaction_detail')->insert($tr_detail);
  //           DB::commit();

  //           return [
  //             'success' => true,
  //             'data' => $dataResponse
  //           ];
  //         }
  //       } else if (!$users) {
  //         if (isset($dataResponse['payment_url'])) {
  //           $tr_data = [
  //             'transaction_code' => $transaction_code,
  //             'users_code' => $request->email,
  //             'email' => $request->email,
  //             'total_amount' => $game_item->price - $discount + $fee,
  //             'subtotal' => $game_item->price,
  //             'fee' => $fee,
  //             'transaction_url' => $dataResponse['payment_url'],
  //             'from' => $game_item->from,
  //             'payment_method' => $pm->pm_code,
  //             // 'no_reference' => $dataResponse['order_id'],
  //             'status' => 'waiting',
  //             'voucher_discount' => $discount,
  //             'voucher_code' => $voucher_code,
  //             'transaction_token' => $dataResponse['token'],
  //           ];
  //           DB::table('transaction')->insert($tr_data);

  //           $tr_detail = [
  //             'detail_code' => generateFiledCode('TRD'),
  //             'transaction_code' => $transaction_code,
  //             'game_code' => $game_item->game_code,
  //             'item_code' => $game_item->code,
  //             'price' => $game_item->price - $discount,
  //             'qty' => 1,
  //             'total' => $game_item->price - $discount,
  //             'userid' => $request->userid,
  //             'username' => $request->username,
  //             'validation_token' => $request->validation_token
  //           ];
  //           DB::table('transaction_detail')->insert($tr_detail);
  //           DB::commit();

  //           return [
  //             'success' => true,
  //             'data' => $dataResponse
  //           ];
  //         }
  //       }
  //       DB::rollBack();
  //       return [
  //         'success' => false,
  //         'msg' => 'error'
  //       ];
  //     }
  //     if ($pm->from == 'duitku') {
  //       $apiKey = env('DUITKU_APIKEY');
  //       $merchantCode = env('DUITKU_MERCHANTID');
  //       $URL = env('APP_URL');
  //       // $paymentAmount = 10000; // mininum transaction
  //       $paymentAmount = $game_item->price - $discount + $fee;
  //       $merchantOrderId = $transaction_code;

  //       if (!is_null($users)) {
  //         $body = (object) [
  //           'paymentMethod' => $pm_code,
  //           'merchantOrderId' => $transaction_code,
  //           'merchantCode' => $merchantCode,
  //           "paymentAmount" => $paymentAmount,
  //           'productDetails' => $game_item->title,
  //           "additionalParam" => "",
  //           "merchantUserInfo" => "",
  //           "customerVaName" => $user_detail->name,
  //           "email" => $request->email,
  //           "phoneNumber" => $user_detail->phone,
  //           'itemDetails' => [
  //             (object) [
  //               'name' => $game_item->title,
  //               'price' => $paymentAmount,
  //               'quantity' => 1
  //             ]
  //           ],
  //           "customerDetail" => (object) [
  //             "firstName" => $user_detail->name,
  //             "lastName" => $user_detail->name,
  //             "email" => $request->email,
  //             "phone" => $user_detail->phone
  //           ],
  //           "callbackUrl" => "$URL/api/v1/callback",
  //           "returnUrl" => "https://vogaon.com/order/buy/" . $transaction_code,
  //           "signature" => md5($merchantCode . $merchantOrderId . $paymentAmount . $apiKey),
  //           "expiryPeriod" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time
  //         ];
  //       } else if (!$users) {
  //         $body = (object) [
  //           'paymentMethod' => $pm_code,
  //           'merchantOrderId' => $transaction_code,
  //           'merchantCode' => $merchantCode,
  //           "paymentAmount" => $paymentAmount,
  //           'productDetails' => $game_item->title,
  //           "additionalParam" => "",
  //           "merchantUserInfo" => "",
  //           "customerVaName" => $request->email,
  //           "email" => $request->email,
  //           "phoneNumber" => 0,
  //           'itemDetails' => [
  //             (object) [
  //               'name' => $game_item->title,
  //               'price' => $paymentAmount,
  //               'quantity' => 1
  //             ]
  //           ],
  //           "customerDetail" => (object) [
  //             "firstName" => $request->email,
  //             "lastName" => '',
  //             "email" => $request->email,
  //           ],
  //           "callbackUrl" => "$URL/api/v1/callback",
  //           "returnUrl" => "https://vogaon.com/order/buy/" . $transaction_code,
  //           "signature" => md5($merchantCode . $merchantOrderId . $paymentAmount . $apiKey),
  //           "expiryPeriod" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time
  //         ];
  //       }

  //       $response = $client->request('POST', 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry', [
  //         'body' => json_encode($body),
  //         'headers' => [
  //           'content-type' => 'application/json',
  //         ]
  //       ]);
  //       $dataResponse = json_decode($response->getBody()->getContents(), true);
  //       if ($users) {
  //         if (isset($dataResponse['paymentUrl'])) {
  //           $tr_data = [
  //             'transaction_code' => $transaction_code,
  //             'users_code' => $users_code,
  //             'email' => $request->email,
  //             'total_amount' => $game_item->price - $discount + $fee,
  //             'subtotal' => $game_item->price,
  //             'fee' => $fee,
  //             'transaction_url' => $dataResponse['paymentUrl'],
  //             'from' => $game_item->from,
  //             'payment_method' => $pm->pm_code,
  //             // 'no_reference' => $dataResponse['reference'],
  //             'status' => 'waiting',
  //             'voucher_discount' => $discount,
  //             'voucher_code' => $voucher_code,
  //           ];
  //           DB::table('transaction')->insert($tr_data);

  //           $tr_detail = [
  //             'detail_code' => generateFiledCode('TRD'),
  //             'transaction_code' => $transaction_code,
  //             'game_code' => $game_item->game_code,
  //             'item_code' => $game_item->code,
  //             'price' => $game_item->price - $discount,
  //             'qty' => 1,
  //             'total' => $game_item->price - $discount,
  //             'userid' => $request->userid,
  //             'username' => $request->username,
  //             'validation_token' => $request->validation_token
  //           ];
  //           DB::table('transaction_detail')->insert($tr_detail);
  //           DB::commit();

  //           return [
  //             'success' => true,
  //             'data' => (object) [
  //               'order_id' => $transaction_code,
  //               'payment_url' => $dataResponse['paymentUrl'],
  //               'other_data' => $dataResponse
  //             ]
  //           ];
  //         }
  //       } else if (!$users) {
  //         if (isset($dataResponse['paymentUrl'])) {
  //           $tr_data = [
  //             'transaction_code' => $transaction_code,
  //             'users_code' => $request->email,
  //             'email' => $request->email,
  //             'total_amount' => $game_item->price - $discount + $fee,
  //             'subtotal' => $game_item->price,
  //             'fee' => $fee,
  //             'transaction_url' => $dataResponse['paymentUrl'],
  //             'from' => $game_item->from,
  //             'payment_method' => $pm->pm_code,
  //             // 'no_reference' => $dataResponse['reference'],
  //             'status' => 'waiting',
  //             'voucher_discount' => $discount,
  //             'voucher_code' => $voucher_code,
  //           ];
  //           DB::table('transaction')->insert($tr_data);

  //           $tr_detail = [
  //             'detail_code' => generateFiledCode('TRD'),
  //             'transaction_code' => $transaction_code,
  //             'game_code' => $game_item->game_code,
  //             'item_code' => $game_item->code,
  //             'price' => $game_item->price - $discount,
  //             'qty' => 1,
  //             'total' => $game_item->price - $discount,
  //             'userid' => $request->userid,
  //             'username' => $request->username,
  //             'validation_token' => $request->validation_token
  //           ];
  //           DB::table('transaction_detail')->insert($tr_detail);
  //           DB::commit();

  //           return [
  //             'success' => true,
  //             'data' => (object) [
  //               'order_id' => $transaction_code,
  //               'payment_url' => $dataResponse['paymentUrl'],
  //               'other_data' => $dataResponse
  //             ]
  //           ];
  //         }
  //       }
  //       DB::rollBack();
  //       return [
  //         'success' => false,
  //         'msg' => 'error'
  //       ];
  //     }
  //     if (!is_null($users)) {
  //       if ($pm->from == 'saldo') {
  //         $cek = checkBalanceUser($users_code, ($game_item->price - $discount));

  //         if (!$cek['success']) {
  //           DB::rollBack();
  //           return [
  //             'success' => false,
  //             'msg' => 'Saldo Tidak Cukup !',
  //           ];
  //         }
  //         $total_amount = ($game_item->price + $fee) - $discount;
  //         $game = DB::table('games')->where('code', $game_item->game_code)->first();
  //         $total_amount_formatted = 'Rp. ' . number_format($total_amount, 0, ',', '.');
  //         $str_userid = $request->userid;
  //         $payment_method = DB::table('payment_method')->where('pm_code', $request->pm_code)->first();

  //         if (strpos($str_userid, '#') !== false) {
  //           $user_ids = explode('#', $str_userid);
  //           $merged_user_id = implode('(', $user_ids) . ')';
  //         } else {
  //           $merged_user_id = $str_userid;
  //         }

  //         $mail_data = [
  //           'game' => $game->title,
  //           'game_item' => $game_item->title,
  //           'userid' => $merged_user_id,
  //           'order_id' => $transaction_code,
  //           // 'no_reference' => $transaction_code,
  //           'order_date' => Carbon::now(),
  //           'pay_method' => $payment_method->pm_title,
  //           'pay_status' => 'Berhasil',
  //           'total_amount' => $total_amount_formatted,
  //         ];

  //         if ($game_item->from == 'unipin') {
  //           $result = Unipin::orderUnipin($transaction_code, $game_item->game_code, $request->validation_token, $game_item->denomination_id);

  //           Log::info('RESULT ORDER UNIPIN => ' . json_encode($result));

  //           if ($result['success']) {
  //             saveLog('ORDER PRODUCT', $transaction_code, $request->all(), 'Data from Unipinsssssss');
  //             $tr_data = [
  //               'transaction_code' => $transaction_code,
  //               'users_code' => $request->users_code,
  //               'email' => $request->email,
  //               'total_amount' => $game_item->price + $fee - $discount,
  //               'subtotal' => $game_item->price - $discount,
  //               'fee' => 0,
  //               'transaction_url' => '#',
  //               'from' => $game_item->from,
  //               'payment_method' => $pm->pm_code,
  //               // 'no_reference' => $transaction_code,
  //               'status' => 'success',
  //               'voucher_discount' => $discount,
  //               'voucher_code' => '-',
  //               'game_transaction_number' => $result['data']['transaction_number'],
  //               'game_transaction_status' => 1,
  //               'remaining_balance' => $cek['data']->users_balance - $game_item->price - $discount
  //             ];
  //             DB::table('transaction')->insert($tr_data);
  //             Log::info('RESULT ORDER UNIPIN TR 1 => ' . json_encode($tr_data));
  //             $dec_data = [
  //               'transaction_code' => generateOrderCode('VGN'),
  //               'users_code' => $request->users_code,
  //               'email' => $request->email,
  //               'total_amount' => -$game_item->price - $fee + $discount,
  //               'subtotal' => -$game_item->price + $fee + $discount,
  //               'fee' => 0,
  //               'transaction_url' => '#',
  //               'from' => $game_item->from,
  //               'payment_method' => $pm->pm_code,
  //               // 'no_reference' => $transaction_code,
  //               'status' => 'success',
  //               'voucher_discount' => $discount,
  //               'voucher_code' => $voucher_code,
  //               'type' => 'topup',
  //               'game_transaction_number' => $result['data']['transaction_number'],
  //               'game_transaction_status' => 1,
  //               'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
  //               'remaining_balance' => $cek['data']->users_balance - $game_item->price - $discount
  //             ];
  //             DB::table('transaction')->insert($dec_data);
  //             Log::info('RESULT ORDER UNIPIN TR 2 => ' . json_encode($dec_data));
  //             $tr_detail = [
  //               'detail_code' => generateFiledCode('TRD'),
  //               'transaction_code' => $transaction_code,
  //               'game_code' => $game_item->game_code,
  //               'item_code' => $game_item->code,
  //               'price' => $game_item->price,
  //               'qty' => 1,
  //               'total' => $game_item->price,
  //               'userid' => $request->userid,
  //               'username' => $request->username,
  //               'validation_token' => $request->validation_token
  //             ];
  //             DB::table('transaction_detail')->insert($tr_detail);

  //             DB::table('users_balance')->where('users_balance_code', $cek['data']->users_balance_code)->update(['users_balance' => ($cek['data']->users_balance - ($game_item->price - $discount))]);
  //             DB::commit();

  //             Mail::to($request->email)->send(new SuccessOrderItem($mail_data));
  //             return ['success' => true, 'data' => $tr_detail];
  //           } else {
  //             DB::rollBack();
  //             return ['success' => false, 'msg' => $result['data']['error']['message']];
  //           }
  //         }

  //         // ORDER DIGIFLAZZ
  //         if ($game_item->from == 'digiflazz') {
  //           $dataOrderDigi = [
  //             'user' => $users,
  //             'game_item' => $game_item,
  //           ];

  //           saveLog('ORDER DIGIFLAZZ', $transaction_code, $dataOrderDigi, 'Data from Digiflazz');
  //           $result = Digiflazz::order($transaction_code, $game_item->digi_code, $request->userid);

  //           Log::info('RESULT ORDER DIGIFLAZZ => ' . json_encode($result));
  //           saveLog('ORDER PRODUCT', $transaction_code, $request->all(), 'Data from Digiflazz');
  //           if ($result['success']) {
  //             $tr_data = [
  //               'transaction_code' => $transaction_code,
  //               'users_code' => $request->users_code,
  //               'email' => $request->email,
  //               'total_amount' => $game_item->price + $fee - $discount,
  //               'subtotal' => $game_item->price + $fee - $discount,
  //               'fee' => 0,
  //               'transaction_url' => '#',
  //               'from' => $game_item->from,
  //               'payment_method' => $pm->pm_code,
  //               // 'no_reference' => $transaction_code,
  //               'status' => strtolower($result['data']['status']),
  //               'voucher_discount' => $discount,
  //               'voucher_code' => '-',
  //               'game_transaction_number' => '',
  //               'game_transaction_status' => 1,
  //               'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
  //             ];
  //             DB::table('transaction')->insert($tr_data);
  //             $dec_data = [
  //               'transaction_code' => generateOrderCode('VGN'),
  //               'users_code' => $request->users_code,
  //               'email' => $request->email,
  //               'total_amount' => -$game_item->price + $fee + $discount,
  //               'subtotal' => -$game_item->price + $fee + $discount,
  //               'fee' => 0,
  //               'transaction_url' => '#',
  //               'from' => $game_item->from,
  //               'payment_method' => $pm->pm_code,
  //               // 'no_reference' => $transaction_code,
  //               'status' => strtolower($result['data']['status']),
  //               'voucher_discount' => $discount,
  //               'voucher_code' => $voucher_code,
  //               'type' => 'topup',
  //               'game_transaction_number' => '',
  //               'game_transaction_status' => 1,
  //               'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
  //               'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
  //             ];
  //             DB::table('transaction')->insert($dec_data);

  //             $tr_detail = [
  //               'detail_code' => generateFiledCode('TRD'),
  //               'transaction_code' => $transaction_code,
  //               'item_code' => $game_item->code,
  //               'price' => $game_item->price,
  //               'qty' => 1,
  //               'total' => $game_item->price,
  //               'userid' => $request->userid,
  //               'username' => $request->username,
  //               'validation_token' => $request->validation_token
  //             ];
  //             DB::table('transaction_detail')->insert($tr_detail);

  //             DB::table('users_balance')->where('users_balance_code', $cek['data']->users_balance_code)->update(['users_balance' => ($cek['data']->users_balance - ($game_item->price + $fee - $discount))]);
  //             DB::commit();

  //             Mail::to($request->email)->send(new SuccessOrderItem($mail_data));
  //             return ['success' => true, 'data' => $tr_detail];
  //           } else {
  //             DB::rollBack();
  //             return [
  //               'success' => false,
  //               'msg' => $result['msg']
  //             ];
  //           }
  //         }
  //         # Api games order
  //         if ($game_item->from == 'apigames') {
  //           $user_id = $request->userid;
  //           $result = Apigames::orderApigames($transaction_code, $game_item->ag_code, $user_id);
  //           saveLog('CALLBACK ORDER PRODUCT', $transaction_code, $result, 'Data from Apigames');
  //           if ($result['data']['status'] == 1) {
  //             $orderStatus = $result['data']['data']['status'];

  //             $statusTransaction = [
  //               'Sukses' => 1,
  //               'Pending' => 2,
  //               'Proses' => 3,
  //               'Sukses Sebagian' => 4,
  //               'Validasi Provider' => 5,
  //               'Gagal' => 6
  //             ];

  //             if ($orderStatus === 'Sukses') {
  //               $tr_data = [
  //                 'transaction_code' => $transaction_code,
  //                 'users_code' => $users_code,
  //                 'email' => $user_detail->email,
  //                 'total_amount' => $game_item->price + $fee - $discount,
  //                 'subtotal' => $game_item->price + $fee - $discount,
  //                 'fee' => 0,
  //                 'transaction_url' => '#',
  //                 'from' => $game_item->from,
  //                 'payment_method' => $pm->pm_code,
  //                 // 'no_reference' => $transaction_code,
  //                 'status' => 'success',
  //                 'voucher_discount' => $discount,
  //                 'voucher_code' => '-',
  //                 'game_transaction_number' => $result['data']['data']['trx_id'],
  //                 'game_transaction_status' => $statusTransaction[$orderStatus],
  //                 'game_transaction_message' => $result['data']['data']['message'],
  //                 'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
  //               ];
  //               DB::table('transaction')->insert($tr_data);
  //               $dec_data = [
  //                 'transaction_code' => generateOrderCode('VGN'),
  //                 'users_code' => $users_code,
  //                 'email' => $user_detail->email,
  //                 'total_amount' => -$game_item->price + $fee + $discount,
  //                 'subtotal' => -$game_item->price + $fee + $discount,
  //                 'fee' => 0,
  //                 'transaction_url' => '#',
  //                 'from' => $game_item->from,
  //                 'payment_method' => $pm->pm_code,
  //                 // 'no_reference' => $transaction_code,
  //                 'status' => 'success',
  //                 'voucher_discount' => $discount,
  //                 'voucher_code' => $voucher_code,
  //                 'game_transaction_number' => $result['data']['data']['trx_id'],
  //                 'game_transaction_status' => $statusTransaction[$orderStatus],
  //                 'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
  //                 'type' => 'topup',
  //                 'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
  //               ];
  //               // dd($dec_data);
  //               DB::table('transaction')->insert($dec_data);
  //               $tr_detail = [
  //                 'detail_code' => generateFiledCode('TRD'),
  //                 'transaction_code' => $transaction_code,
  //                 'game_code' => $game_item->game_code,
  //                 'item_code' => $game_item->code,
  //                 'price' => $game_item->price,
  //                 'qty' => 1,
  //                 'total' => $game_item->price,
  //                 'userid' => $request->userid,
  //                 'username' => $request->username,
  //                 'validation_token' => $request->validation_token
  //               ];
  //               DB::table('transaction_detail')->insert($tr_detail);

  //               DB::table('users_balance')->where('users_balance_code', $cek['data']->users_balance_code)->update(['users_balance' => ($cek['data']->users_balance - ($game_item->price + $fee - $discount))]);
  //               DB::commit();

  //               Mail::to($request->email)->send(new SuccessOrderItem($mail_data));
  //               Log::info('SUCCESS ORDER APIGAMES => ' . json_encode($result));
  //               return ['success' => true, 'data' => $tr_detail];
  //             }

  //             if ($orderStatus === 'Sukses Sebagian') {
  //               $tr_data = [
  //                 'transaction_code' => $transaction_code,
  //                 'users_code' => $users_code,
  //                 'email' => $user_detail->email,
  //                 'total_amount' => $game_item->price + $fee - $discount,
  //                 'subtotal' => $game_item->price + $fee - $discount,
  //                 'fee' => $fee,
  //                 'transaction_url' => '#',
  //                 'from' => $game_item->from,
  //                 'payment_method' => $pm->pm_code,
  //                 // 'no_reference' => $transaction_code,
  //                 'status' => 'success',
  //                 'voucher_discount' => $discount,
  //                 'voucher_code' => '-',
  //                 'game_transaction_number' => $result['data']['data']['trx_id'],
  //                 'game_transaction_status' => $statusTransaction[$orderStatus],
  //                 'game_transaction_message' => $result['data']['data']['message'],
  //                 'remaining_balance' => $cek['data']->users_balance - ($game_item->price + $fee) - $discount
  //               ];
  //               DB::table('transaction')->insert($tr_data);
  //               $dec_data = [
  //                 'transaction_code' => generateOrderCode('VGN'),
  //                 'users_code' => $users_code,
  //                 'email' => $user_detail->email,
  //                 'total_amount' => -$game_item->price + $fee + $discount,
  //                 'subtotal' => -$game_item->price + $fee + $discount,
  //                 'fee' => 0,
  //                 'transaction_url' => '#',
  //                 'from' => $game_item->from,
  //                 'payment_method' => $pm->pm_code,
  //                 // 'no_reference' => $transaction_code,
  //                 'status' => 'success',
  //                 'voucher_discount' => $discount,
  //                 'voucher_code' => $voucher_code,
  //                 'game_transaction_number' => $result['data']['data']['trx_id'],
  //                 'game_transaction_status' => $statusTransaction[$orderStatus],
  //                 'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
  //                 'type' => 'topup',
  //                 'remaining_balance' => $cek['data']->users_balance - $game_item->price - $discount
  //               ];
  //               // dd($dec_data);
  //               DB::table('transaction')->insert($dec_data);
  //               $tr_detail = [
  //                 'detail_code' => generateFiledCode('TRD'),
  //                 'transaction_code' => $transaction_code,
  //                 'game_code' => $game_item->game_code,
  //                 'item_code' => $game_item->code,
  //                 'price' => $game_item->price,
  //                 'qty' => 1,
  //                 'total' => $game_item->price,
  //                 'userid' => $request->userid,
  //                 'username' => $request->username,
  //                 'validation_token' => $request->validation_token
  //               ];
  //               DB::table('transaction_detail')->insert($tr_detail);

  //               DB::table('users_balance')->where('users_balance_code', $cek['data']->users_balance_code)->update(['users_balance' => ($cek['data']->users_balance - ($game_item->price + $fee - $discount))]);
  //               DB::commit();

  //               Mail::to($request->email)->send(new SuccessOrderItem($mail_data));
  //               Log::info('SUCCESS ORDER APIGAMES => ' . json_encode($result));
  //             }

  //             if ($orderStatus === 'Gagal') {
  //               $tr_data = [
  //                 'transaction_code' => $transaction_code,
  //                 'users_code' => $users_code,
  //                 'email' => $user_detail->email,
  //                 'total_amount' => $game_item->price + $fee - $discount,
  //                 'subtotal' => $game_item->price + $fee - $discount,
  //                 'fee' => 0,
  //                 'transaction_url' => '#',
  //                 'from' => $game_item->from,
  //                 'payment_method' => $pm->pm_code,
  //                 // 'no_reference' => $transaction_code,
  //                 'status' => 'failed',
  //                 'voucher_discount' => $discount,
  //                 'voucher_code' => '-',
  //                 'game_transaction_number' => $result['data']['data']['trx_id'],
  //                 'game_transaction_status' => $statusTransaction[$orderStatus],
  //                 'game_transaction_message' => $result['data']['data']['message'],
  //                 'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
  //               ];
  //               DB::table('transaction')->insert($tr_data);

  //               $tr_detail = [
  //                 'detail_code' => generateFiledCode('TRD'),
  //                 'transaction_code' => $transaction_code,
  //                 'game_code' => $game_item->game_code,
  //                 'item_code' => $game_item->code,
  //                 'price' => $game_item->price,
  //                 'qty' => 1,
  //                 'total' => $game_item->price,
  //                 'userid' => $request->userid,
  //                 'username' => $request->username,
  //                 'validation_token' => $request->validation_token
  //               ];
  //               DB::table('transaction_detail')->insert($tr_detail);
  //               DB::commit();

  //               Log::info('FAILED ORDER APIGAMES => ' . json_encode($result));
  //             }

  //             if ($orderStatus === 'Validasi Provider') {
  //               $tr_data = [
  //                 'transaction_code' => $transaction_code,
  //                 'users_code' => $users_code,
  //                 'email' => $user_detail->email,
  //                 'total_amount' => $game_item->price + $fee - $discount,
  //                 'subtotal' => $game_item->price + $fee - $discount,
  //                 'fee' => $fee,
  //                 'transaction_url' => '#',
  //                 'from' => $game_item->from,
  //                 'payment_method' => $pm->pm_code,
  //                 // 'no_reference' => $transaction_code,
  //                 'status' => 'processing',
  //                 'voucher_discount' => $discount,
  //                 'voucher_code' => '-',
  //                 'game_transaction_number' => $result['data']['data']['trx_id'],
  //                 'game_transaction_status' => $statusTransaction[$orderStatus],
  //                 'game_transaction_message' => $result['data']['data']['message'],
  //                 'remaining_balance' => $cek['data']->users_balance - $game_item->price - $discount
  //               ];
  //               DB::table('transaction')->insert($tr_data);

  //               $tr_detail = [
  //                 'detail_code' => generateFiledCode('TRD'),
  //                 'transaction_code' => $transaction_code,
  //                 'game_code' => $game_item->game_code,
  //                 'item_code' => $game_item->code,
  //                 'price' => $game_item->price,
  //                 'qty' => 1,
  //                 'total' => $game_item->price,
  //                 'userid' => $request->userid,
  //                 'username' => $request->username,
  //                 'validation_token' => $request->validation_token
  //               ];
  //               DB::table('transaction_detail')->insert($tr_detail);
  //               DB::commit();

  //               Log::info('Validasi Provider APIGAMES => ' . json_encode($result));
  //             }

  //             if ($orderStatus === 'Proses') {
  //               $tr_data = [
  //                 'transaction_code' => $transaction_code,
  //                 'users_code' => $users_code,
  //                 'email' => $user_detail->email,
  //                 'total_amount' => $game_item->price + $fee - $discount,
  //                 'subtotal' => $game_item->price + $fee - $discount,
  //                 'fee' => $fee,
  //                 'transaction_url' => '#',
  //                 'from' => $game_item->from,
  //                 'payment_method' => $pm->pm_code,
  //                 // 'no_reference' => $transaction_code,
  //                 'status' => 'failed',
  //                 'voucher_discount' => $discount,
  //                 'voucher_code' => '-',
  //                 'game_transaction_number' => $result['data']['data']['trx_id'],
  //                 'game_transaction_status' => $statusTransaction[$orderStatus],
  //                 'game_transaction_message' => $result['data']['data']['message'],
  //                 'remaining_balance' => $cek['data']->users_balance - $game_item->price - $discount
  //               ];
  //               DB::table('transaction')->insert($tr_data);

  //               $tr_detail = [
  //                 'detail_code' => generateFiledCode('TRD'),
  //                 'transaction_code' => $transaction_code,
  //                 'game_code' => $game_item->game_code,
  //                 'item_code' => $game_item->code,
  //                 'price' => $game_item->price,
  //                 'qty' => 1,
  //                 'total' => $game_item->price,
  //                 'userid' => $request->userid,
  //                 'username' => $request->username,
  //                 'validation_token' => $request->validation_token
  //               ];
  //               DB::table('transaction_detail')->insert($tr_detail);
  //               DB::commit();

  //               Log::info('PROCESSING APIGAMES => ' . json_encode($result));
  //             }

  //             if ($orderStatus === 'Pending') {
  //               $tr_data = [
  //                 'transaction_code' => $transaction_code,
  //                 'users_code' => $users_code,
  //                 'email' => $user_detail->email,
  //                 'total_amount' => $game_item->price + $fee - $discount,
  //                 'subtotal' => $game_item->price + $fee - $discount,
  //                 'fee' => $fee,
  //                 'transaction_url' => '#',
  //                 'from' => $game_item->from,
  //                 'payment_method' => $pm->pm_code,
  //                 // 'no_reference' => $transaction_code,
  //                 'status' => 'waiting',
  //                 'voucher_discount' => $discount,
  //                 'voucher_code' => '-',
  //                 'game_transaction_number' => $result['data']['data']['trx_id'],
  //                 'game_transaction_status' => $statusTransaction[$orderStatus],
  //                 'game_transaction_message' => $result['data']['data']['message'],
  //                 'remaining_balance' => $cek['data']->users_balance - ($game_item->price + $fee) - $discount
  //               ];
  //               DB::table('transaction')->insert($tr_data);

  //               $tr_detail = [
  //                 'detail_code' => generateFiledCode('TRD'),
  //                 'transaction_code' => $transaction_code,
  //                 'game_code' => $game_item->game_code,
  //                 'item_code' => $game_item->code,
  //                 'price' => $game_item->price,
  //                 'qty' => 1,
  //                 'total' => $game_item->price,
  //                 'userid' => $request->userid,
  //                 'username' => $request->username,
  //                 'validation_token' => $request->validation_token
  //               ];
  //               DB::table('transaction_detail')->insert($tr_detail);
  //               DB::commit();

  //               Log::info('PENDING ORDER APIGAMES => ' . json_encode($result));
  //               saveLog('ORDER APIGAMES', $transaction_code, $request->all(), 'Order Pending');
  //             }
  //             return ['success' => true, 'data' => $tr_detail];
  //           } else if ($result['data']['status'] == 0) {
  //             DB::rollBack();
  //             saveLog('ORDER APIGAMES', $transaction_code, $request->all(), 'Order Failed');
  //             return ['success' => false, 'msg' => $result['data']['error_msg']];
  //           }
  //         }
  //       }
  //     } else if (!$users && $pm->from == 'saldo') {
  //       return [
  //         'success' => false,
  //         'msg' => 'Payment method invalid, please register an account and top up your balance',
  //       ];
  //     }
  //   } catch (\Exception $e) {
  //     DB::rollBack();
  //     return [
  //       'success' => false,
  //       'msg' => $e->getMessage()
  //     ];
  //   }
  // }

  public function payTransaction($request)
  {
    DB::beginTransaction();
    try {
      $pm_code = $request->pm_code; // Payment method
      $users_code = '';
      $pm = DB::table('payment_method')->where('pm_code', $pm_code)->first();
      // dd($pm);

      if (empty($pm)) {
        return [
          'success' => false,
          'msg' => 'Payment method invalid',
        ];
      }

      $client = new Client();
      $transaction_code = generateOrderCode('VGN');
      $game_item = DB::table('games_item')->where('code', $request->item_code)->orderBy('created_at', 'DESC')->first();
      if (empty($game_item)) {
        return [
          'success' => false,
          'msg' => 'Game selected not valid',
        ];
      }

      $game = DB::table('games')->where('code', $game_item->game_code)->first();

      $user_detail = (object) [
        'name' => $request->email,
        'email' => $request->email,
        'phone' => 0
      ];

      $users = auth('sanctum')->user();

      if (!$users) {
        $game_item->price = $game_item->price_not_member;
      }

      if (!is_null($users)) {
        $users_code = $users->users_code;
        if ($users->memberType == 2) {
          $game_item->price = $game_item->price_reseller;
        }

        $user = DB::table('users')->where('users_code', $users_code)->first();
        if (!empty($user)) {
          $user_detail->name = $user->name;
          $user_detail->email = $user->email;
          $user_detail->phone = $user->no_telp;
        }
      }

      $voucher_code = $request->voucher_code;
      $discount = 0;
      if (!empty($voucher_code)) {
        $voucher = checkVoucherRules($voucher_code, $pm_code, $game_item->price);
        if ($voucher['success']) {
          $discount = $voucher['data']['vouchers_discount'];
          decreaseMaxVoucherUsed($voucher_code);
        }
      } else {
        $voucher_code = '-';
      }

      $fee = $pm->fee;
      if ($pm->fee_type == 'percent') {
        $fee = ($game_item->price - $discount) * number_format($pm->fee / 100, 3);
      }
      $fee = ceil($fee);

      $all_fields = $request->all_fields;
      foreach ($all_fields as $key => $value) {
        $idg = [
          'game_code' => $game_item->game_code,
          'transaction_code' => $transaction_code,
          'fields_name' => $key,
          'value' => $value
        ];
        DB::table('transaction_game_id')->insert($idg);
      }

      $request->userid = makeFields($request->all_fields);

      if ($pm->from == 'midtrans') {
        $URL = env('APP_URL');
        $enable_payment = getEnablePayment($pm->pm_code);

        if (!is_null($users)) {
          $body = (object) [
            "transaction_details" => (object) [
              "order_id" => $transaction_code,
              "gross_amount" => $game_item->price + $fee - $discount
            ],
            "customer_required" => false,
            "customer_details" => (object) [
              "first_name" => $user_detail->name,
              "last_name" => $user_detail->name,
              "email" => $request->email,
              "phone" => $user_detail->phone
            ],
            "enabled_payments" => $enable_payment,
            'item_details' => [
              [
                "id" => $transaction_code,
                "name" => $game_item->title,
                "price" => $game_item->price,
                "quantity" => 1,
              ],
              [
                "id" => $voucher_code,
                "name" => "Voucher discount",
                "price" => -$discount,
                "quantity" => 1,
              ],
              [
                "name" => "Fee",
                "price" => ceil($fee),
                "quantity" => 1,
              ]
            ],
            "callbackUrl" => "$URL/api/v1/callback",
            "usage_limit" => 1,
            "expiry" => (object) [
              // "start_time" => date('Y-m-d H:i:s Z', strtotime('+1 minutes')),
              "duration" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time,
              "unit" => "minutes"
            ],
          ];
        } else if (!$users) {
          $body = (object) [
            "transaction_details" => (object) [
              "order_id" => $transaction_code,
              "gross_amount" => $game_item->price + $fee - $discount
            ],
            "customer_required" => false,
            // "customer_details" => (object)[
            //     // "first_name" => $user_detail->name,
            //     // "last_name" => $user_detail->name,
            //     "email" => $request->email,
            //     // "phone" => $user_detail->phone
            // ],
            "enabled_payments" => $enable_payment,
            "callbackUrl" => "$URL/api/v1/callback",
            'item_details' => [
              [
                "id" => $transaction_code,
                "name" => $game_item->title,
                "price" => $game_item->price,
                "quantity" => 1,
              ],
              [
                "id" => $voucher_code,
                "name" => "Voucher discount",
                "price" => -$discount,
                "quantity" => 1,
              ],
              [
                "name" => "Fee",
                "price" => $fee,
                "quantity" => 1,
              ]
            ],
            "usage_limit" => 1,
            "expiry" => (object) [
              // "start_time" => date('Y-m-d H:i:s Z', strtotime('+1 minutes')),
              "duration" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time,
              "unit" => "minutes"
            ],
          ];
        }

        $dataResponse = Midtrans::useSnapMidtrans($body);

        // $dataResponse = $this->usePaymentLinkMidtrans($body);
        if (!is_null($users)) {
          if (isset($dataResponse['payment_url'])) {
            $tr_data = [
              'transaction_code' => $transaction_code,
              'users_code' => $users_code,
              'email' => $request->email,
              'total_amount' => $game_item->price - $discount + $fee,
              'subtotal' => $game_item->price,
              'fee' => $fee,
              'transaction_url' => $dataResponse['payment_url'],
              'from' => $game_item->from,
              'payment_method' => $pm->pm_code,
              // 'no_reference' => $dataResponse['order_id'],
              'status' => 'waiting',
              'voucher_discount' => $discount,
              'voucher_code' => $voucher_code,
              'transaction_token' => $dataResponse['token'],
            ];
            DB::table('transaction')->insert($tr_data);

            $tr_detail = [
              'detail_code' => generateFiledCode('TRD'),
              'transaction_code' => $transaction_code,
              'game_code' => $game_item->game_code,
              'item_code' => $game_item->code,
              'price' => $game_item->price - $discount,
              'qty' => 1,
              'total' => $game_item->price - $discount,
              'userid' => $request->userid,
              'username' => $request->username,
              'validation_token' => $request->validation_token,
              'game_title' => $game->title,
              'item_title' => $game_item->title,
            ];
            DB::table('transaction_detail')->insert($tr_detail);
            DB::commit();

            return [
              'success' => true,
              'data' => $dataResponse
            ];
          }
        } else if (!$users) {
          if (isset($dataResponse['payment_url'])) {
            $tr_data = [
              'transaction_code' => $transaction_code,
              'users_code' => $request->email,
              'email' => $request->email,
              'total_amount' => $game_item->price - $discount + $fee,
              'subtotal' => $game_item->price,
              'fee' => $fee,
              'transaction_url' => $dataResponse['payment_url'],
              'from' => $game_item->from,
              'payment_method' => $pm->pm_code,
              // 'no_reference' => $dataResponse['order_id'],
              'status' => 'waiting',
              'voucher_discount' => $discount,
              'voucher_code' => $voucher_code,
              'transaction_token' => $dataResponse['token'],
            ];
            DB::table('transaction')->insert($tr_data);

            $tr_detail = [
              'detail_code' => generateFiledCode('TRD'),
              'transaction_code' => $transaction_code,
              'game_code' => $game_item->game_code,
              'item_code' => $game_item->code,
              'price' => $game_item->price - $discount,
              'qty' => 1,
              'total' => $game_item->price - $discount,
              'userid' => $request->userid,
              'username' => $request->username,
              'validation_token' => $request->validation_token,
              'game_title' => $game->title,
              'item_title' => $game_item->title,
            ];
            DB::table('transaction_detail')->insert($tr_detail);
            DB::commit();

            return [
              'success' => true,
              'data' => $dataResponse
            ];
          }
        }
        DB::rollBack();
        return [
          'success' => false,
          'msg' => 'error'
        ];
      }
      if ($pm->from == 'duitku') {
        $apiKey = env('DUITKU_APIKEY');
        $merchantCode = env('DUITKU_MERCHANTID');
        $URL = env('APP_URL');
        // $paymentAmount = 10000; // mininum transaction
        $paymentAmount = $game_item->price - $discount + $fee;
        $merchantOrderId = $transaction_code;

        if (!is_null($users)) {
          $body = (object) [
            'paymentMethod' => $pm_code,
            'merchantOrderId' => $transaction_code,
            'merchantCode' => $merchantCode,
            "paymentAmount" => $paymentAmount,
            'productDetails' => $game_item->title,
            "additionalParam" => "",
            "merchantUserInfo" => "",
            "customerVaName" => $user_detail->name,
            "email" => $request->email,
            "phoneNumber" => $user_detail->phone,
            'itemDetails' => [
              (object) [
                'name' => $game_item->title,
                'price' => $paymentAmount,
                'quantity' => 1
              ]
            ],
            "customerDetail" => (object) [
              "firstName" => $user_detail->name,
              "lastName" => $user_detail->name,
              "email" => $request->email,
              "phone" => $user_detail->phone
            ],
            "callbackUrl" => "$URL/api/v1/callback",
            "returnUrl" => "https://vogaon.com/order/buy/" . $transaction_code,
            "signature" => md5($merchantCode . $merchantOrderId . $paymentAmount . $apiKey),
            "expiryPeriod" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time
          ];
        } else if (!$users) {
          $body = (object) [
            'paymentMethod' => $pm_code,
            'merchantOrderId' => $transaction_code,
            'merchantCode' => $merchantCode,
            "paymentAmount" => $paymentAmount,
            'productDetails' => $game_item->title,
            "additionalParam" => "",
            "merchantUserInfo" => "",
            "customerVaName" => $request->email,
            "email" => $request->email,
            "phoneNumber" => 0,
            'itemDetails' => [
              (object) [
                'name' => $game_item->title,
                'price' => $paymentAmount,
                'quantity' => 1
              ]
            ],
            "customerDetail" => (object) [
              "firstName" => $request->email,
              "lastName" => '',
              "email" => $request->email,
            ],
            "callbackUrl" => "$URL/api/v1/callback",
            "returnUrl" => "https://vogaon.com/order/buy/" . $transaction_code,
            "signature" => md5($merchantCode . $merchantOrderId . $paymentAmount . $apiKey),
            "expiryPeriod" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time
          ];
        }

        $response = $client->request('POST', 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry', [
          'body' => json_encode($body),
          'headers' => [
            'content-type' => 'application/json',
          ]
        ]);
        $dataResponse = json_decode($response->getBody()->getContents(), true);
        if ($users) {
          if (isset($dataResponse['paymentUrl'])) {
            $tr_data = [
              'transaction_code' => $transaction_code,
              'users_code' => $users_code,
              'email' => $request->email,
              'total_amount' => $game_item->price - $discount + $fee,
              'subtotal' => $game_item->price,
              'fee' => $fee,
              'transaction_url' => $dataResponse['paymentUrl'],
              'from' => $game_item->from,
              'payment_method' => $pm->pm_code,
              // 'no_reference' => $dataResponse['reference'],
              'status' => 'waiting',
              'voucher_discount' => $discount,
              'voucher_code' => $voucher_code,
            ];
            DB::table('transaction')->insert($tr_data);

            $tr_detail = [
              'detail_code' => generateFiledCode('TRD'),
              'transaction_code' => $transaction_code,
              'game_code' => $game_item->game_code,
              'item_code' => $game_item->code,
              'price' => $game_item->price - $discount,
              'qty' => 1,
              'total' => $game_item->price - $discount,
              'userid' => $request->userid,
              'username' => $request->username,
              'validation_token' => $request->validation_token,
              'game_title' => $game->title,
              'item_title' => $game_item->title,
            ];
            DB::table('transaction_detail')->insert($tr_detail);
            DB::commit();

            return [
              'success' => true,
              'data' => (object) [
                'order_id' => $transaction_code,
                'payment_url' => $dataResponse['paymentUrl'],
                'other_data' => $dataResponse
              ]
            ];
          }
        } else if (!$users) {
          if (isset($dataResponse['paymentUrl'])) {
            $tr_data = [
              'transaction_code' => $transaction_code,
              'users_code' => $request->email,
              'email' => $request->email,
              'total_amount' => $game_item->price - $discount + $fee,
              'subtotal' => $game_item->price,
              'fee' => $fee,
              'transaction_url' => $dataResponse['paymentUrl'],
              'from' => $game_item->from,
              'payment_method' => $pm->pm_code,
              // 'no_reference' => $dataResponse['reference'],
              'status' => 'waiting',
              'voucher_discount' => $discount,
              'voucher_code' => $voucher_code,
            ];
            DB::table('transaction')->insert($tr_data);

            $tr_detail = [
              'detail_code' => generateFiledCode('TRD'),
              'transaction_code' => $transaction_code,
              'game_code' => $game_item->game_code,
              'item_code' => $game_item->code,
              'price' => $game_item->price - $discount,
              'qty' => 1,
              'total' => $game_item->price - $discount,
              'userid' => $request->userid,
              'username' => $request->username,
              'validation_token' => $request->validation_token,
              'game_title' => $game->title,
              'item_title' => $game_item->title,
            ];
            DB::table('transaction_detail')->insert($tr_detail);
            DB::commit();

            return [
              'success' => true,
              'data' => (object) [
                'order_id' => $transaction_code,
                'payment_url' => $dataResponse['paymentUrl'],
                'other_data' => $dataResponse
              ]
            ];
          }
        }
        DB::rollBack();
        return [
          'success' => false,
          'msg' => 'error'
        ];
      }
      if (!is_null($users)) {
        if ($pm->from == 'saldo') {
          if ($users->memberType == 2) {
            $fee = 0;
          }
          $cek = checkBalanceUser($users_code, ($game_item->price + $fee) - $discount);

          if (!$cek['success']) {
            DB::rollBack();
            return [
              'success' => false,
              'msg' => 'Saldo Tidak Cukup !',
            ];
          }
          $total_amount = ($game_item->price + $fee) - $discount;
          $game = DB::table('games')->where('code', $game_item->game_code)->first();
          $total_amount_formatted = 'Rp. ' . number_format($total_amount, 0, ',', '.');
          $str_userid = $request->userid;
          $payment_method = DB::table('payment_method')->where('pm_code', $request->pm_code)->first();

          if (strpos($str_userid, '#') !== false) {
            $user_ids = explode('#', $str_userid);
            $merged_user_id = implode('(', $user_ids) . ')';
          } else {
            $merged_user_id = str_replace("-", "", $str_userid);
          }

          $mail_data = [
            'game' => $game->title,
            'game_item' => $game_item->title,
            'userid' => $merged_user_id,
            'order_id' => $transaction_code,
            // 'no_reference' => $transaction_code,
            'order_date' => Carbon::now(),
            'pay_method' => $payment_method->pm_title,
            'pay_status' => 'Berhasil',
            'total_amount' => $total_amount_formatted,
          ];

          if ($game_item->from == 'unipin') {
            DB::table('users_balance')
              ->where('users_balance_code', $cek['data']->users_balance_code)
              ->update(['users_balance' => ($cek['data']->users_balance - $total_amount)]);
            DB::commit();

            $result = Unipin::orderUnipin($transaction_code, $game_item->game_code, $request->validation_token, $game_item->denomination_id);

            Log::info('RESULT ORDER UNIPIN => ' . json_encode($result));

            if ($result['success']) {
              saveLog('ORDER PRODUCT', $transaction_code, $request->all(), 'Data from Unipinsssssss');

              $tr_data = [
                'transaction_code' => $transaction_code,
                'users_code' => $request->users_code,
                'email' => $request->email,
                'total_amount' => $game_item->price + $fee - $discount,
                'subtotal' => $game_item->price - $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'success',
                'voucher_discount' => $discount,
                'voucher_code' => '-',
                'game_transaction_number' => $result['data']['transaction_number'],
                'game_transaction_status' => 1,
                'remaining_balance' => $cek['data']->users_balance - $game_item->price - $discount
              ];
              DB::table('transaction')->insert($tr_data);
              Log::info('RESULT ORDER UNIPIN TR 1 => ' . json_encode($tr_data));
              $tr_detail = [
                'detail_code' => generateFiledCode('TRD'),
                'transaction_code' => $transaction_code,
                'game_code' => $game_item->game_code,
                'item_code' => $game_item->code,
                'price' => $game_item->price,
                'qty' => 1,
                'total' => $game_item->price,
                'userid' => $request->userid,
                'username' => $request->username,
                'validation_token' => $request->validation_token,
                'game_title' => $game->title,
                'item_title' => $game_item->title,
              ];
              DB::table('transaction_detail')->insert($tr_detail);

              $dec_data = [
                'transaction_code' => generateOrderCode('VGN'),
                'users_code' => $request->users_code,
                'email' => $request->email,
                'total_amount' => -$game_item->price - $fee + $discount,
                'subtotal' => -$game_item->price + $fee + $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'success',
                'voucher_discount' => $discount,
                'voucher_code' => $voucher_code,
                'type' => 'topup',
                'game_transaction_number' => $result['data']['transaction_number'],
                'game_transaction_status' => 1,
                'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
                'remaining_balance' => $cek['data']->users_balance - $total_amount
              ];
              DB::table('transaction')->insert($dec_data);
              Log::info('RESULT ORDER UNIPIN TR 2 => ' . json_encode($dec_data));

              DB::commit();

              Mail::to($request->email)->send(new SuccessOrderItem($mail_data));
              return ['success' => true, 'data' => $tr_detail];
            } else {
              DB::rollBack();

              DB::beginTransaction();
              $cek = checkBalanceUser($users_code, $total_amount);
              DB::table('users_balance')
                ->where('users_balance_code', $cek['data']->users_balance_code)
                ->update(['users_balance' => ($cek['data']->users_balance + $total_amount)]);

              $tr_data = [
                'transaction_code' => $transaction_code,
                'users_code' => $request->users_code,
                'email' => $request->email,
                'total_amount' => $game_item->price + $fee - $discount,
                'subtotal' => $game_item->price - $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'failed',
                'voucher_discount' => $discount,
                'voucher_code' => '-',
                'game_transaction_number' => $result['data']['transaction_number'] ?? "",
                'game_transaction_status' => 1,
                'remaining_balance' => $cek['data']->users_balance - $total_amount
              ];
              DB::table('transaction')->insert($tr_data);

              $tr_detail = [
                'detail_code' => generateFiledCode('TRD'),
                'transaction_code' => $transaction_code,
                'game_code' => $game_item->game_code,
                'item_code' => $game_item->code,
                'price' => $game_item->price,
                'qty' => 1,
                'total' => $game_item->price,
                'userid' => $request->userid,
                'username' => $request->username,
                'validation_token' => $request->validation_token,
                'game_title' => $game->title,
                'item_title' => $game_item->title,
              ];
              DB::table('transaction_detail')->insert($tr_detail);

              $dec_data = [
                'transaction_code' => generateOrderCode('VGN'),
                'users_code' => $request->users_code,
                'email' => $request->email,
                'total_amount' => -$game_item->price - $fee + $discount,
                'subtotal' => -$game_item->price + $fee + $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'success',
                'voucher_discount' => $discount,
                'voucher_code' => $voucher_code,
                'type' => 'topup',
                'game_transaction_number' => $result['data']['transaction_number'] ?? "",
                'game_transaction_status' => 1,
                'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
                'remaining_balance' => $cek['data']->users_balance - $total_amount
              ];
              DB::table('transaction')->insert($dec_data);

              $inc_data = [
                'transaction_code' => generateOrderCode('VGN'),
                'users_code' => $request->users_code,
                'email' => $request->email,
                'total_amount' => $game_item->price - $fee + $discount,
                'subtotal' => $game_item->price + $fee + $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'success',
                'voucher_discount' => $discount,
                'voucher_code' => $voucher_code,
                'type' => 'topup',
                'game_transaction_number' => $result['data']['transaction_number'] ?? "",
                'game_transaction_status' => 1,
                'game_transaction_message' => 'Pengembalian dana Transaksi gagal ' . $transaction_code,
                'remaining_balance' => $cek['data']->users_balance,
                'created_at' => date('Y-m-d H:i:s', strtotime('+10 seconds'))
              ];
              DB::table('transaction')->insert($inc_data);

              DB::commit();

              return ['success' => false, 'msg' => $result['data']['error']['message']];
            }
          }

          // ORDER DIGIFLAZZ
          if ($game_item->from == 'digiflazz') {
            $dataOrderDigi = [
              'user' => $users,
              'game_item' => $game_item,
            ];

            DB::table('users_balance')
              ->where('users_balance_code', $cek['data']->users_balance_code)
              ->update(['users_balance' => ($cek['data']->users_balance - ($game_item->price + $fee - $discount))]);
            DB::commit();

            saveLog('ORDER DIGIFLAZZ', $transaction_code, $dataOrderDigi, 'Data from Digiflazz');
            $result = Digiflazz::order($transaction_code, $game_item->digi_code, $request->userid);

            Log::info('RESULT ORDER DIGIFLAZZ => ' . json_encode($result));
            saveLog('ORDER PRODUCT', $transaction_code, $request->all(), 'Data from Digiflazz');
            if ($result['success']) {
              $tr_data = [
                'transaction_code' => $transaction_code,
                'users_code' => $request->users_code,
                'email' => $request->email,
                'total_amount' => $game_item->price + $fee - $discount,
                'subtotal' => $game_item->price + $fee - $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => strtolower($result['data']['status']),
                'voucher_discount' => $discount,
                'voucher_code' => '-',
                'game_transaction_number' => '',
                'game_transaction_status' => 1,
                'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
              ];
              DB::table('transaction')->insert($tr_data);

              $tr_detail = [
                'detail_code' => generateFiledCode('TRD'),
                'transaction_code' => $transaction_code,
                'item_code' => $game_item->code,
                'price' => $game_item->price,
                'qty' => 1,
                'total' => $game_item->price,
                'userid' => $request->userid,
                'username' => $request->username,
                'validation_token' => $request->validation_token,
                'game_title' => $game->title,
                'item_title' => $game_item->title,
              ];
              DB::table('transaction_detail')->insert($tr_detail);

              $dec_data = [
                'transaction_code' => generateOrderCode('VGN'),
                'users_code' => $request->users_code,
                'email' => $request->email,
                'total_amount' => -$game_item->price + $fee + $discount,
                'subtotal' => -$game_item->price + $fee + $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => strtolower($result['data']['status']),
                'voucher_discount' => $discount,
                'voucher_code' => $voucher_code,
                'type' => 'topup',
                'game_transaction_number' => '',
                'game_transaction_status' => 1,
                'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
                'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
              ];
              DB::table('transaction')->insert($dec_data);

              // DB::table('users_balance')->where('users_balance_code', $cek['data']->users_balance_code)->update(['users_balance' => ($cek['data']->users_balance - ($game_item->price + $fee - $discount))]);
              // DB::commit();

              Mail::to($request->email)->send(new SuccessOrderItem($mail_data));
              return ['success' => true, 'data' => $tr_detail];
            } else {
              DB::rollBack();

              DB::beginTransaction();
              $cek = checkBalanceUser($users_code, ($game_item->price - $discount));
              DB::table('users_balance')
                ->where('users_balance_code', $cek['data']->users_balance_code)
                ->update(['users_balance' => ($cek['data']->users_balance + ($game_item->price + $fee - $discount))]);

              $tr_data = [
                'transaction_code' => $transaction_code,
                'users_code' => $request->users_code,
                'email' => $request->email,
                'total_amount' => $game_item->price + $fee - $discount,
                'subtotal' => $game_item->price + $fee - $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'failed',
                'voucher_discount' => $discount,
                'voucher_code' => '-',
                'game_transaction_number' => '',
                'game_transaction_status' => 1,
                'remaining_balance' => $cek['data']->users_balance + $game_item->price + $fee - $discount,
                'created_at' => date('Y-m-d H:i:s', strtotime('+10 seconds'))
              ];
              DB::table('transaction')->insert($tr_data);

              $tr_detail = [
                'detail_code' => generateFiledCode('TRD'),
                'transaction_code' => $transaction_code,
                'item_code' => $game_item->code,
                'price' => $game_item->price,
                'qty' => 1,
                'total' => $game_item->price,
                'userid' => $request->userid,
                'username' => $request->username,
                'validation_token' => $request->validation_token,
                'game_title' => $game->title,
                'item_title' => $game_item->title,
              ];
              DB::table('transaction_detail')->insert($tr_detail);

              $dec_data = [
                'transaction_code' => generateOrderCode('VGN'),
                'users_code' => $request->users_code,
                'email' => $request->email,
                'total_amount' => -$game_item->price + $fee + $discount,
                'subtotal' => -$game_item->price + $fee + $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'success',
                'voucher_discount' => $discount,
                'voucher_code' => $voucher_code,
                'type' => 'topup',
                'game_transaction_number' => '',
                'game_transaction_status' => 1,
                'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
                'remaining_balance' => $cek['data']->users_balance
              ];
              DB::table('transaction')->insert($dec_data);

              $inc_data = [
                'transaction_code' => generateOrderCode('VGN'),
                'users_code' => $request->users_code,
                'email' => $request->email,
                'total_amount' => $game_item->price + $fee + $discount,
                'subtotal' => $game_item->price + $fee + $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'success',
                'voucher_discount' => $discount,
                'voucher_code' => $voucher_code,
                'type' => 'topup',
                'game_transaction_number' => '',
                'game_transaction_status' => 1,
                'game_transaction_message' => 'Pengembalian dana Transaksi gagal ' . $transaction_code,
                'remaining_balance' => $cek['data']->users_balance
              ];
              DB::table('transaction')->insert($inc_data);

              DB::commit();

              return [
                'success' => false,
                'msg' => $result['msg']
              ];
            }
          }

          # Api games order
          if ($game_item->from == 'apigames') {
            $user_id = $request->userid;
            $result = Apigames::orderApigames($transaction_code, $game_item->ag_code, $user_id);
            saveLog('CALLBACK ORDER PRODUCT', $transaction_code, $result, 'Data from Apigames');
            if ($result['data']['status'] == 1) {
              $orderStatus = $result['data']['data']['status'];

              $statusTransaction = [
                'Sukses' => 1,
                'Pending' => 2,
                'Proses' => 3,
                'Sukses Sebagian' => 4,
                'Validasi Provider' => 5,
                'Gagal' => 6
              ];

              if ($orderStatus === 'Sukses') {
                $tr_data = [
                  'transaction_code' => $transaction_code,
                  'users_code' => $users_code,
                  'email' => $user_detail->email,
                  'total_amount' => $game_item->price + $fee - $discount,
                  'subtotal' => $game_item->price + $fee - $discount,
                  'fee' => 0,
                  'transaction_url' => '#',
                  'from' => $game_item->from,
                  'payment_method' => $pm->pm_code,
                  // 'no_reference' => $transaction_code,
                  'status' => 'success',
                  'voucher_discount' => $discount,
                  'voucher_code' => '-',
                  'game_transaction_number' => $result['data']['data']['trx_id'],
                  'game_transaction_status' => $statusTransaction[$orderStatus],
                  'game_transaction_message' => $result['data']['data']['message'],
                  'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
                ];
                DB::table('transaction')->insert($tr_data);
                $dec_data = [
                  'transaction_code' => generateOrderCode('VGN'),
                  'users_code' => $users_code,
                  'email' => $user_detail->email,
                  'total_amount' => -$game_item->price + $fee + $discount,
                  'subtotal' => -$game_item->price + $fee + $discount,
                  'fee' => 0,
                  'transaction_url' => '#',
                  'from' => $game_item->from,
                  'payment_method' => $pm->pm_code,
                  // 'no_reference' => $transaction_code,
                  'status' => 'success',
                  'voucher_discount' => $discount,
                  'voucher_code' => $voucher_code,
                  'game_transaction_number' => $result['data']['data']['trx_id'],
                  'game_transaction_status' => $statusTransaction[$orderStatus],
                  'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
                  'type' => 'topup',
                  'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
                ];
                // dd($dec_data);
                DB::table('transaction')->insert($dec_data);
                $tr_detail = [
                  'detail_code' => generateFiledCode('TRD'),
                  'transaction_code' => $transaction_code,
                  'game_code' => $game_item->game_code,
                  'item_code' => $game_item->code,
                  'price' => $game_item->price,
                  'qty' => 1,
                  'total' => $game_item->price,
                  'userid' => $request->userid,
                  'username' => $request->username,
                  'validation_token' => $request->validation_token,
                  'game_title' => $game->title,
                  'item_title' => $game_item->title,
                ];
                DB::table('transaction_detail')->insert($tr_detail);

                DB::table('users_balance')->where('users_balance_code', $cek['data']->users_balance_code)->update(['users_balance' => ($cek['data']->users_balance - ($game_item->price + $fee - $discount))]);
                DB::commit();

                Mail::to($request->email)->send(new SuccessOrderItem($mail_data));
                Log::info('SUCCESS ORDER APIGAMES => ' . json_encode($result));
                return ['success' => true, 'data' => $tr_detail];
              }

              if ($orderStatus === 'Sukses Sebagian') {
                $tr_data = [
                  'transaction_code' => $transaction_code,
                  'users_code' => $users_code,
                  'email' => $user_detail->email,
                  'total_amount' => $game_item->price + $fee - $discount,
                  'subtotal' => $game_item->price + $fee - $discount,
                  'fee' => $fee,
                  'transaction_url' => '#',
                  'from' => $game_item->from,
                  'payment_method' => $pm->pm_code,
                  // 'no_reference' => $transaction_code,
                  'status' => 'success',
                  'voucher_discount' => $discount,
                  'voucher_code' => '-',
                  'game_transaction_number' => $result['data']['data']['trx_id'],
                  'game_transaction_status' => $statusTransaction[$orderStatus],
                  'game_transaction_message' => $result['data']['data']['message'],
                  'remaining_balance' => $cek['data']->users_balance - ($game_item->price + $fee) - $discount
                ];
                DB::table('transaction')->insert($tr_data);
                $dec_data = [
                  'transaction_code' => generateOrderCode('VGN'),
                  'users_code' => $users_code,
                  'email' => $user_detail->email,
                  'total_amount' => -$game_item->price + $fee + $discount,
                  'subtotal' => -$game_item->price + $fee + $discount,
                  'fee' => 0,
                  'transaction_url' => '#',
                  'from' => $game_item->from,
                  'payment_method' => $pm->pm_code,
                  // 'no_reference' => $transaction_code,
                  'status' => 'success',
                  'voucher_discount' => $discount,
                  'voucher_code' => $voucher_code,
                  'game_transaction_number' => $result['data']['data']['trx_id'],
                  'game_transaction_status' => $statusTransaction[$orderStatus],
                  'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
                  'type' => 'topup',
                  'remaining_balance' => $cek['data']->users_balance - $game_item->price - $discount
                ];
                // dd($dec_data);
                DB::table('transaction')->insert($dec_data);
                $tr_detail = [
                  'detail_code' => generateFiledCode('TRD'),
                  'transaction_code' => $transaction_code,
                  'game_code' => $game_item->game_code,
                  'item_code' => $game_item->code,
                  'price' => $game_item->price,
                  'qty' => 1,
                  'total' => $game_item->price,
                  'userid' => $request->userid,
                  'username' => $request->username,
                  'validation_token' => $request->validation_token,
                  'game_title' => $game->title,
                  'item_title' => $game_item->title,
                ];
                DB::table('transaction_detail')->insert($tr_detail);

                DB::table('users_balance')->where('users_balance_code', $cek['data']->users_balance_code)->update(['users_balance' => ($cek['data']->users_balance - ($game_item->price + $fee - $discount))]);
                DB::commit();

                Mail::to($request->email)->send(new SuccessOrderItem($mail_data));
                Log::info('SUCCESS ORDER APIGAMES => ' . json_encode($result));
              }

              if ($orderStatus === 'Gagal') {
                $tr_data = [
                  'transaction_code' => $transaction_code,
                  'users_code' => $users_code,
                  'email' => $user_detail->email,
                  'total_amount' => $game_item->price + $fee - $discount,
                  'subtotal' => $game_item->price + $fee - $discount,
                  'fee' => 0,
                  'transaction_url' => '#',
                  'from' => $game_item->from,
                  'payment_method' => $pm->pm_code,
                  // 'no_reference' => $transaction_code,
                  'status' => 'failed',
                  'voucher_discount' => $discount,
                  'voucher_code' => '-',
                  'game_transaction_number' => $result['data']['data']['trx_id'],
                  'game_transaction_status' => $statusTransaction[$orderStatus],
                  'game_transaction_message' => $result['data']['data']['message'],
                  'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
                ];
                DB::table('transaction')->insert($tr_data);

                $tr_detail = [
                  'detail_code' => generateFiledCode('TRD'),
                  'transaction_code' => $transaction_code,
                  'game_code' => $game_item->game_code,
                  'item_code' => $game_item->code,
                  'price' => $game_item->price,
                  'qty' => 1,
                  'total' => $game_item->price,
                  'userid' => $request->userid,
                  'username' => $request->username,
                  'validation_token' => $request->validation_token,
                  'game_title' => $game->title,
                  'item_title' => $game_item->title,
                ];
                DB::table('transaction_detail')->insert($tr_detail);

                $dec_data = [
                  'transaction_code' => generateOrderCode('VGN'),
                  'users_code' => $users_code,
                  'email' => $user_detail->email,
                  'total_amount' => -$game_item->price + $fee + $discount,
                  'subtotal' => -$game_item->price + $fee + $discount,
                  'fee' => 0,
                  'transaction_url' => '#',
                  'from' => $game_item->from,
                  'payment_method' => $pm->pm_code,
                  // 'no_reference' => $transaction_code,
                  'status' => 'success',
                  'voucher_discount' => $discount,
                  'voucher_code' => $voucher_code,
                  'game_transaction_number' => $result['data']['data']['trx_id'] ?? '',
                  'game_transaction_status' => $statusTransaction[$orderStatus],
                  'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
                  'type' => 'topup',
                  'remaining_balance' => $cek['data']->users_balance
                ];
                DB::table('transaction')->insert($dec_data);

                $inc_data = [
                  'transaction_code' => generateOrderCode('VGN'),
                  'users_code' => $users_code,
                  'email' => $user_detail->email,
                  'total_amount' => $game_item->price + $fee + $discount,
                  'subtotal' => $game_item->price + $fee + $discount,
                  'fee' => 0,
                  'transaction_url' => '#',
                  'from' => $game_item->from,
                  'payment_method' => $pm->pm_code,
                  // 'no_reference' => $transaction_code,
                  'status' => 'success',
                  'voucher_discount' => $discount,
                  'voucher_code' => $voucher_code,
                  'game_transaction_number' => $result['data']['data']['trx_id'] ?? '',
                  'game_transaction_status' => $statusTransaction[$orderStatus],
                  'game_transaction_message' => 'Pengembalian Dana Transaksi gagal ' . $transaction_code,
                  'type' => 'topup',
                  'remaining_balance' => $cek['data']->users_balance + $game_item->price + $fee - $discount,
                  'created_at' => date('Y-m-d H:i:s', strtotime('+10 seconds'))
                ];
                DB::table('transaction')->insert($inc_data);

                DB::commit();

                Log::info('FAILED ORDER APIGAMES => ' . json_encode($result));
              }

              if ($orderStatus === 'Validasi Provider') {
                $tr_data = [
                  'transaction_code' => $transaction_code,
                  'users_code' => $users_code,
                  'email' => $user_detail->email,
                  'total_amount' => $game_item->price + $fee - $discount,
                  'subtotal' => $game_item->price + $fee - $discount,
                  'fee' => $fee,
                  'transaction_url' => '#',
                  'from' => $game_item->from,
                  'payment_method' => $pm->pm_code,
                  // 'no_reference' => $transaction_code,
                  'status' => 'processing',
                  'voucher_discount' => $discount,
                  'voucher_code' => '-',
                  'game_transaction_number' => $result['data']['data']['trx_id'],
                  'game_transaction_status' => $statusTransaction[$orderStatus],
                  'game_transaction_message' => $result['data']['data']['message'],
                  'remaining_balance' => $cek['data']->users_balance - $game_item->price - $discount
                ];
                DB::table('transaction')->insert($tr_data);

                $tr_detail = [
                  'detail_code' => generateFiledCode('TRD'),
                  'transaction_code' => $transaction_code,
                  'game_code' => $game_item->game_code,
                  'item_code' => $game_item->code,
                  'price' => $game_item->price,
                  'qty' => 1,
                  'total' => $game_item->price,
                  'userid' => $request->userid,
                  'username' => $request->username,
                  'validation_token' => $request->validation_token,
                  'game_title' => $game->title,
                  'item_title' => $game_item->title,
                ];
                DB::table('transaction_detail')->insert($tr_detail);
                DB::commit();

                Log::info('Validasi Provider APIGAMES => ' . json_encode($result));
              }

              if ($orderStatus === 'Proses') {
                $tr_data = [
                  'transaction_code' => $transaction_code,
                  'users_code' => $users_code,
                  'email' => $user_detail->email,
                  'total_amount' => $game_item->price + $fee - $discount,
                  'subtotal' => $game_item->price + $fee - $discount,
                  'fee' => $fee,
                  'transaction_url' => '#',
                  'from' => $game_item->from,
                  'payment_method' => $pm->pm_code,
                  // 'no_reference' => $transaction_code,
                  'status' => 'failed',
                  'voucher_discount' => $discount,
                  'voucher_code' => '-',
                  'game_transaction_number' => $result['data']['data']['trx_id'],
                  'game_transaction_status' => $statusTransaction[$orderStatus],
                  'game_transaction_message' => $result['data']['data']['message'],
                  'remaining_balance' => $cek['data']->users_balance - $game_item->price - $discount
                ];
                DB::table('transaction')->insert($tr_data);

                $tr_detail = [
                  'detail_code' => generateFiledCode('TRD'),
                  'transaction_code' => $transaction_code,
                  'game_code' => $game_item->game_code,
                  'item_code' => $game_item->code,
                  'price' => $game_item->price,
                  'qty' => 1,
                  'total' => $game_item->price,
                  'userid' => $request->userid,
                  'username' => $request->username,
                  'validation_token' => $request->validation_token,
                  'game_title' => $game->title,
                  'item_title' => $game_item->title,
                ];
                DB::table('transaction_detail')->insert($tr_detail);
                DB::commit();

                Log::info('PROCESSING APIGAMES => ' . json_encode($result));
              }

              if ($orderStatus === 'Pending') {
                $tr_data = [
                  'transaction_code' => $transaction_code,
                  'users_code' => $users_code,
                  'email' => $user_detail->email,
                  'total_amount' => $game_item->price + $fee - $discount,
                  'subtotal' => $game_item->price + $fee - $discount,
                  'fee' => $fee,
                  'transaction_url' => '#',
                  'from' => $game_item->from,
                  'payment_method' => $pm->pm_code,
                  // 'no_reference' => $transaction_code,
                  'status' => 'waiting',
                  'voucher_discount' => $discount,
                  'voucher_code' => '-',
                  'game_transaction_number' => $result['data']['data']['trx_id'],
                  'game_transaction_status' => $statusTransaction[$orderStatus],
                  'game_transaction_message' => $result['data']['data']['message'],
                  'remaining_balance' => $cek['data']->users_balance - ($game_item->price + $fee) - $discount
                ];
                DB::table('transaction')->insert($tr_data);

                $tr_detail = [
                  'detail_code' => generateFiledCode('TRD'),
                  'transaction_code' => $transaction_code,
                  'game_code' => $game_item->game_code,
                  'item_code' => $game_item->code,
                  'price' => $game_item->price,
                  'qty' => 1,
                  'total' => $game_item->price,
                  'userid' => $request->userid,
                  'username' => $request->username,
                  'validation_token' => $request->validation_token,
                  'game_title' => $game->title,
                  'item_title' => $game_item->title,
                ];
                DB::table('transaction_detail')->insert($tr_detail);
                DB::commit();

                Log::info('PENDING ORDER APIGAMES => ' . json_encode($result));
                saveLog('ORDER APIGAMES', $transaction_code, $request->all(), 'Order Pending');
              }
              return ['success' => true, 'data' => $tr_detail];
            } else if ($result['data']['status'] == 0) {
              DB::rollBack();
              saveLog('ORDER APIGAMES', $transaction_code, $request->all(), 'Order Failed');
              return ['success' => false, 'msg' => $result['data']['error_msg']];
            }
          }

          # Api games order
          if ($game_item->from == 'lapakgaming') {
            $user_id = $request->userid;
            $result = LapakGaming::orderGame($all_fields, $game_item, $transaction_code);
            saveLog('ORDER PRODUCT LAPAKGAMING', $transaction_code, $result, 'Data from LapakGaming');

            if ($result['success'] == 'SUCCESS') {
              $tr_data = [
                'transaction_code' => $transaction_code,
                'users_code' => $users_code,
                'email' => $user_detail->email,
                'total_amount' => $game_item->price + $fee - $discount,
                'subtotal' => $game_item->price + $fee - $discount,
                'fee' => $fee,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'processing',
                'voucher_discount' => $discount,
                'voucher_code' => '-',
                'game_transaction_number' => $result['data']['tid'],
                'game_transaction_status' => 0,
                'game_transaction_message' => $result['success'],
                'remaining_balance' => $cek['data']->users_balance - $game_item->price + $fee - $discount
              ];
              DB::table('transaction')->insert($tr_data);
              $dec_data = [
                'transaction_code' => generateOrderCode('VGN'),
                'users_code' => $users_code,
                'email' => $user_detail->email,
                'total_amount' => -$game_item->price + abs($fee) + $discount,
                'subtotal' => -$game_item->price + abs($fee) + $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'success',
                'voucher_discount' => $discount,
                'voucher_code' => $voucher_code,
                'game_transaction_number' => $result['data']['tid'],
                'game_transaction_status' => 0,
                'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
                'type' => 'topup',
                'remaining_balance' => $cek['data']->users_balance - ($game_item->price + $fee - $discount)
              ];
              // dd($dec_data);
              DB::table('transaction')->insert($dec_data);
              $tr_detail = [
                'detail_code' => generateFiledCode('TRD'),
                'transaction_code' => $transaction_code,
                'game_code' => $game_item->game_code,
                'item_code' => $game_item->code,
                'price' => $game_item->price,
                'qty' => 1,
                'total' => $game_item->price,
                'userid' => $request->userid,
                'username' => $request->username,
                'validation_token' => $request->validation_token,
                'game_title' => $game->title,
                'item_title' => $game_item->title,
              ];
              DB::table('transaction_detail')->insert($tr_detail);

              DB::table('users_balance')->where('users_balance_code', $cek['data']->users_balance_code)->update(['users_balance' => ($cek['data']->users_balance - ($game_item->price + $fee - $discount))]);
              DB::commit();

              // Mail::to($request->email)->send(new SuccessOrderItem($mail_data));
              Log::info('SUCCESS ORDER LAPAKGAMING => ' . json_encode($result));
              return ['success' => true, 'data' => $tr_detail];
            } elseif ($result['success'] == 'QUEUE') {
              $tr_data = [
                'transaction_code' => $transaction_code,
                'users_code' => $users_code,
                'email' => $user_detail->email,
                'total_amount' => $game_item->price + $fee - $discount,
                'subtotal' => $game_item->price + $fee - $discount,
                'fee' => $fee,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'processing',
                'voucher_discount' => $discount,
                'voucher_code' => '-',
                'game_transaction_number' => '-',
                'game_transaction_status' => 0,
                'game_transaction_message' => $result['success'],
                'remaining_balance' => $cek['data']->users_balance - ($game_item->price + $fee - $discount)
              ];
              DB::table('transaction')->insert($tr_data);
              $dec_data = [
                'transaction_code' => generateOrderCode('VGN'),
                'users_code' => $users_code,
                'email' => $user_detail->email,
                'total_amount' => -$game_item->price + abs($fee) + $discount,
                'subtotal' => -$game_item->price + abs($fee) + $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'success',
                'voucher_discount' => $discount,
                'voucher_code' => $voucher_code,
                'game_transaction_number' => '-',
                'game_transaction_status' => 0,
                'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
                'type' => 'topup',
                'remaining_balance' => $cek['data']->users_balance - ($game_item->price + $fee - $discount)
              ];
              // dd($dec_data);
              DB::table('transaction')->insert($dec_data);
              $tr_detail = [
                'detail_code' => generateFiledCode('TRD'),
                'transaction_code' => $transaction_code,
                'game_code' => $game_item->game_code,
                'item_code' => $game_item->code,
                'price' => $game_item->price,
                'qty' => 1,
                'total' => $game_item->price,
                'userid' => $request->userid,
                'username' => $request->username,
                'validation_token' => $request->validation_token,
                'game_title' => $game->title,
                'item_title' => $game_item->title,
              ];
              DB::table('transaction_detail')->insert($tr_detail);

              DB::table('users_balance')->where('users_balance_code', $cek['data']->users_balance_code)->update(['users_balance' => ($cek['data']->users_balance - ($game_item->price + $fee - $discount))]);
              DB::commit();

              $result['transaction_code'] = $transaction_code;
              Log::info('QUEUE ORDER LAPAKGAMING => ' . json_encode($result));

              LapakGaming::queueOrder($transaction_code, 'saldo');

              return ['success' => true, 'data' => $tr_detail];
            } else {
              DB::rollBack();
              saveLog('ORDER LAPAKGAMING', $transaction_code, $request->all(), 'Order Failed');

              $tr_data = [
                'transaction_code' => $transaction_code,
                'users_code' => $users_code,
                'email' => $user_detail->email,
                'total_amount' => $game_item->price + $fee - $discount,
                'subtotal' => $game_item->price + $fee - $discount,
                'fee' => $fee,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'failed',
                'voucher_discount' => $discount,
                'voucher_code' => '-',
                'game_transaction_number' => '-',
                'game_transaction_status' => 0,
                'game_transaction_message' => $result['success'],
                'remaining_balance' => $cek['data']->users_balance - ($game_item->price + $fee - $discount)
              ];
              DB::table('transaction')->insert($tr_data);

              $tr_detail = [
                'detail_code' => generateFiledCode('TRD'),
                'transaction_code' => $transaction_code,
                'game_code' => $game_item->game_code,
                'item_code' => $game_item->code,
                'price' => $game_item->price,
                'qty' => 1,
                'total' => $game_item->price,
                'userid' => $request->userid,
                'username' => $request->username,
                'validation_token' => $request->validation_token,
                'game_title' => $game->title,
                'item_title' => $game_item->title,
              ];
              DB::table('transaction_detail')->insert($tr_detail);

              $dec_data = [
                'transaction_code' => generateOrderCode('VGN'),
                'users_code' => $users_code,
                'email' => $user_detail->email,
                'total_amount' => -$game_item->price + abs($fee) + $discount,
                'subtotal' => -$game_item->price + abs($fee) + $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'success',
                'voucher_discount' => $discount,
                'voucher_code' => $voucher_code,
                'game_transaction_number' => '-',
                'game_transaction_status' => 0,
                'game_transaction_message' => 'Pembayaran Transaksi ' . $transaction_code,
                'type' => 'topup',
                'remaining_balance' => $cek['data']->users_balance - ($game_item->price + $fee - $discount)
              ];
              DB::table('transaction')->insert($dec_data);

              $inc_data = [
                'transaction_code' => generateOrderCode('VGN'),
                'users_code' => $users_code,
                'email' => $user_detail->email,
                'total_amount' => $game_item->price + $fee + $discount,
                'subtotal' => $game_item->price + $fee + $discount,
                'fee' => 0,
                'transaction_url' => '#',
                'from' => $game_item->from,
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $transaction_code,
                'status' => 'success',
                'voucher_discount' => $discount,
                'voucher_code' => $voucher_code,
                'game_transaction_number' => '-',
                'game_transaction_status' => 0,
                'game_transaction_message' => 'Pengembalian Dana Transaksi gagal ' . $transaction_code,
                'type' => 'topup',
                'remaining_balance' => $cek['data']->users_balance,
                'created_at' => date('Y-m-d H:i:s', strtotime('+10 seconds'))
              ];
              DB::table('transaction')->insert($inc_data);

              DB::commit();

              Log::info('FAILED ORDER LAPAKGAMING => ' . json_encode($result));

              return ['success' => true, 'data' => $tr_detail];
            }
          }
        }
      } elseif (!$users && $pm->from == 'saldo') {
        DB::rollBack();
        return [
          'success' => false,
          'msg' => 'Payment method invalid, please register an account and top up your balance',
        ];
      }
    } catch (QueryException $e) {
      DB::rollBack();
      $errorCode = $e->errorInfo[1];
      if ($errorCode == 1062) {
        // a duplicate entry problem, try again
        return $this->payTransaction($request);
      } else {
        Log::alert($errorCode);
      }

      return ['success' => false, 'message' => $e->getMessage()];
    } catch (\Exception $e) {
      DB::rollBack();
      return [
        'success' => false,
        'msg' => $e->getMessage()
      ];
    }
  }

  public function callbackPayment($request)
  {
    try {
      $order_id = '';
      if (isset($request->order_id)) {
        $id = explode('-', $request->order_id);
        $order_id = $id[0];
      } else if (isset($request->merchantOrderId)) {
        //duitku
        $order_id = $request->merchantOrderId;
      } else if (isset($request->data)) {
        $order_id = $request->data['ref_id'];
      }

      $order = DB::table('transaction')->where('transaction_code', $order_id)->first();
      if (empty($order)) {
        Log::error('[CALLBACK] => Order not found ' . $order_id);
        saveLog('CALLBACK', $order_id, $request->all(), 'Order  ' . $order_id . ' not found');
        return ['success' => false, 'msg' => 'order tidak ditemukan'];
      }

      if ($order->status == 'success') {
        return ['success' => true, 'msg' => 'Transaksi sudah diproses sebelumnya'];
      }

      $payment_method = DB::table('payment_method')->where('pm_code', $order->payment_method)->first();
      if (empty($payment_method)) {
        Log::error('[CALLBACK] => Payment method not found');
        saveLog('CALLBACK', $order_id, $request->all(), 'Payment method not found');
        return ['success' => false, 'msg' => 'payment method tidak ditemukan'];
      }


      saveLog('CALLBACK', $order_id, $request->all(), 'CALLBACK CALLED');
      switch ($payment_method->from) {
        case 'midtrans':
          $status = $request->transaction_status;
          if ($status == 'pending') {
            $status = 'waiting';
            DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
            saveLog('CALLBACK', $order_id, $request->all(), 'Transaction Pending');
            return ['success' => true, 'msg' => 'Transaksi pending'];
          } else if ($status == 'cancel') {
            $status = 'cancel';
            DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
            saveLog('CALLBACK', $order_id, $request->all(), 'Transaction Canceled');
            return ['success' => true, 'msg' => 'Transaksi dibatalkan'];
          } else if ($status == 'failure' || $status == 'deny') {
            $status = 'failed';
            DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
            saveLog('CALLBACK', $order_id, $request->all(), 'Transaction Failed');
            return ['success' => true, 'msg' => 'Transaksi gagal dilakukan'];
          } else if ($status == 'expire') {
            $status = 'expired';
            DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
            saveLog('CALLBACK', $order_id, $request->all(), 'Transaction Expired');
            return ['success' => true, 'msg' => 'Transaksi expired'];
          }

          # CALL TOPUP

          if ($order->type == 'topup') {
            if ($status == 'settlement' || $status == 'capture') {
              if ($request->fraud_status == 'accept') {
                DB::beginTransaction();
                $user_balance = DB::table('users_balance')->where('users_code', $order->users_code)->first();
                DB::table('users_balance')->where('users_code', $order->users_code)
                  ->increment('users_balance', $order->subtotal);
                DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'success', 'remaining_balance' => $user_balance->users_balance + $order->subtotal, 'created_at' => date('Y-m-d H:i:s')]);

                $user = DB::table('users')->where('users_code', $order->users_code)->first();
                $total_amount_formatted = 'Rp. ' . number_format($order->total_amount, 0, ',', '.');
                $nominal_topup_formatted = 'Rp. ' . number_format($order->subtotal, 0, ',', '.');

                $mail_data = [
                  'userid' => $user->name,
                  'order_id' => $order_id,
                  'order_date' => Carbon::parse($order->created_at)->format('d-m-Y H:i:s'),
                  'pay_method' => $payment_method->pm_title,
                  'pay_status' => 'Berhasil',
                  'total_amount' => $total_amount_formatted,
                  'nominal' => $nominal_topup_formatted
                ];
                DB::commit();

                Mail::to($order->email)->send(new SuccessTopUp($mail_data));

                saveLog('CALLBACK TOPUP', $order_id, $request->all(), 'Topup Success');
                return ['success' => true, 'msg' => 'Saldo berhasil diperbarui'];
              }
            }
          }
          # CALL ORDER VOUCHER
          if ($order->type == 'order_voucher') {
            if ($status == 'settlement' || $status == 'capture') {
              if ($request->fraud_status == 'accept') {
                $order_detail = DB::table('transaction_detail')->where('transaction_code', $order_id)->get();
                $item_count = count($order_detail);
                $redeem_codes = [];
                foreach ($order_detail as $order_item) {
                  $order_qty = $order_item->qty;
                  $voucher_games_collect = DB::table('games_item_voucher')
                    ->where('games_item_code', $order_item->item_code)
                    ->where('voucher_status', 1)
                    ->limit($order_qty)
                    ->get();
                  if ($voucher_games_collect) {
                    $redeem_codes_item = $voucher_games_collect->pluck('redeem_code')->toArray();

                    if ($order_qty > 1) {
                      $redeem_codes[] = implode(',', $redeem_codes_item);
                    } else {
                      $redeem_codes[] = $redeem_codes_item[0];
                    }

                    DB::table('transaction_detail')
                      ->where('transaction_code', $order_id)
                      ->where('item_code', $order_item->item_code)
                      ->update(['redeem_code' => implode(',', $redeem_codes_item)]);

                    DB::table('games_item_voucher')
                      ->where('games_item_code', $order_item->item_code)
                      ->whereIn('redeem_code', $redeem_codes_item)
                      ->update(['voucher_status' => 0]);
                  }
                }

                DB::table('transaction')
                  ->where('transaction_code', $order_id)
                  ->update([
                    'game_transaction_status' => 1,
                    'status' => 'success'
                  ]);

                $transaction = DB::table('transaction')->where('transaction_code', $order_id)->first();
                $emailCustomer = $transaction->email;
                $nameCustomer = $transaction->email;
                $str_email = explode('@', $emailCustomer);
                $nameCustomer = $str_email[0];
                $t_detail = DB::table('transaction_detail')->where('transaction_code', $order_id)->get();

                $user = DB::table('users')->where('users_code', $transaction->users_code)->first();
                if ($user) {
                  $nameCustomer = $user->name;
                }

                $redeem_codes_all = [];
                foreach ($t_detail as $detail) {
                  $game_item = DB::table('games_item')->where('code', $detail->item_code)->first();

                  $redeem_codes = $detail->redeem_code;
                  if ($redeem_codes && strpos($redeem_codes, ',') !== false) {
                    $redeem_codes_individual = explode(',', $redeem_codes);
                    $redeem_codes = $redeem_codes_individual;
                  } else {
                    $redeem_codes = [$redeem_codes];
                  }

                  $game = DB::table('games')->where('code', $game_item->game_code)->first();
                  $redeem_codes_all[] = [
                    'game' => $game->title,
                    'title' => $game_item->title,
                    'redeem_codes' => $redeem_codes
                  ];
                }
                $url = env('EMAIL_DOMAIN');
                $data = [
                  'name' => $nameCustomer,
                  'email' => $emailCustomer,
                  'redeem_codes' => $redeem_codes_all,
                  'order_id' => $order_id,
                  'url' => $url
                ];
                // dd($data);
                Mail::to($emailCustomer)->send(new RedeemCode($data));

                saveLog('CALLBACK ORDER VOUCHER', $order_id, $request->all(), 'Transaction Success');
                return ['success' => true];
              }
            }
          }
          # CALL ORDER ITEM
          if ($order->type == 'order_item') {
            if ($status == 'settlement' || $status == 'capture') {
              if ($request->fraud_status == 'accept') {
                $transaction_detail = DB::table('transaction_detail')->where('transaction_code', $order_id)->first();
                $game = DB::table('games')->where('code', $transaction_detail->game_code)->first();
                $game_item = DB::table('games_item')->where('code', $transaction_detail->item_code)->first();
                $total_amount_formatted = 'Rp. ' . number_format($order->total_amount, 0, ',', '.');
                $str_userid = $transaction_detail->userid;
                if (strpos($str_userid, '#') !== false) {
                  $user_ids = explode('#', $str_userid);
                  $merged_user_id = implode('(', $user_ids) . ')';
                } else {
                  $merged_user_id = $str_userid;
                }

                $mail_data = [
                  'game' => $game->title,
                  'game_item' => $game_item->title,
                  'userid' => $merged_user_id,
                  'order_id' => $order_id,
                  // 'no_reference' => $order->no_reference,
                  'order_date' => $order->created_at,
                  'pay_method' => $payment_method->pm_title,
                  'pay_status' => 'Sudah di proses',
                  'total_amount' => $total_amount_formatted
                ];
                // Mail::to($order->email)->send(new SuccessOrderItem($mail_data));
                // DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'success']);

                # Unipin order
                if ($order->from == 'unipin') {
                  $tdetail = DB::table('transaction_detail')->where('transaction_code', $order_id)->first();
                  $denom = DB::table('games_item')->where('code', $tdetail->item_code)->first();
                  $result = Unipin::orderUnipin($tdetail->transaction_code, $tdetail->game_code, $tdetail->validation_token, $denom->denomination_id);

                  Log::info('RESULT ORDER UNIPIN => ' . json_encode($result));
                  saveLog('CALLBACK ORDER PRODUCT', $order_id, $result, 'Data From Unipin');
                  if (isset($result['success']) && $result['success'] === 1) {
                    DB::table('transaction')->where('transaction_code', $order_id)->update([
                      'game_transaction_number' => $result['data']['transaction_number'],
                      'game_transaction_status' => 1,
                      'status' => 'success'
                    ]);
                    Mail::to($order->email)->send(new SuccessOrderItem($mail_data));
                  } else {
                    DB::table('transaction')->where('transaction_code', $order_id)->update([
                      'game_transaction_status' => 2,
                      'status' => 'failed',
                      // PENDING
                      'game_transaction_message' => $result['data']['error']['message']
                    ]);
                  }
                  return ['success' => true];
                }
                // ORDER DIGIFLAZZ
                if ($order->from == 'digiflazz') {
                  $data = Digiflazz::order($order_id, $game_item->digi_code, $transaction_detail->userid);
                  saveLog('CALLBACK ORDER PRODUCT', $order_id, $data, 'Data From Digiflazz');
                  if ($data['data']['status'] == 'Sukses') {
                    DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
                      'status' => 'success',
                      'game_transaction_status' => 1,
                      'game_transaction_number' => isset($data['sn']) ? $data['sn'] : '',
                      // 'no_reference' => $data['trx_id'],
                    ]);
                    // DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
                    // 	'status' => 'success',
                    // 	'game_transaction_status' => 1,
                    // 	'game_transaction_number' => isset($data['sn']) ? $data['sn'] : '',
                    // 	'no_reference' => $data['trx_id'],
                    // ]);
                    return ['success' => true];
                  } elseif ($data['data']['status'] == 'Pending') {
                    DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
                      'status' => 'pending',
                      'game_transaction_status' => 2
                    ]);
                    // DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
                    // 	'status' => 'pending',
                    // 	'game_transaction_status' => 1
                    // ]);
                    return ['success' => true];
                  } elseif ($data['data']['status'] == 'Gagal') {
                    DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
                      'status' => 'failed',
                      'game_transaction_status' => 4
                    ]);
                    // DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
                    // 	'status' => 'failed',
                    // 	'game_transaction_status' => 1
                    // ]);
                    return ['success' => true];
                  } else {
                    DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
                      'status' => 'pending'
                    ]);
                    // DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
                    // 	'status' => 'pending'
                    // ]);
                    return ['success' => true];
                  }
                }
                # Api games order
                if ($order->from == 'apigames') {
                  $tdetail = DB::table('transaction_detail')->where('transaction_code', $order_id)->first();
                  $tgameid = DB::table('transaction_game_id')->where('transaction_code', $order_id)->get()->toArray();

                  $itemDetail = DB::table('games_item')->where('code', $tdetail->item_code)->first();
                  // $result = Apigames::orderApigames($tdetail->transaction_code, $itemDetail->ag_code, $tdetail->userid);
                  $uid = makeFieldsApigames($tgameid);
                  $result = Apigames::orderApigames($tdetail->transaction_code, $itemDetail->ag_code, $uid);
                  Log::info('RESULT ORDER APIGAMES => ' . json_encode($result));
                  saveLog('CALLBACK ORDER PRODUCT', $order_id, $result, 'Data From Apigames');

                  if ($result['data']['status'] == 1) {
                    $orderStatus = $result['data']['data']['status'];
                    if ($orderStatus == 'Sukses' || $orderStatus == 'Sukses Sebagian') {
                      DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'success', 'game_transaction_status' => 1]);
                      Mail::to($order->email)->send(new SuccessOrderItem($mail_data));
                    } elseif ($orderStatus == 'Pending') {
                      DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'waiting', 'game_transaction_status' => 2]);
                    } elseif ($orderStatus == 'Gagal') {
                      DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'failed', 'game_transaction_status' => 4]);
                    } elseif ($orderStatus == 'Proses') {
                      DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'processing', 'game_transaction_status' => 3]);
                    } elseif ($orderStatus == 'Validasi Provider') {
                      DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'processing', 'game_transaction_status' => 5]);
                    }
                    // Mail::to($order->email)->send(new SuccessOrderItem($mail_data));
                    return ['success' => true, 'data' => $result['data']];
                  } else if ($result['data']['status'] == 0) {
                    return ['success' => false, 'message' => $result['data']['error_msg']];
                  }
                }
                # LAPAKGAMING order
                if ($order->from == 'lapakgaming') {
                  $tdetail = DB::table('transaction_detail')->where('transaction_code', $order_id)->first();
                  $tgameid = LapakGaming::getGameID($order_id);
                  
                  $denom = DB::table('games_item')->where('code', $tdetail->item_code)->first();
                  // $result = LapakGaming::orderGame($tdetail->userid, $denom, $order_id);
                  $result = LapakGaming::orderGame($tgameid, $denom, $order_id);

                  Log::info('RESULT ORDER LAPAKGAMING => ' . json_encode($result));
                  saveLog('CALLBACK ORDER PRODUCT', $order_id, $result, 'Data From LAPAKGAMING');
                  if ($result['success'] == 'SUCCESS') {
                    DB::table('transaction')->where('transaction_code', $order_id)->update([
                      'game_transaction_number' => $result['data']['tid'],
                      'game_transaction_status' => 0,
                      'status' => 'processing'
                    ]);
                    // Mail::to($order->email)->send(new SuccessOrderItem($mail_data));
                  } elseif ($result['success'] == 'QUEUE') {
                    LapakGaming::queueOrder($order_id, 'direct');

                    DB::table('transaction')->where('transaction_code', $order_id)->update([
                      'game_transaction_number' => '-',
                      'game_transaction_status' => 0,
                      'status' => 'processing'
                    ]);
                  } else {
                    DB::table('transaction')->where('transaction_code', $order_id)->update([
                      'game_transaction_status' => 2,
                      'status' => 'failed',
                      // PENDING
                      'game_transaction_message' => $result['success']
                    ]);
                  }
                  return ['success' => true];
                }
              }
            }
          }
          break;
        case 'duitku':
          $status = $request->resultCode;
          if ($request->resultCode == 00) {
            $status = 'success';
          }
          if (!empty($order) && $order->status == 'success') {
            return ['success' => true, 'msg' => 'Transaksi sudah diproses sebelumnya'];
          }
          $t_status = $request->resultCode;
          if ($order->type == 'topup') {
            if ($t_status == 00) {
              DB::beginTransaction();
              # Update user balance
              $user_balance = DB::table('users_balance')->where('users_code', $order->users_code)->first();
              DB::table('users_balance')->where('users_code', $order->users_code)
                ->increment('users_balance', $order->subtotal);
              DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'success', 'remaining_balance' => $user_balance->users_balance + $order->subtotal, 'created_at' => date('Y-m-d H:i:s')]);

              $user = DB::table('users')->where('users_code', $order->users_code)->first();
              $total_amount_formatted = 'Rp. ' . number_format($order->total_amount, 0, ',', '.');
              $nominal_topup_formatted = 'Rp. ' . number_format($order->subtotal, 0, ',', '.');

              $mail_data = [
                'userid' => $user->name,
                'order_id' => $order_id,
                'order_date' => Carbon::parse($order->created_at)->format('d-m-Y H:i:s'),
                'pay_method' => $payment_method->pm_title,
                'pay_status' => 'Berhasil',
                'total_amount' => $total_amount_formatted,
                'nominal' => $nominal_topup_formatted
              ];
              DB::commit();

              Mail::to($order->email)->send(new SuccessTopUp($mail_data));

              saveLog('CALLBACK TOPUP', $order_id, $request->all(), 'TRANSACTION SUCCESS');
              return ['success' => true, 'msg' => 'Saldo berhasil diperbarui'];
            } else if ($t_status == 01) {
              $status = 'failed';
              DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);

              saveLog('CALLBACK TOPUP', $order_id, $request->all(), 'TRANSACTION FAILED');
              return ['success' => false, 'msg' => 'Transaksi Pending'];
            }
          }
          if ($order->type == 'order_voucher') {
            if ($t_status == 00) {

              $order_detail = DB::table('transaction_detail')->where('transaction_code', $order_id)->get();
              $item_count = count($order_detail);
              $redeem_codes = [];
              for ($i = 0; $i < $item_count; $i++) {
                $order_item = $order_detail[$i];
                $order_qty = $order_item->qty;
                $voucher_games_collect = DB::table('games_item_voucher')
                  ->where('games_item_code', $order_item->item_code)
                  ->where('voucher_status', 1)
                  ->limit($order_qty)
                  ->get();
                if ($voucher_games_collect) {
                  $redeem_codes_item = [];
                  foreach ($voucher_games_collect as $voucher_game) {
                    $redeem_codes_item[] = $voucher_game->redeem_code;
                  }

                  if ($order_qty > 1) {
                    $redeem_codes[] = implode(',', $redeem_codes_item);
                  } else {
                    $redeem_codes[] = $redeem_codes_item[0];
                  }

                  DB::table('transaction_detail')
                    ->where('transaction_code', $order_id)
                    ->where('item_code', $order_item->item_code)
                    ->update(['redeem_code' => implode(',', $redeem_codes_item)]);

                  DB::table('games_item_voucher')
                    ->where('games_item_code', $order_item->item_code)
                    ->whereIn('redeem_code', $redeem_codes_item)
                    ->update(['voucher_status' => 0]);
                }
              }

              DB::table('transaction')
                ->where('transaction_code', $order_id)
                ->update([
                  'game_transaction_status' => 1,
                  'status' => $status
                ]);

              $transaction = DB::table('transaction')->where('transaction_code', $order_id)->first();
              $emailCustomer = $transaction->email;
              $nameCustomer = $transaction->email;
              $str_email = explode('@', $emailCustomer);
              $nameCustomer = $str_email[0];
              $t_detail = DB::table('transaction_detail')->where('transaction_code', $order_id)->get();

              $user = DB::table('users')->where('users_code', $transaction->users_code)->first();
              if ($user) {
                $nameCustomer = $user->name;
              }

              $redeem_codes_all = [];
              foreach ($t_detail as $detail) {
                $game_item = DB::table('games_item')->where('code', $detail->item_code)->first();

                $redeem_codes = $detail->redeem_code;
                if ($redeem_codes && strpos($redeem_codes, ',') !== false) {
                  $redeem_codes_individual = explode(',', $redeem_codes);
                  $redeem_codes = $redeem_codes_individual;
                } else {
                  $redeem_codes = [$redeem_codes];
                }

                $game = DB::table('games')->where('code', $game_item->game_code)->first();
                $redeem_codes_all[] = [
                  'game' => $game->title,
                  'title' => $game_item->title,
                  'redeem_codes' => $redeem_codes,
                ];
              }
              $url = env('EMAIL_DOMAIN');
              $data = [
                'name' => $nameCustomer,
                'email' => $emailCustomer,
                'redeem_codes' => $redeem_codes_all,
                'order_id' => $order_id,
                'url' => $url,
              ];
              Mail::to($emailCustomer)->send(new RedeemCode($data));

              saveLog('CALLBACK VOUCHER', $order_id, $request->all(), 'ORDER VOUCHER SUCCESS');
              return ['success' => true];
            } else if ($t_status == 01) {
              $status = 'failed';
              DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
              saveLog('CALLBACK VOUCHER', $order_id, $request->all(), 'ORDER VOUCHER FAILED');
              return ['success' => false, 'msg' => 'Transaksi pending'];
            }
          }
          DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);

          if ($order->type == 'order_item') {
            if ($t_status == 00) {
              $transaction_detail = DB::table('transaction_detail')->where('transaction_code', $order_id)->first();
              $game = DB::table('games')->where('code', $transaction_detail->game_code)->first();
              $game_item = DB::table('games_item')->where('code', $transaction_detail->item_code)->first();
              $total_amount_formatted = 'Rp. ' . number_format($order->total_amount, 0, ',', '.');
              $str_userid = $transaction_detail->userid;
              if (strpos($str_userid, '#') !== false) {
                $user_ids = explode('#', $str_userid);
                $merged_user_id = implode('(', $user_ids) . ')';
              } else {
                $merged_user_id = $str_userid;
              }

              $mail_data = [
                'game' => $game->title,
                'game_item' => $game_item->title,
                'userid' => $merged_user_id,
                'order_id' => $order_id,
                // 'no_reference' => $order->no_reference,
                'order_date' => $order->created_at,
                'pay_method' => $payment_method->pm_title,
                'pay_status' => 'Sudah di proses',
                'total_amount' => $total_amount_formatted
              ];
              // Mail::to($order->email)->send(new SuccessOrderItem($mail_data));

              # Unipin order
              if ($order->from == 'unipin') {
                $tdetail = DB::table('transaction_detail')->where('transaction_code', $order_id)->first();
                $denom = DB::table('games_item')->where('code', $tdetail->item_code)->first();
                $result = Unipin::orderUnipin($tdetail->transaction_code, $tdetail->game_code, $tdetail->validation_token, $denom->denomination_id);
                saveLog('CALLBACK ORDER PRODUCT', $order_id, $result, 'Data From Unipin');

                Log::info('RESULT ORDER UNIPIN => ' . json_encode($result));
                if ($result['success']) {
                  DB::table('transaction')->where('transaction_code', $order_id)->update([
                    'game_transaction_number' => $result['data']['transaction_number'],
                    'game_transaction_status' => 1,
                    'status' => 'success'
                  ]);
                  Mail::to($order->email)->send(new SuccessOrderItem($mail_data));
                } else {
                  DB::table('transaction')->where('transaction_code', $order_id)->update([
                    'game_transaction_status' => 2,
                    // PENDING
                    'game_transaction_message' => $result['data']['error']['message']
                  ]);
                }
                return ['success' => true];
              }
              // ORDER DIGIFLAZZ
              if ($order->from == 'digiflazz') {
                $data = Digiflazz::order($order_id, $game_item->digi_code, $transaction_detail->userid);
                saveLog('CALLBACK ORDER PRODUCT', $order_id, $data, 'Data From Digiflazz');
                if ($data['data']['status'] == 'Sukses') {
                  DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
                    'status' => 'success',
                    'game_transaction_status' => 1,
                    'game_transaction_number' => isset($data['sn']) ? $data['sn'] : '',
                    // 'no_reference' => $data['trx_id'],
                  ]);
                  // DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
                  // 	'status' => 'success',
                  // 	'game_transaction_status' => 1,
                  // 	'game_transaction_number' => isset($data['sn']) ? $data['sn'] : '',
                  // 	'no_reference' => $data['trx_id'],
                  // ]);
                  return ['success' => true];
                } elseif ($data['data']['status'] == 'Pending') {
                  DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
                    'status' => 'pending',
                    'game_transaction_status' => 2
                  ]);
                  // DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
                  // 	'status' => 'pending',
                  // 	'game_transaction_status' => 1
                  // ]);
                  return ['success' => true];
                } elseif ($data['data']['status'] == 'Gagal') {
                  DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
                    'status' => 'failed',
                    'game_transaction_status' => 4
                  ]);
                  // DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
                  // 	'status' => 'failed',
                  // 	'game_transaction_status' => 1
                  // ]);
                  return ['success' => true];
                } else {
                  DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
                    'status' => 'pending'
                  ]);
                  // DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
                  // 	'status' => 'pending'
                  // ]);
                  return ['success' => true];
                }
              }
              # Api games order
              if ($order->from == 'apigames') {
                $tdetail = DB::table('transaction_detail')->where('transaction_code', $order_id)->first();
                $tgameid = DB::table('transaction_game_id')->where('transaction_code', $order_id)->get()->toArray();
                
                $uid = makeFieldsApigames($tgameid);
                $itemDetail = DB::table('games_item')->where('code', $tdetail->item_code)->first();
                // $result = Apigames::orderApigames($tdetail->transaction_code, $itemDetail->ag_code, $tdetail->userid);
                $result = Apigames::orderApigames($tdetail->transaction_code, $itemDetail->ag_code, $uid);
                Log::info('RESULT ORDER APIGAMES => ' . json_encode($result));
                saveLog('CALLBACK ORDER PRODUCT', $order_id, $result, 'Data From Apigames');

                if ($result['data']['status'] == 1) {
                  $orderStatus = $result['data']['data']['status'];
                  if ($orderStatus == 'Sukses' || $orderStatus == 'Sukses Sebagian') {
                    DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'success', 'game_transaction_status' => 1]);
                    Mail::to($order->email)->send(new SuccessOrderItem($mail_data));
                  } elseif ($orderStatus == 'Pending') {
                    DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'waiting', 'game_transaction_status' => 2]);
                  } elseif ($orderStatus == 'Gagal') {
                    DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'failed', 'game_transaction_status' => 4]);
                  } elseif ($orderStatus == 'Proses') {
                    DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'processing', 'game_transaction_status' => 3]);
                  } elseif ($orderStatus == 'Validasi Provider') {
                    DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'processing', 'game_transaction_status' => 5]);
                  }
                  // Mail::to($order->email)->send(new SuccessOrderItem($mail_data));
                  return ['success' => true, 'data' => $result['data']];
                } else if ($result['data']['status'] == 0) {
                  return ['success' => false, 'message' => $result['data']['error_msg']];
                }
              }
              # LAPAKGAMING order
              if ($order->from == 'lapakgaming') {
                $tdetail = DB::table('transaction_detail')->where('transaction_code', $order_id)->first();
                $tgameid = LapakGaming::getGameID($order_id);

                $denom = DB::table('games_item')->where('code', $tdetail->item_code)->first();
                // $result = LapakGaming::orderGame($tdetail->userid, $denom, $order_id);
                $result = LapakGaming::orderGame($tgameid, $denom, $order_id);

                saveLog('CALLBACK ORDER PRODUCT', $order_id, $result, 'Data From LAPAKGAMING');

                Log::info('RESULT ORDER LAPAKGAMING => ' . json_encode($result));
                if ($result['success'] == 'SUCCESS') {
                  DB::table('transaction')->where('transaction_code', $order_id)->update([
                    'game_transaction_number' => $result['data']['tid'],
                    'game_transaction_status' => 0,
                    'status' => 'processing'
                  ]);
                  // Mail::to($order->email)->send(new SuccessOrderItem($mail_data));
                } elseif ($result['success'] == 'QUEUE') {
                  LapakGaming::queueOrder($order_id, 'direct');
                  DB::table('transaction')->where('transaction_code', $order_id)->update([
                    'game_transaction_number' => '-',
                    'game_transaction_status' => 0,
                    'status' => 'processing'
                  ]);
                  // Mail::to($order->email)->send(new SuccessOrderItem($mail_data));
                } else {
                  DB::table('transaction')->where('transaction_code', $order_id)->update([
                    'game_transaction_status' => 2,
                    'status' => 'failed',
                    // PENDING
                    'game_transaction_message' => $result['success']
                  ]);
                }
                return ['success' => true];
              }
            } else if ($t_status == 01) {
              $status = 'failed';
              DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
              return ['success' => false, 'msg' => 'Transaksi gagal dilakukan'];
            }
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
        return $this->callbackPayment($request);
      } else {
        Log::alert($errorCode);
      }

      return ['success' => false, 'message' => $e->getMessage()];
    } catch (\Exception $e) {
      Log::error('CALLBACK ERROR ' . $e->getMessage());
      saveLog('CALLBACK ERROR', $order_id, $request->all(), 'CALLBACK ERROR, ' . $e->getMessage());
      return ['success' => false, 'msg' => $e->getMessage()];
    }
  }

  public function callbackDigiflazz($request)
  {
    try {
      $data = $request->data;
      $order_id = $data['ref_id'];
      $order = DB::table('transaction')->where('transaction_code', $order_id)->first();
      $payment_method = DB::table('payment_method')->where('pm_code', $order->payment_method)->first();
      switch ($data['status']) {
        case 'Sukses':
          DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
            'status' => 'success',
            'game_transaction_status' => 1,
            'game_transaction_number' => isset($data['sn']) ? $data['sn'] : '',
            'no_reference' => $data['trx_id'],
          ]);
          DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
            'status' => 'success',
            'game_transaction_status' => 1,
            'game_transaction_number' => isset($data['sn']) ? $data['sn'] : '',
            'no_reference' => $data['trx_id'],
          ]);

          $transaction_detail = DB::table('transaction_detail')->where('transaction_code', $order_id)->first();
          $game = DB::table('games')->where('code', $transaction_detail->game_code)->first();
          $game_item = DB::table('games_item')->where('code', $transaction_detail->item_code)->first();
          $total_amount_formatted = 'Rp. ' . number_format($order->total_amount, 0, ',', '.');
          $str_userid = $transaction_detail->userid;
          if (strpos($str_userid, '#') !== false) {
            $user_ids = explode('#', $str_userid);
            $merged_user_id = implode('(', $user_ids) . ')';
          } else {
            $merged_user_id = $str_userid;
          }

          $mail_data = [
            'game' => $game->title,
            'game_item' => $game_item->title,
            'userid' => $merged_user_id,
            'order_id' => $order_id,
            'no_reference' => $order->no_reference,
            'order_date' => $order->created_at,
            'pay_method' => $payment_method->pm_title,
            'pay_status' => 'Sudah di proses',
            'total_amount' => $total_amount_formatted
          ];
          Mail::to($order->email)->send(new SuccessOrderItem($mail_data));

          saveLog('CALLBACK DIGIFLAZZ', $order_id, $data, 'Transaction Success');
          return ['success' => true];
        case 'Pending':
          DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
            'status' => 'pending',
            'game_transaction_status' => 1
          ]);
          DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
            'status' => 'pending',
            'game_transaction_status' => 1
          ]);

          saveLog('CALLBACK DIGIFLAZZ', $order_id, $data, 'Transaction Pending');
          return ['success' => true];
        case 'Gagal':
          DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
            'status' => 'failed',
            'game_transaction_status' => 1
          ]);
          DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
            'status' => 'failed',
            'game_transaction_status' => 1
          ]);
          // $dec_data = [
          //     'transaction_code' => generateOrderCode('VGN'),
          //     'users_code' => $request->users_code,
          //     'email' => $request->email,
          //     'total_amount' => -$game_item->price + + $fee - $discount,
          //     'subtotal' => -$game_item->price + + $fee - $discount,
          //     'fee' => 0,
          //     'transaction_url' => '#',
          //     'from' => $game_item->from,
          //     'payment_method' => $pm->pm_code,
          //     'no_reference' => $transaction_code,
          //     'status' => strtolower($result['data']['status']),
          //     'voucher_discount' => $discount,
          //     'voucher_code' => $voucher_code,
          //     'type' => 'topup',
          //     'game_transaction_number' => '',
          //     'game_transaction_status' => 1
          // ];
          // DB::table('transaction')->insert($dec_data);
          saveLog('CALLBACK DIGIFLAZZ', $order_id, $data, 'Transaction Failed');
          return ['success' => true];

        default:
          DB::table('transaction')->where('transaction_code', $data['ref_id'])->update([
            'status' => 'pending'
          ]);
          DB::table('transaction')->where('no_reference', $data['ref_id'])->update([
            'status' => 'pending'
          ]);

          saveLog('CALLBACK DIGIFLAZZ', $order_id, $data, 'Transaction Pending');
          return ['success' => true];
      }
    } catch (\Exception $e) {
      Log::error('CALLBACK ERROR ' . $e->getMessage());
      saveLog('CALLBACK DIGIFLAZZ', $order_id, $data, 'CALLBACK ERROR, ' . $e->getMessage());
      return ['success' => false, 'msg' => $e->getMessage()];
    }
  }

  public function callbackLapakGaming($request)
  {
    DB::beginTransaction();
    try {
      // saveLog('CALLBACK ORDER PRODUCT', $request->all(), 'Data From LAPAKGAMING');

      $rdata = $request->data;

      $transaction = DB::table('transaction')
        ->where('game_transaction_number', $rdata['tid'])
        ->where('from', 'lapakgaming')
        ->where('type', 'order_item')
        ->first();
      if (empty($transaction)) {
        saveLog('ERROR CALLBACK ORDER PRODUCT', $rdata['tid'], $request, 'Data From LAPAKGAMING');

        return ['success' => false, 'msg' => 'Transaction Not Found'];
      }

      if ($transaction->status == 'success' || $transaction->status == 'failed') {
        saveLog('ERROR CALLBACK ORDER PRODUCT - Transaksi sudah diproses sebelumnya', $transaction->transaction_code, $request->all(), 'Data From LAPAKGAMING');

        return ['success' => true, 'msg' => 'Transaksi sudah diproses sebelumnya'];
      }

      $order_id = $transaction->transaction_code;

      if ($rdata['status'] == 'SUCCESS') {
        saveLog('CALLBACK ORDER PRODUCT', $order_id, $request->all(), 'Data From LAPAKGAMING');

        $tdetail = DB::table('transaction_detail')
          ->where('transaction_code', $transaction->transaction_code)
          ->first();
        $game = DB::table('games')->where('code', $tdetail->game_code)->first();
        $game_item = DB::table('games_item')->where('code', $tdetail->item_code)->first();
        $payment_method = DB::table('payment_method')->where('pm_code', $transaction->payment_method)->first();

        $lg_messages = '';
        if (isset($rdata['transactions'][0])) {
          $lg_messages = $rdata['transactions'][0]['status'] . ' - ' . $rdata['transactions'][0]['note'];
        }

        DB::table('transaction')->where('transaction_code', $order_id)->update([
          'game_transaction_number' => $rdata['tid'],
          'game_transaction_status' => 1,
          'game_transaction_message' => $rdata['status'],
          'status' => 'success',
          'lg_messages' => $lg_messages
        ]);

        if ($game->lg_variant == 'DIGITAL') {
          $mail_data = [
            'game' => $game->title,
            'game_item' => $game_item->title,
            'userid' => $tdetail->userid,
            'order_id' => $order_id,
            // 'no_reference' => $order->no_reference,
            'order_date' => $transaction->created_at,
            'pay_method' => $payment_method->pm_title,
            'pay_status' => 'Sudah di proses',
            'total_amount' => 'Rp. ' . number_format($transaction->total_amount, 0, ',', '.')
          ];
          Mail::to($transaction->email)->send(new SuccessOrderItem($mail_data));
        } else {
          $emailCustomer = $transaction->email;
          $str_email = explode('@', $emailCustomer);
          $nameCustomer = $str_email[0];

          $user = DB::table('users')->where('users_code', $transaction->users_code)->first();
          if ($user) {
            $nameCustomer = $user->name;
          }

          $redeem_codes = [
            [
              'redeem_codes' => [],
              'game' => $game->title,
              'title' => $game_item->title,
            ]
          ];

          foreach ($rdata['transactions'] as $value) {
            array_push($redeem_codes[0]['redeem_codes'], $value['voucher_code']);
          }

          $data = [
            'name' => $nameCustomer,
            'email' => $emailCustomer,
            'redeem_codes' => $redeem_codes,
            'order_id' => $order_id,
            'url' => env('EMAIL_DOMAIN')
          ];

          Mail::to($emailCustomer)->send(new RedeemCode($data));
        }

        DB::commit();

        return ['success' => true];
      } else {
        DB::table('transaction')->where('transaction_code', $order_id)->update([
          'game_transaction_number' => $rdata['tid'],
          'game_transaction_status' => 0,
          'game_transaction_message' => $rdata['status'],
          'status' => 'failed'
        ]);

        $cek = checkBalanceUser($transaction->users_code);
        if (isset($cek['data'])) {
          $inc_data = [
            'transaction_code' => generateOrderCode('VGN'),
            'users_code' => $transaction->users_code,
            'email' => $transaction->email,
            'total_amount' => $transaction->total_amount,
            'subtotal' => $transaction->total_amount,
            'fee' => 0,
            'transaction_url' => '#',
            'from' => 'lapakgaming',
            'payment_method' => $transaction->payment_method,
            // 'no_reference' => $transaction_code,
            'status' => 'success',
            'voucher_discount' => 0,
            'voucher_code' => '-',
            'game_transaction_number' => '-',
            'game_transaction_status' => 0,
            'game_transaction_message' => 'Pengembalian Dana Transaksi gagal ' . $order_id,
            'type' => 'topup',
            'remaining_balance' => $cek['data']->users_balance + $transaction->total_amount,
            'created_at' => date('Y-m-d H:i:s', strtotime('+10 seconds'))
          ];
          DB::table('transaction')->insert($inc_data);
  
          updateUsersBalance($transaction->users_code, $transaction->total_amount);
        }


        DB::commit();
        return ['success' => true];
      }
    } catch (\Exception $e) {
      DB::rollBack();

      Log::error('CALLBACK ERROR LAPAKGAMING ' . $e->getMessage());
      return ['success' => false, 'msg' => $e->getMessage()];
    }
  }

  public function callbackProductLapakGaming($request)
  {
    DB::beginTransaction();
    try {
      $rdata = $request->data;

      $item = DB::table('games_item')
        ->where('lg_code', $rdata['code'])
        ->first();

      if (empty($item)) {
        Log::error('CALLBACK PRODUK ERROR LAPAKGAMING - Item tidak disimpan' . json_encode($request->all()));
        return ['success' => true, 'msg' => 'Item tidak disimpan', 'data' => $rdata];
      }

      $upd = [
        'price_original' => $rdata['price'],
        'isActive' => $rdata['status'] == 'available' ? 1 : 0
      ];
      DB::table('games_item')
        ->where('lg_code', $rdata['code'])
        ->where('from', 'lapakgaming')
        ->update($upd);

      saveLog('CALLBACK PRODUCT SUCCESS', $rdata['code'], $request->all(), 'Data From LAPAKGAMING');
      DB::commit();

      return ['success' => true, 'msg' => 'Item diupdate', 'data' => $rdata];
    } catch (\Exception $e) {
      DB::rollBack();

      Log::error('CALLBACK ERROR LAPAKGAMING ' . $e->getMessage());
      return ['success' => false, 'msg' => $e->getMessage()];
    }
  }

  public function riwayatTopUp($request)
  {
    $users_code = Auth::user()->users_code;
    $date_from = date_create_from_format('d/m/Y', $request->date_from);
    $date_to = date_create_from_format('d/m/Y', $request->date_to);
    $search = $request->search;

    $select = [
      "created_at",
      "email",
      "fee",
      "game_transaction_message",
      "game_transaction_number",
      "game_transaction_status",
      "id",
      "no_reference",
      "payment_method",
      "remaining_balance",
      "status",
      "subtotal",
      "total_amount",
      "transaction_code",
      "transaction_token",
      "transaction_url",
      "type",
      "updated_at",
      "users_code",
      "voucher_code",
      "voucher_discount"
    ];
    $query = DB::table('transaction')
      ->select($select)
      ->where('users_code', $users_code)
      ->where('type', 'topup')
      ->whereIn('status', ['success']);

    if ($date_from && $date_to) {
      $date_from_formatted = $date_from->format('Y-m-d');
      $date_to_formatted = $date_to->format('Y-m-d');
      $query->whereDate('created_at', '>=', $date_from_formatted);
      $query->whereDate('created_at', '<=', $date_to_formatted);
    }

    if ($search) {
      $query->where(function ($q) use ($search) {
        $q->where('transaction_code', 'LIKE', '%' . $search . '%')
          ->orWhere('email', 'LIKE', '%' . $search . '%');
      });
    }

    $all_data = $query->orderBy('created_at', 'DESC')->paginate(10);

    return $all_data;
  }

  public function historyTransaction($request)
  {
    $users_code = auth('api')->user()->users_code;
    $date_from = $request->date_from;
    $date_to = $request->date_to;
    $search = $request->search;
    $status = $request->status;
    $select = [
      'transaction.*', 'payment_method.pm_code', 'payment_method.pm_title',
      // 'td.item_code',
      // 'td.transaction_code',
      // 'td.detail_code',
      // 'gi.title as item_title',
      // 'gi.game_code',
      // 'gi.from',
      // 'g.*'
    ];
    $data = DB::table('transaction')
      ->select($select)
      // ->leftJoin('transaction_detail as td', 'transaction.transaction_code', '=', 'td.transaction_code')
      // ->leftJoin('games_item as gi', 'td.item_code', '=', 'gi.code')
      // ->leftJoin('games as g', 'gi.game_code', '=', 'g.code')
      ->where('transaction.users_code', $users_code)
      ->whereIn('transaction.type', ['order_item', 'order_voucher'])
      ->leftJoin('payment_method', 'transaction.payment_method', '=', 'payment_method.pm_code');


    if (!empty($status) && !str_contains($status, 'all')) {
      $status = explode(',', $status);
      if (in_array("pending", $status)) {
        array_push($status, "waiting");
      }
      $data = $data->whereIn('transaction.status', $status);
    }

    if (!empty($search)) {
      $data = $data->where(function ($query) use ($search) {
        $query->where('transaction.transaction_code', 'LIKE', '%' . $search . '%')
          ->orWhere('transaction.no_reference', 'LIKE', '%' . $search . '%');
      });
    }

    if (!empty($date_from) && !empty($date_to)) {
      $date_from = date('Y-m-d 00:00:00', strtotime($date_from));
      $date_to = date('Y-m-d 23:59:00', strtotime($date_to));
      // dd($date_from, $date_to);
      $data = $data->whereBetween('transaction.created_at', [$date_from, $date_to]);
    } else {
      $date_from = date('Y-m-d', strtotime('-3 month'));
      $date_to = date('Y-m-d', strtotime('+1 day'));

      $data = $data->whereBetween('transaction.created_at', [$date_from, $date_to]);
    }
    $data = $data->orderBy('transaction.created_at', 'DESC')->paginate(10);

    foreach ($data as $item) {
      $order_code = $item->transaction_code;
      $select = [
        'transaction_detail.*',
        'g.*'
      ];
      $detail = DB::table('transaction_detail')
        ->select($select)
        ->leftJoin('games as g', 'transaction_detail.game_code', '=', 'g.code')
        ->where('transaction_code', $order_code)
        ->groupBy("detail_code")
        ->get();

      // Mengambil game_code dari objek $data


      // Menggunakan game_code tersebut untuk mengambil data dari tabel "fields"
      $fields = DB::table('fields')->select('name', 'type', 'display_name')->where('game_code', $detail[0]->code ?? '')->get()->toArray();

      $hasDropdown = false;

      $newObj = (object) [
        'display_name' => 'username',
        'name' => 'username',
        'type' => 'string',
        'username' => $detail[0]->username ?? '',
        'value' => $detail[0]->username ?? '',
      ];

      $count = 1;
      // foreach ($fields as $value) {
      //   $userServer = $detail[0]->userid;

      //   if (isset($detail[0]->userid)) {
      //     if ($userServer[0] == '-') {
      //       $userServer = ltrim($userServer, '-');
      //     }
      //     $parts = explode('-', $userServer);
      //     $userId = $parts[0];
      //     $serverId = $parts[1] ?? '';

      //     if (count($parts) >= 3) {
      //       $username = $parts[count($parts) - 3];
      //     }

      //     if ($value->name == 'userid' || $value->name == 'user_id') {
      //       $value->userid = $userId;
      //       $value->value = $userId;
      //     } elseif ($value->name == 'username') {
      //       $value->username = $username;
      //       $value->value = $username;
      //     } elseif ($value->type == 'dropdown') {
      //       $dropdown = DB::table('fields_data')->where('game_code', $detail[0]->code)->get();
      //       foreach ($dropdown as $drop) {
      //         if ($serverId == $drop->value) {
      //           $value->serverid = $drop->value;
      //           $value->server = $drop->name;
      //           $value->value = $drop->name;
      //         }
      //       }
      //       $hasDropdown = true;
      //     }
      //     // else {
      //     //   $value->otherid = $serverId;
      //     //   $value->other = $serverId;

      //     //   if ($count == 1) {
      //     //     $value->value = $userId;
      //     //   } else {
      //     //     $value->value = !empty($serverId) ? $serverId : $userId;
      //     //   }
      //     // }
      //     $count++;
      //   }
      // }
      // foreach ($fields as $key => $value) {
      //   $userServer = $detail[0]->userid ?? "";

      //   if (isset($detail[0]->userid)) {
      //     if (isset($userServer[0]) && $userServer[0] == '-') {
      //       $userServer = ltrim($userServer, '-');
      //     }
      //     $parts = explode('-', $userServer);
      //     $userId = $parts[0] ?? '';
      //     $serverId = $parts[1] ?? '';

      //     if (count($parts) >= 3) {
      //       $username = $parts[count($parts) - 3];
      //     }

      //     $dropdown = DB::table('fields_data')->where('game_code', $detail[0]->code ?? '')->get();
      //     $dd = false;
      //     foreach ($dropdown as $drop) {
      //       if (isset($parts[$key]) && $parts[$key] == $drop->value && $value->type == 'dropdown') {
      //         $value->serverid = $drop->value;
      //         $value->server = $drop->name;
      //         $value->value = $drop->name;

      //         $dd = true;
      //         continue;
      //       }
      //       // if ($userId == $drop->value && $value->type == 'dropdown') {
      //       //   $value->serverid = $drop->value;
      //       //   $value->server = $drop->name;
      //       //   $value->value = $drop->name;

      //       //   $dd = true;
      //       //   continue;
      //       // }
      //       // if ($serverId == $drop->value && $value->type == 'dropdown') {
      //       //   $value->serverid = $drop->value;
      //       //   $value->server = $drop->name;
      //       //   $value->value = $drop->name;

      //       //   $dd = true;
      //       //   continue;
      //       // }
      //     }

      //     if ($dd) {
      //       continue;
      //     }

      //     if ($value->name == 'userid' || $value->name == 'user_id') {
      //       $value->userid = $userId;
      //       $value->value = $userId;
      //     } elseif ($value->name == 'username') {
      //       $value->username = $username;
      //       $value->value = $username;
      //     }
      //     // elseif ($value->type == 'dropdown') {
      //     //   $hasDropdown = true;
      //     // }
      //     else {
      //       $value->otherid = $serverId;
      //       $value->other = $serverId;

      //       // if ($count == 1) {
      //         $value->value = $parts[$key] ?? '';
      //       // } else {
      //       //   $value->value = $serverId ? $serverId : $userId;
      //       // }
      //     }
      //     $count++;
      //   }
      // }

      // if ($hasDropdown) {
      // if (count($fields) == 2) {
      array_unshift($fields, $newObj);
      // }
      if (!isset($detail[0])) {
        $detail[] = (object)[
          'fields' => []
        ];
      }
      // $detail[0]->fields = $fields;
      $detail[0]->fields = getIdGamesAttribute($detail[0]);
      // dd($detail[0]->fields);
      // }
      $item->product = '';
      foreach ($detail as $key => $detailItem) {
        $detailItem->product = '';
        $gameCode = $detailItem->game_code ?? '';
        $game = DB::table('games')->where('code', $gameCode)->first();
        if (!empty($game)) {
          $item->product .= $key == 0 ? $game->title : ', ' . $game->title;
          $detailItem->product = $game->title;
        }
        $detailItem->userid = str_replace('-', '', $detailItem->userid ?? '');
        unset($detailItem->from);
      }
      $item->order_item = $detail;
      // dd($item);

      unset($item->from);
    }
    return $data;
  }
}
