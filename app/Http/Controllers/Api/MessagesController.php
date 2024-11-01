<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MessagesController extends ApiController
{
    public function getMessages(Request $request)
    {
        $to = $request->to;
        $from = Auth::user()->id;

        $data = DB::table('messages')
            ->where('user_id', $from)
            ->where('shop_id', $to)
            ->orderBy('id','desc')
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
            ->orderBy('id','desc')
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
            ->orderBy('created_at','desc')
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
}
