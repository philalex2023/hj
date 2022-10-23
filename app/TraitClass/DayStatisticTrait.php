<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait DayStatisticTrait
{
    use PHPRedisTrait;

    public function getDayStatisticHashData(): array
    {
        $redis = $this->redis();
        $dayData = date('Ymd');
        $nowTime = time();
        $dayEndTime = strtotime(date('Y-m-d 23:59:59'));
        $starTime = strtotime(date('Y-m-d'));
        $hourAgo = strtotime('-1 hour');

        $hashData['active_user'] = $redis->sCard('active_user_'.$dayData);
        $hashData['online_user'] = $this->redis('video')->sCard('onlineUser_'.$dayData);

        $hashData['keep_1'] = $redis->get('total_keep_1_'.$dayData);

        $hashData['hour_inc_user'] = $redis->zCount('new_increase_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_inc_user'] = $redis->zCount('new_increase_'.$dayData,$starTime,$dayEndTime);

        $hashData['day_inc_android_user'] = $redis->sCard('new_increase_android_'.$dayData);
        $hashData['day_inc_ios_user'] = $redis->sCard('new_increase_ios_'.$dayData);

        $hashData['hour_gold_recharge'] = $this->sumRangeValue($redis->zRangeByScore('gold_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_gold_recharge'] = $this->sumRangeValue($redis->zRangeByScore('gold_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_vip_recharge'] = $this->sumRangeValue($redis->zRangeByScore('vip_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_vip_recharge'] = $this->sumRangeValue($redis->zRangeByScore('vip_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_new_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('new_user_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_new_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('new_user_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_old_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('old_user_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_old_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('old_user_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['day_total_recharge'] = round($hashData['day_new_user_recharge'] + $hashData['day_old_user_recharge'],2);

        $hashData['day_inc_recharge_user'] = $redis->sCard('day_inc_recharge_user_'.$dayData);
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
        $hashData['day_lp_access'] = $redis->get('lp_ac_inc_'.$dayData);

        $hashData['hour_android_recharge'] = $this->sumRangeValue($redis->zRangeByScore('android_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_android_recharge'] = $this->sumRangeValue($redis->zRangeByScore('android_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_ios_recharge'] = $this->sumRangeValue($redis->zRangeByScore('ios_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_ios_recharge'] = $this->sumRangeValue($redis->zRangeByScore('ios_recharge_'.$dayData,$starTime,$dayEndTime));

        $hashData['hour_inc_channel_user'] = $redis->zCount('new_inc_channel_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_inc_channel_user'] = $redis->zCount('new_inc_channel_'.$dayData,$starTime,$dayEndTime);

        $hashData['hour_inc_auto_user'] = $redis->zCount('new_inc_auto_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_inc_auto_user'] = $redis->zCount('new_inc_auto_'.$dayData,$starTime,$dayEndTime);

        $hashData['day_channel_deduction_increase_user'] = $redis->get('channel_deduction_increase_user_'.$dayData);

        $day_up_master_income = DB::table('up_income_day')
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
            $sum += intval(explode(',',$value)[1]);
        }
        return round($sum,2);
    }
}