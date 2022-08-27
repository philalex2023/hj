@extends('admin.default.layouts.baseCont')
@section('content')
    <style>
            
        </style>
        <div class="layui-card shadow panel ">
            <div class="layui-card-header ">
                控制台
                <div class="panel-action">
                    <a href="#" data-perform="panel-collapse"><i title="点击可折叠" class="layui-icon layui-icon-subtraction"></i></a>
                </div>
            </div>

            <div class="layui-card-body" id="collapseSearch">

                <div class="layui-form layui-form-pane">
                <form class="layui-form" method="POST" action="/admin/channelRefer/submit">
                {{ csrf_field() }}
                    <div class="layui-form-item">
                        {{--<label class="layui-form-label">筛选结果方式</label>
                            <div class="layui-input-block">
                                <input type="radio" name="type" value="checkList" title="未在检测名单中" @if($type=="checkList") checked="" @endif >
                                <input type="radio" name="type" value="redList" title="在已报红名单中" @if($type=="redList") checked="" @endif >
                            </div>
                        </div>--}}
                    {{--<div class="layui-form-item layui-form-text">
                        <label class="layui-form-label">域名列表</label>
                        <div class="layui-input-block">
                            <textarea id="sql_line" name="domain" placeholder="多个域名用','逗号隔开" class="layui-textarea" rows="5">{{ $domain??'' }}</textarea>
                        </div>
                    </div>--}}

                    <div class="layui-form-item">
                        <div class="layui-input-block " style="margin-left: 0;">
                            <button type="submit" class="layui-btn" lay-submit="" lay-filter="activeSubmit">查询</button>
                            @if($numbers>0)
                            <button class="layui-btn layui-btn-primary" disabled="">共{{ $numbers }}条记录</button>
                            @endif
                        </div>
                    </div>
                </form>
                    <div class="layui-form-item layui-form-text">
                        <label class="layui-form-label">结果</label>
                        <div class="layui-input-block">
                            <textarea placeholder="显示内容" class="layui-textarea" rows="30" readonly>{{ $parameters??'' }}</textarea>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        

        <script src="{{ ___('admin/layui/layui.js',$res_version??'') }}"></script>
        <script src="{{ ___('admin/jquery/jquery.min.js',$res_version??'') }}"></script>
        <script>
            
        </script>

    @endsection




