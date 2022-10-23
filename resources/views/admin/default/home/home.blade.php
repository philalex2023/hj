@extends('admin.default.layouts.baseCont')
@section('content')
    <div class="layui-row layui-col-space15">
        <div class="layui-card shadow panel">

            <div class="layui-card-header">{{ lang('首页-数据统计') }}<small> (未标注时间是今日实时数据)</small>
                <div class="panel-action"  >
                    <a href="#" data-perform="panel-collapse"><i  title="点击可折叠" class="layui-icon layui-icon-subtraction"></i></a>
                </div>
            </div>
            <div class="layui-card-body ">
                <div class="layui-bg-gray" style="padding: 10px;text-align: left">
                    <div class="layui-row layui-col-space15">
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">在线人数/日活</div>
                                <div class="layui-card-body">{{ $data['online_user'] }} / {{ $data['active_user'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">留存</div>
                                <div class="layui-card-body">{{ $data['keep_1'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">单小时新增/日新增</div>
                                <div class="layui-card-body">{{ $data['hour_inc_user'] }} / {{ $data['day_inc_user'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">安卓新增/ios新增</div>
                                <div class="layui-card-body">{{ $data['day_inc_android_user'] }} / {{ $data['day_inc_ios_user'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">金币充值(小时/日)</div>
                                <div class="layui-card-body">{{ $data['hour_gold_recharge'] }} / {{ $data['day_gold_recharge'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">VIP充值(小时/日)</div>
                                <div class="layui-card-body">{{ $data['hour_vip_recharge'] }} / {{ $data['day_vip_recharge'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">新用户充值(小时/日)</div>
                                <div class="layui-card-body">{{ $data['hour_new_user_recharge'] }} / {{ $data['day_new_user_recharge'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">老用户充值(小时/日)</div>
                                <div class="layui-card-body">{{ $data['hour_old_user_recharge'] }} / {{ $data['day_old_user_recharge'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">小时订单数(成功/拉起)</div>
                                <div class="layui-card-body">{{ $data['hour_success_order'] }} / {{ $data['hour_total_order'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">日订单数(成功/拉起)</div>
                                <div class="layui-card-body">{{ $data['day_success_order'] }} / {{ $data['day_total_order'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">新增会员/新增ARPU</div>
                                <div class="layui-card-body">{{ $data['day_inc_recharge_user'] }} / {{ $data['day_inc_arpu'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">总充值</div>
                                <div class="layui-card-body">{{ $data['day_total_recharge'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header"> 落地页访问量(小时/日)</div>
                                <div class="layui-card-body">{{ $data['hour_lp_access'] }} / {{ $data['day_lp_access'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header"> 安卓充值(小时/日)</div>
                                <div class="layui-card-body">{{ $data['hour_android_recharge'] }} / {{ $data['day_android_recharge'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header"> ios充值(小时/日)</div>
                                <div class="layui-card-body">{{ $data['hour_ios_recharge'] }} / {{ $data['day_ios_recharge'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header"> 渠道新增(小时/日)</div>
                                <div class="layui-card-body">{{ $data['hour_inc_channel_user'] }} / {{ $data['day_inc_channel_user'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header"> 自来量新增(小时/日)</div>
                                <div class="layui-card-body">{{ $data['hour_inc_auto_user'] }} / {{ $data['day_inc_auto_user'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header"> 渠道扣量后新增(日)</div>
                                <div class="layui-card-body">{{ $data['day_channel_deduction_increase_user'] }}</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
