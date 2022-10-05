@extends('admin.default.layouts.baseCont')
@section('content')
    <div class="main-warp">
        @include($base_blade_path.'.tpl.table')
    </div>
@endsection
@section('foot_js')
    <script>
        let listConfig = @json($form_item['list_config']);
        layui.use(['listTable'], function () {
            let listTable = layui.listTable;
            let cols = @json([$form_item['cols']]);

            //渲染
            listTable.render(listConfig.index_url, cols, {
                where: {

                }
            });
            //监听搜索
            listTable.search();
            //开启排序
            listTable.sort();
        });
    </script>
    {{--//追加，上面的配置保留，如果上面需要替换，那么单独设置这个页面--}}
    @if(isset($indexfootAddJavascript) && !empty($indexfootAddJavascript))
        @includeIf($indexfootAddJavascript)
    @endif
@endsection