<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\HjStatisticsDay;
use App\Models\Role;

class StatController extends BaseCurlIndexController
{
    //页面信息
    public $pageName = '统计列表';

    //1.设置模型
    public function setModel()
    {
        $this->model = new HjStatisticsDay();

    }


    //2.首页设置列表显示的信息
    public function indexCols(): array
    {
        return [
            [
                'field' => 'active_user',
                'width' => 80,
                'title' => '日活',
//                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'online_user',
                'minWidth' => 120,
                'title' => '在线用户',
                'align' => 'center'
            ],
            [
                'field' => 'keep_1',
                'width' => 80,
                'title' => '留存',
                'align' => 'center',
            ],
            [
                'field' => 'inc_user',
                'width' => 120,
                'title' => '新增用户',
                'align' => 'center'

            ],
            [
                'field' => 'inc_android_user',
                'width' => 120,
                'title' => '新增安卓用户',
                'align' => 'center'
            ],
            [
                'field' => 'inc_ios_user',
                'minWidth' => 120,
                'title' => '新增苹果用户',
                'align' => 'center'
            ],
            [
                'field' => 'gold_recharge',
                'width' => 120,
                'title' => '金币充值',
                'align' => 'center'
            ],
            [
                'field' => 'vip_recharge',
                'width' => 120,
                'title' => 'VIP充值',
                'align' => 'center'
            ],
            [
                'field' => 'new_user_recharge',
                'width' => 120,
                'title' => '新用户充值',
                'align' => 'center'
            ],
            [
                'field' => 'old_user_recharge',
                'width' => 120,
                'title' => '老用户充值',
                'align' => 'center'
            ],
            [
                'field' => 'total_recharge',
                'width' => 120,
                'title' => '总充值',
                'align' => 'center'
            ],
            [
                'field' => 'inc_recharge_user',
                'width' => 120,
                'title' => '新增充值用户',
                'align' => 'center'
            ],
            [
                'field' => 'inc_arpu',
                'width' => 80,
                'title' => 'ARPU',
                'align' => 'center'
            ],
            [
                'field' => 'success_order',
                'width' => 120,
                'title' => '成功订单数',
                'align' => 'center'
            ],
            [
                'field' => 'total_order',
                'width' => 120,
                'title' => '拉起订单数',
                'align' => 'center'
            ],
            [
                'field' => 'lp_access',
                'width' => 120,
                'title' => '落地页访问数',
                'align' => 'center'
            ],
            [
                'field' => 'android_recharge',
                'width' => 100,
                'title' => '安卓充值',
                'align' => 'center'
            ],
            [
                'field' => 'ios_recharge',
                'width' => 100,
                'title' => '苹果充值',
                'align' => 'center'
            ],
            [
                'field' => 'inc_channel_user',
                'width' => 120,
                'title' => '渠道新增用户',
                'align' => 'center'
            ],
            [
                'field' => 'channel_deduction_increase_user',
                'width' => 120,
                'title' => '渠道扣量后新增用户',
                'align' => 'center'
            ],
            [
                'field' => 'inc_auto_user',
                'width' => 120,
                'title' => '自来量用户',
                'align' => 'center'
            ],
            [
                'field' => 'at_time',
                'width' => 150,
                'title' => '日期',
                'fixed' => 'right',
                'align' => 'center'
            ],
        ];
    }

    //3.设置搜索部分
    public function setOutputSearchFormTpl($shareData)
    {
        $data = [
            [
                'field' => 'at_time',
                'type' => 'date',
//                'attr' => 'data-range=true',
                'attr' => 'data-range=~',//需要特殊分割
                'name' => '时间范围',
            ],

        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function setListOutputItemExtend($item)
    {
        $item->at_time = date('Y-m-d',$item->at_time);
        $item->gold_recharge = round($item->gold_recharge/100,2);
        $item->vip_recharge = round($item->vip_recharge/100,2);
        $item->new_user_recharge = round($item->new_user_recharge/100,2);
        $item->old_user_recharge = round($item->old_user_recharge/100,2);
        $item->total_recharge = round($item->total_recharge/100,2);
        $item->android_recharge = round($item->android_recharge/100,2);
        $item->ios_recharge = round($item->ios_recharge/100,2);
        return $item;
    }

    public function handleResultModel($model): array
    {
        $atTime = $this->rq->input('at_time');
        if($atTime!=null){
            if($atTime > 0){
                $timeRangeArr = explode('~',$atTime);
                $startTime = strtotime($timeRangeArr[0].' 00:00:00');
                $endTime = strtotime($timeRangeArr[1].' 23:59:59');
                $model = $model->where('at_time','>=',$startTime)->where('at_time','<=',$endTime);
            }
        }
        return parent::handleResultModel($model);
    }

}
