<?php

namespace App\Console\Commands;

use App\TraitClass\DayStatisticTrait;
use App\TraitClass\RobotTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReportSend extends Command
{

    use DayStatisticTrait,RobotTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram_bot_report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle()
    {

        $data = $this->getDayStatisticHashData();
//        dd($data);

        $msg = '落地页访问：'.$data['hour_lp_access'].' / '.$data['day_lp_access']."\n";
        $msg .= '总新增：'.$data['hour_inc_user'].' / '.$data['day_inc_user']."\n";
        $msg .= 'ios新增：'.$data['hour_inc_ios_user'].' / '.$data['day_inc_ios_user']."\n";
        $msg .= '安卓新增：'.$data['hour_inc_android_user'].' / '.$data['day_inc_android_user']."\n";
        $msg .= '总充值：'.$data['hour_total_recharge'].' / '.$data['day_total_recharge']."\n";

        $msg .= "\n";

        $msg .= '金币充值：'.$data['hour_gold_recharge'].' / '.$data['day_gold_recharge']."\n";
        $msg .= 'vip充值：'.$data['hour_vip_recharge'].' / '.$data['day_vip_recharge']."\n";
        $msg .= 'ios充值：'.$data['hour_ios_recharge'].' / '.$data['day_ios_recharge']."\n";
        $msg .= '安卓充值：'.$data['hour_android_recharge'].' / '.$data['day_android_recharge']."\n";
        $msg .= '新增充值：'.$data['hour_new_user_recharge'].' / '.$data['day_new_user_recharge']."\n";
        $msg .= '老用户充值：'.$data['hour_old_user_recharge'].' / '.$data['day_old_user_recharge']."\n";

        $msg .= "\n";

        $startDate = date('Y-m-d '.'00:00:00');
        $endDate = date('Y-m-d '.'23:59:59');
        $channelDayRecharge = DB::table('orders')
            ->where('created_at','>=',$startDate)
            ->where('created_at','<=',$endDate)
            ->where('status',1)
            ->where('channel_id','>',0)
            ->sum('amount');
        $officialDayRecharge = DB::table('orders')
            ->where('created_at','>=',$startDate)
            ->where('created_at','<=',$endDate)
            ->where('status',1)
            ->where('channel_id','=',0)
            ->sum('amount');

        $msg .= '渠道充值：'.$channelDayRecharge."\n";
        $msg .= '官方充值：'.$officialDayRecharge."\n";
        $msg .= '拉起订单数：'.$data['hour_total_order'].' / '.$data['day_total_order']."\n";
        $msg .= '成功订单数：'.$data['hour_success_order'].' / '.$data['day_success_order']."\n";

        $msg .= "\n";

        $msg .= "渠道新增：".$data['day_inc_channel_user']."\n";
        $msg .= "渠道扣量后新增：".$data['day_channel_deduction_increase_user']."\n";

        $this->RobotSendMsg($msg);
        return 0;
    }

}
