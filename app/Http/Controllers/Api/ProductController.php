<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Midtrans;
use App\Http\Controllers\ApiController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductController extends ApiController
{
    public function validateThis($request, $rules = array())
    {
        return Validator::make($request->all(), $rules, getCustomMessages());
    }

    public function getSlider()
    {
        $slider = DB::table("slider")->pluck('img');

        return $this->sendResponse(0, 'Success', $slider);
    }

    public function getProduct()
    {
        $shops = getUsersShops();

        if ($shops == false) {
            return $this->sendError(2, 'Gagal', 'Anda tidak memiliki toko');
        }

        $products = DB::table("products")
            ->where("users_shops_id", $shops->id)
            ->where("is_deleted", 0)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($products as $product) {
            $images = DB::table("products_img")->where("products_id", $product->id)->get();
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
            $product->harga = number_format($product->harga, 0, ',', '.');
            $product->images = $images;
            $product->harga_diskon = number_format($hargaDiskon, 0, ',', '.');
        }

        return $this->sendResponse(0, 'Success', $products);
    }

    public function addProduct(Request $request)
    {
        Log::info($request->all());
        $rules = [
            'images' => 'required',
            'jenis_id' => 'required',
            'asuransi' => 'required',
            'berat' => 'required',
            'deskripsi' => 'required',
            'nama_produk' => 'required',
            'harga' => 'required',
            'diskon' => 'required',
            'stock' => 'required'
        ];

        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(
                1,
                validationMessage($validator->errors())
            );
        }

        $user_id = Auth::user()->id;

        $shops = DB::table('users_shops')->where('user_id', $user_id)->first();

        if (empty($shops)) {
            return $this->sendError(2, 'Gagal', 'Anda tidak memiliki toko');
        }

        $images = $request->images;

        $jenis_id = $request->jenis_id;
        $asuransi = $request->asuransi;
        $berat = $request->berat;
        $deskripsi = $request->deskripsi;
        $nama_produk = $request->nama_produk;
        $harga = $request->harga;
        $diskon = $request->diskon;
        $stock = $request->stock;

        $data = [
            'users_shops_id' => $shops->id,
            'jenis_id' => $jenis_id,
            'asuransi' => $asuransi,
            'berat' => $berat,
            'deskripsi' => $deskripsi,
            'nama_produk' => $nama_produk,
            'harga' => $harga,
            'diskon' => $diskon,
            'stock' => $stock,
        ];

        if (!empty($request->id)) {
            DB::table('products')->where('id', $request->id)->update($data);

            DB::table('products_img')->where('products_id', $request->id)->delete();
            foreach ($images as $value) {
                if (!empty($value)) {
                    $image = uploadFotoWithFileNameApi($value, 'PR');

                    DB::table('products_img')->insert(['img' => $image, 'products_id' => $request->id]);
                }
            }
        } else {
            DB::table('products')->insert($data);
            $productId = DB::getPdo()->lastInsertId();

            foreach ($images as $value) {
                if (!empty($value)) {
                    $image = uploadFotoWithFileNameApi($value, 'PR');

                    DB::table('products_img')->insert(['img' => $image, 'products_id' => $productId]);
                }
            }
        }

        return $this->sendResponse(0, 'Success', $data);
    }

    public function getJenis(Request $request)
    {
        $data = DB::table('jenis')->get(['title as text', 'id as value']);

        return $this->sendResponse(0, 'Success', $data);
    }

    public function deleteProduct($id)
    {
        DB::table('products')->where('id', $id)->update(['is_deleted' => 1]);

        return $this->sendResponse(0, 'Success', []);
    }


    public function getSelledProduct(Request $request)
    {
        $search = $request->query('search', null);
        $jenis = $request->query('jenis', null);
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 0);

        $products = DB::table("products")
            ->where("is_deleted", 0);


        if (!empty($search) && $search == 'Produk Pilihan') {
            $products = $products->where('created_at', '>', Carbon::now()->subDays(7));
        } elseif ($search) {
            $products = $products->where(function ($query) use ($search) {
                $query->where('nama_produk', 'like', '%' . $search . '%');
                $query->orWhere('deskripsi', 'like', '%' . $search . '%');
            });
        }
        if ($jenis) {
            $products = $products->where('jenis', $jenis);
        }

        $products = $products->limit($limit)
            ->offset($offset)
            ->inRandomOrder()
            ->get();

        foreach ($products as $product) {
            $images = DB::table("products_img")->where("products_id", $product->id)->get();
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

            $shops = getUsersShopsByShopsId($product->users_shops_id);

            $product->shops = (object)[];
            if ($shops) {
                $product->shops = $shops;
            }

            $hargaDiskon = $product->harga - ($product->harga * $product->diskon / 100);
            $product->berat = $product->berat . ' kg';
            $product->harga = 'Rp ' . number_format($product->harga, 0, ',', '.');
            $product->harga_ori = $product->harga;
            $product->images = $images;
            $product->harga_diskon_ori = $hargaDiskon;
            $product->harga_diskon = 'Rp ' . number_format($hargaDiskon, 0, ',', '.');

            // TODO: tambah terjual
            $sold = 0;
            $product->sold = $sold;
            // TODO: tambah rating
            $rating = '0.0';
            $product->rating = $rating;
            $product->harga_diskon = 'Rp ' . number_format($hargaDiskon, 0, ',', '.');
        }

        return $this->sendResponse(0, 'Success', $products);
    }

    public function getShipping(Request $request)
    {
        $data = DB::table('shipping_method')->get();

        $shops = getUsersShops();
        if ($shops == false) {
            return $this->sendError(2, 'Anda tidak memiliki toko !', []);
        }

        $shippingShops = DB::table('users_shops_shipping')->where('shops_id', $shops->id)->pluck('shipping_id')->toArray();

        foreach ($data as $value) {
            $value->selected = false;
            if (in_array($value->id, $shippingShops)) {
                $value->selected = true;
            }
        }

        if ($data) {
            return $this->sendResponse(0, 'Success', $data);
        } else {
            return $this->sendError(2, 'Error !', []);
        }
    }

    public function saveShipping(Request $request)
    {
        $data = $request->data;
        Log::info('test', $data);

        $shops = getUsersShops();
        if ($shops == false) {
            return $this->sendError(2, 'Anda tidak memiliki toko !', []);
        }

        $shops_id = $shops->id;

        $shipping_ids = [];
        foreach ($data as $value) {
            $exists = DB::table('users_shops_shipping')
                ->where('shipping_id', $value['id'])
                ->where('shops_id', $shops_id)
                ->first();

            if (!empty($exists) && $value['selected']) {
                $shipping_ids[] = $value['id'];
            } elseif (!empty($exists) && !$value['selected']) {
                DB::table('users_shops_shipping')
                    ->where('id', $exists->id)
                    ->delete();
            } elseif (empty($exists) && $value['selected']) {
                DB::table('users_shops_shipping')->insert(['shipping_id' => $value['id'], 'shops_id' => $shops_id]);
            }
        }

        return $this->sendResponse(0, 'Success', $data);
    }

    public function getShippingCheckout(Request $request)
    {
        $users_shops_id = $request->users_shops_id;

        $data = DB::table('users_shops_shipping')
            ->leftJoin('shipping_method', 'shipping_method.id', '=', 'users_shops_shipping.shipping_id')
            ->where('shops_id', $users_shops_id)
            ->get([
                'shipping_method.id',
                'shipping_method.name'
            ]);

        if ($data) {
            return $this->sendResponse(0, 'Success', $data);
        } else {
            return $this->sendError(2, 'Error !', []);
        }
    }

    public function getPaymentMethod(Request $request)
    {
        $data = DB::table('payment_method')
            ->where('status', 1)
            ->get([
                'id',
                'pm_title',
                'pm_logo'
            ]);

        if ($data) {
            return $this->sendResponse(0, 'Success', $data);
        } else {
            return $this->sendError(2, 'Error !', []);
        }
    }

    public function createOrder(Request $request)
    {
        Log::info($request->all());
        $rules = [
            'product' => 'required',
            'alamat_id' => 'required',
            'shipping_id' => 'required',
            'payment_method_id' => 'required',
        ];

        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(
                1,
                validationMessage($validator->errors())
            );
        }

        $user_id = Auth::user()->id;

        $alamat_id = $request->alamat_id;
        $product = $request->product;
        $shipping_id = $request->shipping_id;
        $payment_method_id = $request->payment_method_id;

        $transaction_code = generateOrderCode('FF');

        $allTotal = 0;
        $shopId = '';
        $itemsDetail = [];
        foreach ($product as $value) {
            $product = DB::table('products')->where('id', $value['id'])->first();

            if (!empty($product)) {
                $harga = $product->harga - ($product->harga * $product->diskon / 100);
                $total = $harga * $value['qty'] ?? 1;
                $trDetail = [
                    'transaction_code' => $transaction_code,
                    'shop_id' => $product->users_shops_id,
                    'product_id' => $value['id'],
                    'price' => $harga,
                    'qty' => $value['qty'] ?? 1,
                    'total' => $total
                ];
                DB::table('transaction_detail')->insert($trDetail);

                $allTotal += $total;

                $shopId = $product->users_shops_id;

                $itemsDetail[] = [
                    'id' => $value['id'],
                    'name' => $product->nama_produk,
                    'price' => $harga,
                    'quantity' => $value['qty']
                ];
            }
        }

        $feeTotal = getFeeList(true);
        $fee = getFeeList();
        foreach ($fee as $value) {
            $itemsDetail[] = [
                'id' => $value->id,
                'name' => $value->title,
                'price' => $value->fee,
                'quantity' => 1
            ];
        }

        $pm = DB::table('payment_method')->where('id', $payment_method_id)->first();

        $body = (object) [
            "transaction_details" => (object) [
                "order_id" => $transaction_code,
                "gross_amount" => $allTotal + $feeTotal,
            ],
            "customer_required" => false,
            "customer_details" => (object) [
                "first_name" => Auth::user()->name,
                "last_name" => Auth::user()->name,
                "email" => Auth::user()->email,
                "phone" => Auth::user()->phone
            ],
            "item_details" => $itemsDetail,
            "enabled_payments" => getEnablePayment($pm->pm_code),
            "usage_limit" => 1,
            "expiry" => (object) [
                "duration" => 60,
                "unit" => "minutes"
            ],
        ];

        $dataResponse = Midtrans::useSnapMidtrans($body);
        if (isset($dataResponse['payment_url'])) {
            $tr_data = [
                'transaction_code' => $transaction_code,
                'user_id' => $user_id,
                'shop_id' => $shopId,
                'total_amount' => $allTotal + $feeTotal,
                'subtotal' => $allTotal,
                'fee' => $feeTotal,
                'payment_method_id' => $payment_method_id,
                'shipping_id' => $shipping_id,
                'alamat_id' => $alamat_id,
                'transaction_url' => $dataResponse['payment_url'],
            ];

            DB::table('transaction')->insert($tr_data);

            return $this->sendResponse(0, 'Success', $dataResponse);
        }

        return $this->sendError(2, 'Gagal', []);
    }

    public function getPlatformFee(Request $request)
    {
        $data = getFeeList();

        return $this->sendResponse(0, 'Success', $data);
    }
}
