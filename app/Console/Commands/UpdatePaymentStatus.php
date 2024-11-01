<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Repositories\OrderRepository;

class UpdatePaymentStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the history transactions payment status';

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
        $orderRepository = new OrderRepository();
        $result = $orderRepository->statusPaymentGetUpdates([]);
        \Log::info("Cron is running...");
        \Log::info("Schedule payment updates => Msg: " .  $result['data']->message);
        return 0;
    }
}