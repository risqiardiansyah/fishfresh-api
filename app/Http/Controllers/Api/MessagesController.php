<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessagesController extends ApiController
{
    public function getMessages(Request $request)
    {
        $to = $request->to;
        $from = Auth::user()->id;

        $data = DB::table('messages')
            ->where('user_id', $from)
            ->where('shop_id', $to)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($data as $value) {
            $value->me = false;

            if ($value->from == 'user') {
                $value->me = true;
            }
        }

        return $this->sendResponse(0, 'Success', $data);
    }

    public function sendMessages(Request $request)
    {
        $user_id = Auth::user()->id;
        $to = $request->to;
        $messages = $request->messages;

        $data = [
            'user_id' => $user_id,
            'shop_id' => $to,
            'messages' => $messages,
            'from' => 'user',
        ];

        DB::table('messages')->insert($data);

        return $this->sendResponse(0, 'Success', $data);
    }

    public function getMessagesShop(Request $request)
    {
        $to = $request->to;
        $from = getUsersShops()->id;

        $data = DB::table('messages')
            ->where('user_id', $to)
            ->where('shop_id', $from)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($data as $value) {
            $value->me = false;

            if ($value->from == 'shop') {
                $value->me = true;
            }
        }

        return $this->sendResponse(0, 'Success', $data);
    }

    public function getMessagesShopList(Request $request)
    {
        $shop_id = getUsersShops()->id;

        $data = DB::table('messages')
            ->where('shop_id', $shop_id)
            ->orderBy('created_at', 'desc')
            ->groupBy('user_id')
            ->get();

        foreach ($data as $value) {
            $lawan = DB::table('users')->where('id', $value->user_id)->first(['id', 'name']);

            $value->user = $lawan;
        }

        return $this->sendResponse(0, 'Success', $data);
    }

    public function sendMessagesShop(Request $request)
    {
        $shop_id = getUsersShops()->id;
        $to = $request->to;
        $messages = $request->messages;

        $data = [
            'user_id' => $to,
            'shop_id' => $shop_id,
            'messages' => $messages,
            'from' => 'shop',
        ];

        DB::table('messages')->insert($data);

        return $this->sendResponse(0, 'Success', $data);
    }

    public function callbackMidtrans(Request $request)
    {
        Log::info('[CALLBACK] ');
        Log::info(json_encode($request->all()));

        $order_id = '';
        if (isset($request->order_id)) {
            $id = explode('-', $request->order_id);
            $order_id = $id[0];
        } elseif (isset($request->merchantOrderId)) {
            //duitku
            $order_id = $request->merchantOrderId;
        } elseif (isset($request->data)) {
            $order_id = $request->data['ref_id'];
        }

        $status = $request->transaction_status;

        if (($status == 'settlement' || $status == 'capture') && $request->fraud_status == 'accept') {
            DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => 'process']);
        }

        // if ($status == 'pending') {
        //     $status = 'waiting';
        //     DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
        //     return ['success' => true, 'msg' => 'Transaksi pending'];
        // } elseif ($status == 'cancel') {
        //     $status = 'cancel';
        //     DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
        //     return ['success' => true, 'msg' => 'Transaksi dibatalkan'];
        // } elseif ($status == 'failure' || $status == 'deny') {
        //     $status = 'failed';
        //     DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
        //     return ['success' => true, 'msg' => 'Transaksi gagal dilakukan'];
        // } elseif ($status == 'expire') {
        //     $status = 'expired';
        //     DB::table('transaction')->where('transaction_code', $order_id)->update(['status' => $status]);
        //     return ['success' => true, 'msg' => 'Transaksi expired'];
        // }

        return $this->sendResponse(0, 'Success', []);
    }
}
