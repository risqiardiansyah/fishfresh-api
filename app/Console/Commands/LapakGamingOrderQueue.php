<?php

namespace App\Console\Commands;

use App\Helpers\LapakGaming;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LapakGamingOrderQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lapakgaming:order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run queued order to LapakGaming';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('QUEUE RUN '.date('Y-m-d H:i:s', strtotime('-5 minutes')).'-'.date('Y-m-d H:i:s'));

        $order = DB::table('transaction_queue')
            ->whereBetween('run_at', [date('Y-m-d H:i:s', strtotime('-5 minutes')), date('Y-m-d H:i:s')])
            ->get();
        foreach ($order as $value) {
            Log::info('PROCESSING '.$value->transaction_code.date('Y-m-d H:i:s'));
            
            $order = LapakGaming::resendGameItem($value->transaction_code);
            if ($order['success']) {
                Log::info('SUCCESS '.$value->transaction_code.' | '.date('Y-m-d H:i:s'));
                DB::table('transaction_queue')
                    ->where('transaction_code', $value->transaction_code)
                    ->delete();
            } elseif (!$order['success'] && $order['flags'] == 'QUEUE') {
                Log::info('BACK TO QUEUE '.$value->transaction_code.' | '.date('Y-m-d H:i:s'));

                DB::table('transaction_queue')
                    ->where('transaction_code', $value->transaction_code)
                    ->update(['run_at' => date('Y-m-d H:i:s', strtotime('+1 minutes'))]);
            } else {
                Log::info('FAILED '.$value->transaction_code.' | '.date('Y-m-d H:i:s'));

                if ($value->from == 'saldo') {
                    $transaction = DB::table('transaction')->where('transaction_code', $value->transaction_code)->first();
    
                    // FAILED == SEND MONEY BACK/
                    $saldo = updateUsersBalance($transaction->users_code, $transaction->total_amount);

                    $inc_data = [
                    'transaction_code' => generateOrderCode('VGN'),
                    'users_code' => $transaction->users_code,
                    'email' => $transaction->email,
                    'total_amount' => $transaction->total_amount,
                    'subtotal' => $transaction->total_amount,
                    'fee' => 0,
                    'transaction_url' => '#',
                    'from' => $transaction->from,
                    'payment_method' => $transaction->payment_method,
                    // 'no_reference' => $transaction_code,
                    'status' => 'success',
                    'voucher_discount' => 0,
                    'voucher_code' => '-',
                    'game_transaction_number' => '-',
                    'game_transaction_status' => 0,
                    'game_transaction_message' => 'Pengembalian Dana Transaksi gagal ' . $value->transaction_code,
                    'type' => 'topup',
                    'remaining_balance' => $saldo['users_balance'],
                    'created_at' => date('Y-m-d H:i:s', strtotime('+10 seconds'))
                    ];
                    DB::table('transaction')->insert($inc_data);
                }

                DB::table('transaction_queue')
                    ->where('transaction_code', $value->transaction_code)
                    ->delete();
            }
        }

        Log::info('QUEUE FINISH '.date('Y-m-d H:i:s'));
    }
}