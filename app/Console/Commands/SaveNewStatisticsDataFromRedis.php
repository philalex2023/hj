<?php

namespace App\Console\Commands;

use App\TraitClass\DayStatisticTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SaveNewStatisticsDataFromRedis extends Command
{
    use DayStatisticTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save_new_day_statistic_Data';

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
     */
    public function handle(): int
    {
        $d = 1;
        $hashData = $this->getDayStatisticHashData($d);
        $t = strtotime('-'.$d.' day');
        $at_time = strtotime(date('Y-m-d 00:00:00',$t));

        $data = [
            'active_user'=>(int)$hashData['active_user'],
            'online_user'=>(int)$hashData['online_user'],
            'keep_1'=>(int)$hashData['active_user'],
            'inc_user'=>(int)$hashData['day_inc_user'],
            'inc_android_user'=>(int)$hashData['day_inc_android_user'],
            'inc_ios_user'=>(int)$hashData['day_inc_ios_user'],
            'gold_recharge'=>(int)$hashData['day_gold_recharge']*100,
            'vip_recharge'=>(int)$hashData['day_vip_recharge']*100,
            'new_user_recharge'=>(int)$hashData['day_new_user_recharge']*100,
            'old_user_recharge'=>(int)$hashData['day_old_user_recharge']*100,
            'total_recharge'=>(int)$hashData['day_total_recharge']*100,
            'inc_recharge_user'=>(int)$hashData['day_inc_recharge_user'],
            'inc_arpu'=>$hashData['day_inc_arpu']*100,
            'success_order'=>(int)$hashData['day_success_order'],
            'total_order'=>(int)$hashData['day_total_order'],
            'lp_access'=>(int)$hashData['day_lp_access'],
            'android_recharge'=>(int)$hashData['day_android_recharge']*100,
            'ios_recharge'=>(int)$hashData['day_ios_recharge']*100,
            'inc_channel_user'=>(int)$hashData['day_inc_channel_user'],
            'inc_auto_user'=>(int)$hashData['day_inc_auto_user'],
            'channel_deduction_increase_user'=>(int)$hashData['day_channel_deduction_increase_user'],
            'at_time'=>$at_time,
        ];

        //dump($data);
        $exists = DB::table('hj_statistics_day')->where('at_time',$at_time)->exists();
        if(!$exists){
            DB::table('hj_statistics_day')->where('at_time',$at_time)->insert($data);
        }else{
            DB::table('hj_statistics_day')->where('at_time',$at_time)->update($data);
        }
        $this->info('######执行成功######');
        return 0;
    }
}
