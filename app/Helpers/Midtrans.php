<?php

namespace App\Helpers;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class Midtrans
{
    public static function getTransactionStatus($order_id)
    {
        $url = 'https://api.sandbox.midtrans.com/v2/' . $order_id . '/status';
        $client = new Client();
        $encoded = env('MIDTRANS_KEY');

        $auth = 'Basic ' . $encoded;

        $response = $client->request('GET', $url, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-type' => 'application/json',
                'Authorization' =>  $auth
            ],
        ]);

        $dataResponse = json_decode($response->getBody()->getContents(), true);
        return $dataResponse;
    }

    public static function topUp($request, $userCode)
    {
        $user = User::where('users_code', $userCode)->first();
        $transaction_code = generateOrderCode('VGN');
        $pm_code = $request->pm_code; // Payment method
        $pm = DB::table('payment_method')->where('pm_code', $pm_code)->first();
        if (empty($pm)) {
            return [
                'success' => false,
                'msg' => 'Payment method invalid',
            ];
        }
        if ($pm_code == 'saldo') {
            return [
                'success' => false,
                'msg' => 'Payment method not support',
            ];
        }

        $enable_payment = getEnablePayment($pm->pm_code);
        $URL = env('APP_URL');

        $fee = $pm->fee_saldo;
        if ($pm->fee_type_saldo == 'percent') {
            $fee = $request->amount * number_format(($pm->fee_saldo / 100),3);
        }
        $fee = ceil($fee);

        $body = (object)[
            'transaction_details' => (object)[
                'order_id' => $transaction_code,
                'gross_amount' => $request->amount + $fee,
            ],
            "customer_required" => false,
            'customer_details' => (object)[
                'email' => $user->email,
                'first_name' => $user->name,
                'last_name' => $user->name,
                'phone' => $user->no_telp,
            ],
            "enabled_payments" => $enable_payment,
            'item_details' => [
                (object)[
                    "id" =>  $transaction_code,
                    "name" =>  "Nominal Topup",
                    "price" =>  $request->amount + $fee,
                    "quantity" => 1,
                ]
            ],
            "callbackUrl" => "$URL/api/v1/callback",
            "usage_limit" =>  1,
            "expiry" => (object)[
                "duration" => $pm->expiry_time == 0 ? 15 : $pm->expiry_time,
                "unit" => "minutes"
            ],
        ];

        // $response = $client->request('POST', $url, [
        //     'headers' => $headers,
        //     'json' => $body,
        // ]);
        // $data = json_decode($response->getBody()->getContents(), true);
        $data = self::useSnapMidtrans($body);
        $balance = DB::table('users_balance')->where('users_code', $userCode)->first();
        if (isset($data['payment_url'])) {
            $tr_data = [
                'transaction_code' => $transaction_code,
                'users_code' => $user->users_code,
                'email' => $user->email,
                'status' => 'waiting', // get status transaction
                'total_amount' => $request->amount + $fee,
                'subtotal' => $request->amount,
                'fee' => $fee,
                'transaction_url' => $data['payment_url'],
                'from' => 'midtrans',
                'payment_method' => $pm->pm_code,
                // 'no_reference' => $data['order_id'],
                'type' => 'topup',
                'remaining_balance' => $balance->users_balance + $request->amount,
                'game_transaction_message' => 'Top Up Transaksi ' . $transaction_code
            ];
            DB::table('transaction')->insert($tr_data);
        }
        return ['success' => true, 'data' => $data];
    }

    public static function useSnapMidtrans($params)
    {
        $order_id = $params->transaction_details->order_id;
        $client = new Client();
        $response = $client->request('POST', 'https://app.sandbox.midtrans.com/snap/v1/transactions', [
            'body' => json_encode($params),
            'headers' => [
                'accept' => 'application/json',
                'authorization' => 'Basic ' . env('MIDTRANS_KEY'),
                'content-type' => 'application/json',
            ]
        ]);
        $dataResponse = json_decode($response->getBody()->getContents(), true);
        $result = $dataResponse;
        $result["payment_url"] = $result["redirect_url"];
        unset($result["redirect_url"]);
        $result["order_id"] = $order_id;

        return $result;
    }

    public static function usePaymentLinkMidtrans($params)
    {
        $client = new Client();
        $response = $client->request('POST', 'https://api.sandbox.midtrans.com/v1/payment-links', [
            'body' => json_encode($params),
            'headers' => [
                'accept' => 'application/json',
                'authorization' => 'Basic ' . env('MIDTRANS_KEY'),
                'content-type' => 'application/json',
            ]
        ]);
        $dataResponse = json_decode($response->getBody()->getContents(), true);
        $result = $dataResponse;

        return $result;
    }
}
