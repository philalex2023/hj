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
                                <div class="layui-card-body">{{ $data['onlinePeople'] }} / {{ $data['activePeople'] }}</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">留存</div>
                                <div class="layui-card-body">140573</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">单小时新增/日新增</div>
                                <div class="layui-card-body">1236 / 40200</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">安卓新增/ios新增</div>
                                <div class="layui-card-body">33219 / 6983</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">金币充值(小时/日)</div>
                                <div class="layui-card-body">5100.00 / 76500.00</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">VIP充值(小时/日)</div>
                                <div class="layui-card-body">6200.00 / 98350.00</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">新用户充值(小时/日)</div>
                                <div class="layui-card-body">6450.00 / 85300.00</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">老用户充值(小时/日)</div>
                                <div class="layui-card-body">4850.00 / 89550.00</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">小时订单数(成功/拉起)</div>
                                <div class="layui-card-body">142 / 281</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">日订单数(成功/拉起)</div>
                                <div class="layui-card-body">3013 / 5115</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">新增会员/新增ARPU</div>
                                <div class="layui-card-body">1509 / 3.86</div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="layui-card">
                                <div class="layui-card-header">总充值</div>
                                <div class="layui-card-body">147850.00</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
