<?php

namespace App\Http\Controllers\Admin;

use App\Models\AdminVideo;
use App\Models\ShortComment;
use App\Models\VideoShort;
use App\Services\UiService;
use Illuminate\Http\Request;

class ShortCommentController extends BaseCurlIndexController
{
    public $pageName = '评论';

    public function setModel()
    {
        return $this->model = new ShortComment();
    }

    public function indexCols()
    {
        return [
            [
                'type' => 'checkbox'
            ],
            [
                'field' => 'id',
                'width' => 80,
                'title' => '编号',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'reply_cid',
                'minWidth' => 100,
                'title' => '回复编号',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'vid',
                'minWidth' => 80,
                'title' => '视频ID',
                'sort' => 1,
                'align' => 'center',
//                'edit' => 1
            ],
            [
                'field' => 'uid',
                'minWidth' => 80,
                'title' => '用户ID',
                'align' => 'center',
            ],
            [
                'field' => 'content',
                'minWidth' => 150,
                'title' => '评论内容',
                'align' => 'center',
            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '审核',
                'align' => 'center',
            ],
            [
                'field' => 'reply_at',
                'minWidth' => 150,
                'title' => '创建时间',
                'align' => 'center'
            ],
            [
                'field' => 'handle',
                'minWidth' => 150,
                'title' => '操作',
                'align' => 'center'
            ]
        ];
    }

    //3.设置搜索数据表单
    public function setOutputSearchFormTpl($shareData)
    {
        $data = [

            /*[
                'field' => 'query_like_name',//这个搜索写的查询条件在app/TraitClass/QueryWhereTrait.php 里面写
                'type' => 'text',
                'name' => '评论者id',
            ],
            [
                'field' => 'query_category_id',
                'type' => 'text',
                'name' => '文章id',
            ],*/
        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function setListOutputItemExtend($item)
    {
        $item->status = UiService::switchTpl('status', $item,0,"通过|待审核");
        $item->handle = UiService::editDelTpl(0,1);
        return $item;
    }

    public function setOutputHandleBtnTpl($shareData)
    {
        $data = [];
        if ($this->isCanDel()) {
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '删除',
                'data' => [
                    'data-type' => "allDel"
                ]
            ];
            $data[] = [
                'class' => 'layui-btn-dark',
                'name' => '审核',
                'id' => 'btn-audit',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "audit",
                    'data-value' => 0,
                ]
            ];
        }
        $this->uiBlade['btn'] = $data;
    }

    public function editTable(Request $request)
    {
        $this->rq = $request;
        $ids = $request->input('ids'); // 修改的表主键id批量分割字符串
        //分割ids
        $id_arr = explode(',', $ids);

        $id_arr = is_array($id_arr) ? $id_arr : [$id_arr];

        if (empty($id_arr)) {
            return $this->returnFailApi(lang('没有选择数据'));
        }
        //表格编辑过滤IDS
        $id_arr = $this->editTableFilterIds($id_arr);

        $field = $request->input('field'); // 修改哪个字段
        $value = $request->input('field_value'); // 修改字段值
        $id = 'id'; // 表主键id值

        $type_r = $this->editTableTypeEvent($id_arr, $field, $value);

        if ($type_r) {
            return $type_r;
        } else {
            switch ($field){
                case 'audit':
                    $updateIdArr = [];
                    foreach ($id_arr as $commentId){
                        $commentItem = ShortComment::query()->find($commentId);
                        if($commentItem->status==0){
                            if($commentItem->reply_cid>0){
                                ShortComment::query()->where('id',$commentItem->reply_cid)->increment('replies');
                            }else{
                                AdminVideo::query()->where('id',$commentItem->vid)->increment('comments');
                            }
                            $updateIdArr[] = $commentId;
                        }
                    }
                    $this->editTableAddWhere()->whereIn($id, $updateIdArr)->update(['status' => 1]);
                    $r = true;
                    break;
                case 'status':
                    if($value == 0){
                        return $this->returnFailApi(lang('已审核,若不符请删除'));
                    }
                    foreach ($id_arr as $commentId){
                        $commentItem = ShortComment::query()->find($commentId);
                        if($commentItem->reply_cid>0){
                            ShortComment::query()->where('id',$commentItem->reply_cid)->increment('replies');
                        }else{
                            AdminVideo::query()->where('id',$commentItem->vid)->increment('comments');
                        }
                    }
                    $r = $this->editTableAddWhere()->whereIn($id, $id_arr)->update(['status' => $value]);
                    break;
                default:
                    $r = $this->editTableAddWhere()->whereIn($id, $id_arr)->update([$field => $value]);
                    break;
            }

            if ($r) {
                $this->insertLog($this->getPageName() . lang('成功修改ids') . '：' . implode(',', $id_arr));
            } else {
                $this->insertLog($this->getPageName() . lang('失败ids') . '：' . implode(',', $id_arr));
            }
            return $this->editTablePutLog($r, $field, $id_arr);
        }
    }
}