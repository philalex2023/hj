<?php

namespace App\TraitClass;

trait SDTrait
{
    public function getSDColumns(): array
    {
        return [
            'register'=>['name'=>'SP注册','value'=>'','trend'=>''],
            'bind'=>['name'=>'SP绑定','value'=>'','trend'=>''],
            'bindRatio'=>['name'=>'绑定率','value'=>'','trend'=>''],
            'login'=>['name'=>'SP登陆人数','value'=>'','trend'=>''],
            'active_users'=>['name'=>'SP活跃人数','value'=>'','trend'=>''],
            'active_recharge_ratio'=>['name'=>'活跃充值比','value'=>'','trend'=>''],
            'increase_recharge_users'=>['name'=>'SP新增充值人数','value'=>'','trend'=>''],
            'increase_recharge_amount'=>['name'=>'SP新增充值金额','value'=>'','trend'=>''],
            'increase_recharge_ratio'=>['name'=>'新增充值占比','value'=>'','trend'=>''],
            'old_user_recharge_users'=>['name'=>'SP老用户充值人数','value'=>'','trend'=>''],
            'old_user_recharge_amount'=>['name'=>'SP老用户充值','value'=>'','trend'=>''],
            'recharge_users'=>['name'=>'SP充值人数','value'=>'','trend'=>''],
            'total_recharge_amount'=>['name'=>'SP总充值','value'=>'','trend'=>''],
            'online_recharge_amount'=>['name'=>'SP在线充值','value'=>'','trend'=>''],
            'online_recharge_ratio'=>['name'=>'在线占比','value'=>'','trend'=>''],
            'agent_recharge'=>['name'=>'SP代理充值','value'=>'','trend'=>''],
            'agent_recharge_ratio'=>['name'=>'代理占比','value'=>'','trend'=>''],
            'item_without'=>['name'=>'SP项目提现','value'=>'','trend'=>''],
            'item_revenue_amount'=>['name'=>'SP项目营收','value'=>'','trend'=>''],
            'item_revenue_ratio'=>['name'=>'营收比','value'=>'','trend'=>''],
            'buy_vip_users'=>['name'=>'购买VIP会员人数','value'=>'','trend'=>''],
            'buy_vip_amount'=>['name'=>'购买VIP会员总金额','value'=>'','trend'=>''],
            'buy_gold_users'=>['name'=>'购买金币人数','value'=>'','trend'=>''],
            'buy_gold_amount'=>['name'=>'购买金币的总金额','value'=>'','trend'=>''],
            'ARPU'=>['name'=>'APP日ARPU','value'=>'','trend'=>''],
            'ARPPU'=>['name'=>'APP日ARPPU','value'=>'','trend'=>''],
            'yesterday_keep'=>['name'=>'APP昨日留存','value'=>'','trend'=>''],
            'two_day_keep'=>['name'=>'APP2日留存','value'=>'','trend'=>''],
            'three_day_keep'=>['name'=>'APP3日留存','value'=>'','trend'=>''],
            'four_day_keep'=>['name'=>'APP4日留存','value'=>'','trend'=>''],
            'five_day_keep'=>['name'=>'APP5日留存','value'=>'','trend'=>''],
            'six_day_keep'=>['name'=>'APP6日留存','value'=>'','trend'=>''],
            'server_day_keep'=>['name'=>'APP7日留存','value'=>'','trend'=>''],
            'eight_day_keep'=>['name'=>'APP8日留存','value'=>'','trend'=>''],
            'nine_day_keep'=>['name'=>'APP9日留存','value'=>'','trend'=>''],
            'ten_day_keep'=>['name'=>'APP10日留存','value'=>'','trend'=>''],
        ];
    }
}