<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends BaseController
{
    use PHPRedisTrait;
    /**
     * 首页
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function index(){
        return $this->display();
    }
    public function home(){
        $redis = $this->redis();
        $dayData = date('Ymd');
        $nowTime = time();
        $starTime = strtotime(date('Y-m-d'));
        $hourAgo = strtotime('-1 hour');
        $hashData['keep_1'] = $redis->get('total_keep_1_'.$dayData);
        $hashData['active_user'] = $redis->sCard('active_user_'.$dayData);
        $hashData['online_user'] = $this->redis('video')->sCard('onlineUser_'.$dayData);//ok
        $hashData['hour_inc_user'] = $redis->zCount('new_increase_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_inc_user'] = $redis->zCount('new_increase_'.$dayData,$starTime,$nowTime);
        $hashData['day_inc_android_user'] = $redis->sCard('new_increase_android_'.$dayData);
        $hashData['day_inc_ios_user'] = $redis->sCard('new_increase_ios_'.$dayData);

        $hashData['hour_gold_recharge'] = $this->sumRangeValue($redis->zRangeByScore('gold_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_gold_recharge'] = $this->sumRangeValue($redis->zRangeByScore('gold_recharge_'.$dayData,$starTime,$nowTime));

        $hashData['hour_vip_recharge'] = $this->sumRangeValue($redis->zRangeByScore('vip_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_vip_recharge'] = $this->sumRangeValue($redis->zRangeByScore('vip_recharge_'.$dayData,$starTime,$nowTime));

        $hashData['hour_new_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('new_user_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_new_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('new_user_recharge_'.$dayData,$starTime,$nowTime));

        $hashData['hour_old_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('old_user_recharge_'.$dayData,$hourAgo,$nowTime));
        $hashData['day_old_user_recharge'] = $this->sumRangeValue($redis->zRangeByScore('old_user_recharge_'.$dayData,$starTime,$nowTime));

        $hashData['day_total_recharge'] = round($hashData['day_new_user_recharge'] + $hashData['day_old_user_recharge'],2);

        $hashData['day_inc_recharge_user'] = $redis->sCard('day_inc_recharge_user_'.$dayData);
        $hashData['day_inc_arpu'] = $hashData['day_inc_user']==0 ? 0 : round($hashData['day_total_recharge']/$hashData['day_inc_user'],2);

        $hashData['hour_success_order'] = $redis->zCount('vip_recharge_'.$dayData,$hourAgo,$nowTime) + $redis->zCount('gold_recharge_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_success_order'] = $redis->zCount('vip_recharge_'.$dayData,$starTime,$nowTime) + $redis->zCount('gold_recharge_'.$dayData,$starTime,$nowTime);

        $hashData['hour_total_order'] = $redis->zCount('day_pull_order_'.$dayData,$hourAgo,$nowTime);
        $hashData['day_total_order'] = $redis->zCount('day_pull_order_'.$dayData,$starTime,$nowTime);

        return $this->display(['data'=> $hashData]);
    }

    public function sumRangeValue($rangeValues): int
    {
        $sum = 0;
        foreach ($rangeValues as $value) {
            $sum += intval(explode(',',$value)[1]);
        }
        return round($sum,2);
    }

    public function map($type,Request $request){
        $this->setViewPath($type.'Map');
        return $this->display();
    }
}
