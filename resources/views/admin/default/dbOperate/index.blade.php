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
                <form class="layui-form" method="POST" action="/admin/dbOperate/submit">
                {{ csrf_field() }}
                    <div class="layui-form-item">
                        <label class="layui-form-label">类型</label>
                            <div class="layui-input-block">
                                <input type="radio" name="type" value="select" title="查询" @if($type=="select") checked="" @endif >
                                <input type="radio" name="type" value="update" title="更新" @if($type=="update") checked="" @endif >
                                <input type="radio" name="type" value="del" title="删除" disabled="">
                            </div>
                        </div>
                    <div class="layui-form-item ">
                        <div class="layui-form-label">
                            <span class="layui-item-text">语句</span>
                        </div>
                        <div class="layui-input-block">
                            <input placeholder="sql" name="querySql" value="{{ $querySql??'' }}" class="layui-input" type="text" id="sql_line" >
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <div class="layui-input-block " style="margin-left: 0;">
                            <button type="submit" class="layui-btn" lay-submit="" lay-filter="activeSubmit">提交</button>
                            @if($numbers>0)
                                <button class="layui-btn layui-btn-primary" disabled="">共{{ $numbers }}条记录</button>
                            @endif
                        </div>
                    </div>
                </form>
                    <div class="layui-form-item layui-form-text">
                        <label class="layui-form-label">结果</label>
                        <div class="layui-input-block">
                            <textarea placeholder="显示内容" class="layui-textarea" rows="25" readonly>{{ $parameters??'' }}</textarea>
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




