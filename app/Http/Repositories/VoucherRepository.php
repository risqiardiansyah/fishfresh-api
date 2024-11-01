<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VoucherRepository
{
    public function checkVoucher($request)
    {
        $now = date('Y-m-d');
        $kode_promo = $request->kode_promo;
        $payment_method = $request->payment_method;
        $order_amount = $request->order_amount;

        $voucher = DB::table('vouchers')
            ->where('vouchers_redeem_code', $kode_promo)
            ->where('isActive', 1)
            // ->where('voucher_discount_max', '>', 0)
            ->first();
        if (empty($voucher)) {
            return ['success' => false, 'msg' => 'Kode voucher tidak valid !', 'code' => '04'];
        }
        if ($voucher->voucher_discount_max <= 0) {
            return ['success' => false, 'msg' => 'Voucher telah habis digunakan !', 'code' => '00'];
        }
        if ($now < $voucher->voucher_start) {
            return ['success' => false, 'msg' => 'Voucher belum bisa digunakan !', 'code' => '05'];
        }
        if ($now > $voucher->voucher_end) {
            return ['success' => false, 'msg' => 'Voucher kadaluarsa !', 'code' => '04'];
        }
        if (!empty($payment_method) && $voucher->con_payment_method && !str_contains($voucher->payment_method, $payment_method)) {
            return ['success' => false, 'msg' => 'Voucher tidak bisa digunakan untuk metode pembayaran ini !', 'code' => '01'];
        }
        if (isset($order_amount) && $order_amount < $voucher->voucher_discount) {
            return ['success' => false, 'msg' => 'Tidak mencukupi minimum pembelian !', 'code' => '03'];
        }
        
        $result = [
            'vouchers_redeem_code' => $voucher->vouchers_redeem_code,
            'vouchers_title' => $voucher->vouchers_title,
            'vouchers_discount' => $voucher->voucher_discount,
            'payment_method' => $voucher->payment_method
        ];
        return ['success' => true, 'data' => $result];
    }
}
