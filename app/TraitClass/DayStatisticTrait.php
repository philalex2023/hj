<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait DayStatisticTrait
{
    use PHPRedisTrait;

    public function getDayStatisticHashData($d=0,$report=false): array
    {
        $redis = $this->redis();
        $dayData = date('Ymd');
        $nowTime = time();

        $starTime = strtotime(date('Y-m-d'));
        $dayEndTime = strtotime(date('Y-m-d 23:59:59'));
        if($d>0){
            $t = strtotime('-'.$d.' day');
            $dayData = date('Ymd',$t);
            $starTime = strtotime(date('Y-m-d 00:00:00',$t));
            $dayEndTime = strtotime(date('Y-m-d 23:59:59',$t));
        }
        $hourAgo = strtotime('-1 hour');

        //机器人0点报
        if($report){
            $now_time = time();
            if($now_time >= strtotime(date('Y-m-d 00:00:00')) && $now_time < strtotime(date('Y-m-d 00:10:00'))){
                $t = strtotime('-1 day');
                $dayData = date('Ymd',$t);
                $starTime = strtotime(date('Y-m-d 00:00:00',$t));
                $dayEndTime = strtotime(date('Y-m-d 23:59:59',$t));
                $hourAgo = $dayEndTime-3600;
                $nowTime = $dayEndTime;
            }
        }


//        $hashData['active_user'] = $redis->sCard('at_user_'.$dayData);
//        $hashData['online_user'] = $this->redis('video')->sCard('onlineUser_'.$dayData);
        $hashData['active_user'] = $redis->zCount('at_user_'.$dayData,$starTime,$dayEndTime);

        $hashData['online_user'] = $redis->zCount('online_user_'.$dayData,$starTime,$dayEndTime);

//        $hashData['keep_1'] = $redis->zCount('hj_keep_1_'.$dayData,$starTime,$dayEndTime);

        $hashData['hour_inc_user'] = $redis->zCount('new_increase_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_inc_user'] = $redis->zCount('new_increase_'.$dayData,$starTime,$dayEndTime);

        $hashData['keep_1'] = $hashData['active_user']-$hashData['day_inc_user'];

        $hashData['hour_inc_android_user'] = $redis->zCount('new_inc_android_'.$dayData,$hourAgo,$dayEndTime);
        $hashData['hour_inc_ios_user'] = $redis->zCount('new_inc_ios_'.$dayData,$hourAgo,$dayEndTime);
        $hashData['day_inc_android_user'] = $redis->zCount('new_inc_android_'.$dayData,$starTime,$dayEndTime);
        $hashData['day_inc_ios_user'] = $redis->zCount('new_inc_ios_'.$dayData,$starTime,$dayEndTime);

        $hashData['hour_gold_recharge'] = $this->sumRangeValue($redis->zRangeByScore('gold_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_gold_recharge'] = $this->sumRangeValue($redis->zRangeByScore('gold_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_vip_recharge'] = $this->sumRangeValue($redis->zRangeByScore('vip_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_vip_recharge'] = $this->sumRangeValue($redis->zRangeByScore('vip_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_new_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('new_user_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_new_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('new_user_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_old_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('old_user_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_old_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('old_user_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_total_recharge'] = round($hashData['hour_new_user_recharge'] + $hashData['hour_old_user_recharge'],2);
        $hashData['day_total_recharge'] = round($hashData['day_new_user_recharge'] + $hashData['day_old_user_recharge'],2);

//        $hashData['day_inc_recharge_user'] = $redis->sCard('day_inc_rec_user_'.$dayData);
        $hashData['day_inc_recharge_user'] = $redis->zCount('day_inc_rec_user_'.$dayData,$starTime,$dayEndTime);
        $hashData['day_inc_arpu'] = $hashData['day_inc_user']==0 ? 0 : round($hashData['day_total_recharge']/$hashData['day_inc_user'],2);

        $hashData['hour_success_order'] = $redis->zCount('vip_recharge_'.$dayData,$hourAgo,$nowTime) + $redis->zCount('gold_recharge_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_success_order'] = $redis->zCount('vip_recharge_'.$dayData,$starTime,$nowTime) + $redis->zCount('gold_recharge_'.$dayData,$starTime,$dayEndTime);

        $hashData['hour_total_order'] = $redis->zCount('day_pull_order_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_total_order'] = $redis->zCount('day_pull_order_'.$dayData,$starTime,$dayEndTime);

        $hourLpAccessArr = $redis->zRangeByScore('lp_ac_'.$dayData,$hourAgo,$nowTime);
        $hour_lp_access = 0;
        if(!empty($hourLpAccessArr)){
            $hour_lp_access = end($hourLpAccessArr)-$hourLpAccessArr[0];
        }
        $hashData['hour_lp_access'] = $hour_lp_access;
        $hashData['day_lp_access'] = $redis->zCount('lp_ac_'.$dayData,$starTime,$dayEndTime);

        //点击
        $hourLpHitArr = $redis->zRangeByScore('lp_hit_'.$dayData,$hourAgo,$nowTime);
        $hour_lp_hit = 0;
        if(!empty($hourLpHitArr)){
            $hour_lp_hit = end($hourLpHitArr)-$hourLpHitArr[0];
        }
        $hashData['hour_lp_hit'] = $hour_lp_hit;
        $hashData['day_lp_hit'] = $redis->zCount('lp_hit_'.$dayData,$starTime,$dayEndTime);

        $hashData['hour_android_recharge'] = $this->sumRangeValue($redis->zRangeByScore('android_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_android_recharge'] = $this->sumRangeValue($redis->zRangeByScore('android_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_ios_recharge'] = $this->sumRangeValue($redis->zRangeByScore('ios_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_ios_recharge'] = $this->sumRangeValue($redis->zRangeByScore('ios_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_inc_channel_user'] = $redis->zCount('new_inc_channel_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_inc_channel_user'] = $redis->zCount('new_inc_channel_'.$dayData,$starTime,$dayEndTime);

        $hashData['hour_inc_auto_user'] = $redis->zCount('new_inc_auto_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_inc_auto_user'] = $redis->zCount('new_inc_auto_'.$dayData,$starTime,$dayEndTime);

//        $hashData['day_channel_deduction_increase_user'] = $redis->get('ch_deduction_increase_user_'.$dayData);
        $oldDeductData = (int)$redis->zCount('ch_deduction_inc_user_'.$dayData,$starTime,$dayEndTime);
        $hashData['day_channel_deduction_increase_user'] = $oldDeductData + $this->sumRangeValue($redis->zRangeByScore('ch_deduct_inc_user_'.$dayData,$starTime,$dayEndTime));

        $day_up_master_income = DB::table('income_day')
            ->where('at_time','>=',$starTime)
            ->where('at_time','<=',$dayEndTime)
            ->sum('gold');
        $hashData['day_up_master_income'] = round($day_up_master_income/100,2);

        return $hashData;
    }

    public function sumRangeValue($rangeValues): int
    {
        $sum = 0;
        foreach ($rangeValues as $value) {
            $sum += floatval(explode(',',$value)[1]);
        }
        return round($sum,2);
    }
}