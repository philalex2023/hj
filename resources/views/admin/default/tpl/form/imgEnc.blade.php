<div class="upload-area" id="{{ md5($form_item['field']) }}">
    @php

        $form_item['type'] = 'hidden';
        $src = str_replace(['jpg','png','jpeg'],'htm',$form_item['value']);
    @endphp
    @include('admin.default.tpl.form.text',['form_item'=>$form_item])
    <div class="mb-10">
        <img style="width:150px" id="preview-img" ui-event="showImg" src="" class="iupload-area-img-show {{ $src?'':'none' }}"  alt="">
        <button class="layui-btn layui-btn-white layui-btn-sm iupload-area-img-show-btn {{ $form_item['value']?'':'none' }}" type="button">删除</button>
    </div>

        <button type="button" {{ $form_item['up_attr']??'' }}
        {{ $form_item['up_attr']??'' }}
        id="upload{{ (md5($form_item['field'])) }}"
        data-target="#{{ (md5($form_item['field'])) }}"
          data-event="upload" data-more="0"
                class="layui-btn layui-btn-sm  mr-10"><i class="layui-icon layui-icon-add-1"></i> {{ lang('点击上传') }}</button>

</div>
<script>
    let xhr = new XMLHttpRequest();
    xhr.open('get',"{{ $src }}");
    xhr.responseType = 'blob';
    xhr.onload = function () {
        document.getElementById("preview-img").src = window.URL.createObjectURL(xhr.response);
    }
    xhr.send();
</script>