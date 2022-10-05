@section('content')
    <div class="main-warp">
        @include($base_blade_path.'.tpl.table')
    </div>
    <div class="mt-35 text-center none">
{{--        <input type="hidden" name="_method" value="PUT">--}}
        <button class="layui-btn" type="button" lay-submit="" lay-filter="LAY-form-submit" id="LAY-form-submit">提交</button>
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
        });
    </script>
    {{--//追加，上面的配置保留，如果上面需要替换，那么单独设置这个页面--}}
    @if(isset($indexfootAddJavascript) && !empty($indexfootAddJavascript))
        @includeIf($indexfootAddJavascript)
    @endif
@endsection