<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Midtrans;
use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TransactionController extends ApiController
{
    public function validateThis($request, $rules = array())
    {
        return Validator::make($request->all(), $rules, getCustomMessages());
    }

    public function getUserTransaction(Request $request)
    {
        $status = $request->status;
        $limit = $request->limit ?? 10;
        $offset = $request->offset ?? 0;
        $user_id = Auth::user()->id;

        $transaction = DB::table("transaction")
            ->where("user_id", $user_id)
            ->where("status", $status)
            ->limit($limit)
            ->offset($offset)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($transaction as $value) {
            $details = DB::table("transaction_detail")
                ->where("transaction_code", $value->transaction_code)
                ->get();
            $value->products = [];
            $value->shops = [];
            $value->address = DB::table('users_alamat')->where('id', $value->alamat_id)->first(['kota', 'alamat']);
            $value->payment_method = DB::table('payment_method')->where('id', $value->payment_method_id)->first(['pm_title', 'pm_logo']);
            $value->shipping = DB::table('shipping_method')->where('id', $value->shipping_id)->first(['name']);

            foreach ($details as $det) {
                $product = db::table('products')->where('id', $det->product_id)->first();

                $images = DB::table("products_img")->where("products_id", $det->product_id)->get();
                $product->thumbnail = 'https://curie.pnnl.gov/sites/default/files/default_images/default-image_0.jpeg';
                if (!empty($images)) {
                    $product->thumbnail = asset('storage/' . $images[0]->img);
                }

                foreach ($images as $img) {
                    $img->img = asset('storage/' . $img->img);
                }

                $product->jenis = '';
                $jenis = DB::table('jenis')->where('id', $product->jenis_id)->first();
                if (!empty($jenis)) {
                    $product->jenis = $jenis->title;
                }

                $hargaDiskon = $product->harga - ($product->harga * $product->diskon / 100);
                $product->berat = $product->berat . ' kg';
                $product->harga = 'Rp ' . number_format($product->harga, 0, ',', '.');
                // $product->harga_ori = $product->harga;
                // $product->harga_diskon_ori = $hargaDiskon;
                $product->harga_diskon = 'Rp ' . number_format($hargaDiskon, 0, ',', '.');

                // // TODO: tambah terjual
                // $sold = 0;
                // $product->sold = $sold;
                // // TODO: tambah rating
                // $rating = '0.0';
                // $product->rating = $rating;
                // $product->harga_diskon = 'Rp ' . number_format($hargaDiskon, 0, ',', '.');
                $product->qty = $det->qty;
                $product->price = $det->price;
                $product->total_price = $det->total;

                $value->products[] = $product;

                $shops = getUsersShopsByShopsId($product->users_shops_id);
                if ($shops) {
                    $value->shops = $shops;
                }
            }
        }

        return $this->sendResponse(0, 'Success', $transaction);
    }

    public function updateUserTransaction(Request $request)
    {
        $rules = [
            'status' => 'required|in:process,send,done,cancel',
            'transaction_id' => 'required',
        ];

        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(
                1,
                validationMessage($validator->errors())
            );
        }

        $status = $request->status;
        $user_id = Auth::user()->id;
        $transaction_id = $request->transaction_id;

        DB::table("transaction")
            ->where("id", $transaction_id)
            ->where("user_id", $user_id)
            ->update(['status' => $status]);

        if ($status == 'done') {
            $transaction = DB::table('transaction')
                ->where('id', $transaction_id)
                ->first();

            updateShopBalance($transaction->shop_id, $transaction->total_amount);
        }

        return $this->sendResponse(0, 'Success', []);
    }

    public function getShopTransaction(Request $request)
    {
        $status = $request->status;
        $limit = $request->limit ?? 10;
        $offset = $request->offset ?? 0;
        $user_id = Auth::user()->id;
        $shop = getUsersShops();

        $transaction = DB::table("transaction")
            ->where("user_id", '!=', $user_id)
            ->where("shop_id", $shop->id)
            ->where("status", $status)
            ->limit($limit)
            ->offset($offset)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($transaction as $value) {
            $details = DB::table("transaction_detail")
                ->where("transaction_code", $value->transaction_code)
                ->get();
            $value->products = [];
            $value->shops = [];
            $value->address = DB::table('users_alamat')->where('id', $value->alamat_id)->first(['kota', 'alamat']);
            $value->payment_method = DB::table('payment_method')->where('id', $value->payment_method_id)->first(['pm_title', 'pm_logo']);
            $value->shipping = DB::table('shipping_method')->where('id', $value->shipping_id)->first(['name']);

            foreach ($details as $det) {
                $product = db::table('products')->where('id', $det->product_id)->first();

                $images = DB::table("products_img")->where("products_id", $det->product_id)->get();
                $product->thumbnail = 'https://curie.pnnl.gov/sites/default/files/default_images/default-image_0.jpeg';
                if (!empty($images)) {
                    $product->thumbnail = asset('storage/' . $images[0]->img);
                }

                foreach ($images as $img) {
                    $img->img = asset('storage/' . $img->img);
                }

                $product->jenis = '';
                $jenis = DB::table('jenis')->where('id', $product->jenis_id)->first();
                if (!empty($jenis)) {
                    $product->jenis = $jenis->title;
                }

                $hargaDiskon = $product->harga - ($product->harga * $product->diskon / 100);
                $product->berat = $product->berat . ' kg';
                $product->harga = 'Rp ' . number_format($product->harga, 0, ',', '.');
                // $product->harga_ori = $product->harga;
                // $product->harga_diskon_ori = $hargaDiskon;
                $product->harga_diskon = 'Rp ' . number_format($hargaDiskon, 0, ',', '.');

                // // TODO: tambah terjual
                // $sold = 0;
                // $product->sold = $sold;
                // // TODO: tambah rating
                // $rating = '0.0';
                // $product->rating = $rating;
                // $product->harga_diskon = 'Rp ' . number_format($hargaDiskon, 0, ',', '.');
                $product->qty = $det->qty;
                $product->price = $det->price;
                $product->total_price = $det->total;

                $value->products[] = $product;

                $shops = getUsersShopsByShopsId($product->users_shops_id);
                if ($shops) {
                    $value->shops = $shops;
                }
            }
        }

        return $this->sendResponse(0, 'Success', $transaction);
    }

    public function updateShopTransaction(Request $request)
    {
        $rules = [
            'status' => 'required|in:process,send,done,cancel',
            'transaction_id' => 'required',
        ];

        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(
                1,
                validationMessage($validator->errors())
            );
        }

        $status = $request->status;
        $shop = getUsersShops();
        $transaction_id = $request->transaction_id;

        $data = ['status' => $status];

        if ($status == 'send') {
            $data['no_resi'] = $request->no_resi;
        }

        DB::table("transaction")
            ->where("id", $transaction_id)
            ->where("shop_id", $shop->id)
            ->update($data);

        return $this->sendResponse(0, 'Success', []);
    }

    public function getShopBalance(Request $request)
    {
        $shop = getUsersShops();

        updateShopBalance($shop->id, 0);

        $balance = DB::table('users_shops_balance')
            ->where('shop_id', $shop->id)
            ->first(['balance', 'updated_at']);

        return $this->sendResponse(0, 'Success', $balance);
    }
}
