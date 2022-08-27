<?php
// +----------------------------------------------------------------------
// | KQAdmin [ 基于Laravel后台快速开发后台 ]
// | 快速laravel后台管理系统，集成了，图片上传，多图上传，批量Excel导入，批量插入，修改，添加，搜索，权限管理RBAC,验证码，助你开发快人一步。
// +----------------------------------------------------------------------
// | Copyright (c) 2012~2019 www.haoxuekeji.cn All rights reserved.
// +----------------------------------------------------------------------
// | Laravel 原创视频教程，文档教程请关注 www.heibaiketang.com
// +----------------------------------------------------------------------
// | Author: kongqi <531833998@qq.com>`
// +----------------------------------------------------------------------

namespace App\Http\Controllers\Admin;
use App\ExtendClass\CacheUser;
use App\Jobs\ProcessBbs;
use App\Jobs\ProcessVideoShortMod;
use App\Models\Bbs;
use App\Models\Category;
use App\Models\CommBbs;
use App\Models\CommCate;
use App\Models\User;
use App\Services\UiService;
use App\TraitClass\BbsTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommBbsController extends BaseCurlController
{
    use VideoTrait,BbsTrait;
    //那些页面不共享，需要单独设置的方法
    //public $denyCommonBladePathActionName = ['create'];
    //设置页面的名称
    public $pageName = '帖子记录';

    public array $officialType = [
        0 => [
            'id' => 0,
            'name' => '官方',
        ],
        1 => [
            'id' => 1,
            'name' => '裸聊',
        ],
        2 => [
            'id' => 2,
            'name' => '经纪人',
        ],
        3 => [
            'id' => 3,
            'name' => '楼凤',
        ],
    ];

    //1.设置模型
    public function setModel(): CommBbs
    {
        return $this->model = new CommBbs();
    }

    //2.首页的数据表格数组
    public function indexCols(): array
    {
        //这里99%跟layui的表格设置参数一样
        //要返回给数组
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
                'field' => 'category_name',
                'minWidth' => 120,
                'title' => '版块名称',
                'align' => 'center'
            ],
            [
                'field' => 'title',
                'minWidth' => 80,
                'title' => '标题',
                'hide' => true,
                'align' => 'center'
            ],
            [
                'field' => 'content',
                'minWidth' => 120,
                'title' => '内容缩略',
                'align' => 'left'
            ],
            [
                'field' => 'likes',
                'minWidth' => 80,
                'title' => '点赞数',
                'align' => 'center',
            ],
            [
                'field' => 'comments',
                'minWidth' => 80,
                'title' => '评论数',
                'align' => 'center',
            ],
            [
                'field' => 'wx',
                'minWidth' => 80,
                'title' => '微信号',
                'hide' => true,
                'align' => 'center',
            ],
            [
                'field' => 'game_gold',
                'minWidth' => 80,
                'title' => '所需金币',
                'hide' => true,
                'align' => 'center',
            ],
            [
                'field' => 'buyers',
                'sort' => 1,
                'minWidth' => 80,
                'title' => '购买数量',
                'align' => 'center',
            ],
            [
                'field' => 'buyAmount',
                'minWidth' => 80,
                'title' => '购买金额',
                'align' => 'center',
            ],
            [
                'field' => 'rewards',
                'minWidth' => 80,
                'title' => '打赏数',
                'hide' => true,
                'align' => 'center',
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '发布时间',
                'align' => 'center'
            ],
            [
                'field' => 'video',
                'minWidth' => 150,
                'title' => '视频',
                'hide' => true,
                'align' => 'center'
            ],
            [
                'field' => 'video_picture',
                'minWidth' => 150,
                'title' => '封面图片',
                'hide' => true,
                'align' => 'center'
            ],
            [
                'field' => 'thumbs',
                'minWidth' => 150,
                'title' => '相册',
                'hide' => true,
                'align' => 'center'
            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '审核',
                'align' => 'center',
            ],
            [
                'field' => 'sort',
                'minWidth' => 80,
                'title' => '排序',
                'sort' => 1,
                'align' => 'center',
            ],
            [
                'field' => 'sync',
                'minWidth' => 80,
                'title' => '线路',
                'hide' => true,
                'align' => 'center',
            ],
            [
                'field' => 'updated_at',
                'minWidth' => 150,
                'title' => '更新时间',
//                'hide' => true,
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
            [
                'field' => 'id',
                'type' => 'text',
                'name' => '文章编号',
            ],
            [
                'field' => 'query_category_id',
                'type' => 'select',
                'name' => '版块',
                'default' => '1',
                'data' => array_merge([['id' => '', 'name' => '全部']], CommCate::get()->toArray())

            ]

        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function setOutputHandleBtnTpl($shareData)
    {
        $data = $this->defaultHandleBtnAddTpl($shareData);
        if($this->isCanDel()){
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '修正封面',
                'id' => 'btn-updateCover',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "update_cover",
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
                case 'update_cover':
                    foreach ($id_arr as $primaryKey){
                        $one = CommBbs::query()->where($id, $primaryKey)->first(['id','video']);
                        $video = json_decode($one->video,true);
                        if(!empty($video[0])){
                            $ext = pathinfo($video[0]);
                            $coverImg = $ext['dirname'].'/slice/'.$ext['filename'].'jpg';
                            CommBbs::query()->where($id, $primaryKey)->update(['video_picture'=>json_encode([$coverImg])]);
                        }
                    }
                    $r=true;
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
    //4.编辑和添加页面表单数据
    public function setOutputUiCreateEditForm($show = '')
    {
        $show && $show->url = '';
        if ($show && ($show->video??false) && $show->video != '[]') {
            $show->url = json_decode($show->video)[0];
        }
        $data = [
            [
                'field' => 'author_id',
                'type' => 'select',
                'name' => '关联官方用户',
                'must' => 1,
                'verify' => 'rq',
                'default' => 0,
                'data' => $this->getOfficialUsers()->toArray()
            ],
            [
                'field' => 'official_type',
                'type' => 'radio',
                'name' => '官方用户标识',
                'must' => 0,
                'default' => 0,
                'verify' => 'rq',
                'data' => $this->officialType
            ],
            [
                'field' => 'sort',
                'type' => 'text',
                'name' => '排序(值越大越靠前)',
                'must' => 1,
                'default' => 0,
                'verify' => 'rq'
            ],
            [
                'field' => 'category_id',
                'type' => 'select',
                'name' => '版块',
                'must' => 1,
                'verify' => 'rq',
                'default' => 0,
                'data' => array_merge($this->uiService->allDataArr('请选择版块'), $this->uiService->treeData(CommCate::get()->toArray(), 0))//树形select
            ],
            [
                'field' => 'title',
                'type' => 'text',
                'name' => '标题',
                'must' => 0
            ],
            [
                'field' => 'content',
                'type' => 'textarea',
                'name' => '内容',
                'verify' => 'rq',
                'must' => 1
            ],
            [
                'field' => 'thumbs',
                'type' => 'imgMore',
                'default' => '',
                'name' => '相册',
                'must' => 0,
                'verify' => ''
            ],
            [
                'field' => 'video',
                'type' => 'movie',
                'name' => '视频',
                'sync' => $show ? $show->sync : 0,
                'url' => $show ? $show->url : '',
//                 'value' => $show ? \App\Jobs\VideoSlice::getOrigin($show->sync,$show->url) :''
                'value' => $show ? $show->url :''
            ],
            [
                'field' => 'game_gold',
                'type' => 'number',
                'name' => '游戏所需骚豆',
                'value' => ($show && ($show->game_gold>0)) ? $show->game_gold : 0,
            ],
            [
                'field' => 'wx',
                'type' => 'text',
                'name' => '微信号',
            ],
            [
                'field' => 'wy_download_url',
                'type' => 'text',
                'name' => '下载链接一',
            ],
            [
                'field' => 'wy_get_code',
                'type' => 'text',
                'name' => '下载链接一提取码',
            ],
            [
                'field' => 'ali_download_url',
                'type' => 'text',
                'name' => '下载链接二',
            ],
            [
                'field' => 'ali_get_code',
                'type' => 'text',
                'name' => '下载链接二提取码',
            ],
        ];
        //赋值到ui数组里面必须是`form`的key值
        $this->uiBlade['form'] = $data;
    }

    //表单验证规则

    /*public function checkRule($id = '')
    {
        if ($id) {
            //$id值存在，表示编辑的规则，可以写你的验证规则，跟laravel写法一样，只是抽出来而已
        }
        return [

        ];
    }*/

    public function checkRuleFieldName(): array
    {
        return [
            'name' => '名称',
            'category_id' => '分类'
        ];
    }


    //弹窗大小
    public function layuiOpenWidth()
    {
        return '80%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight()
    {
        return '80%'; // TODO: Change the autogenerated stub
    }

    public function setListOutputItemExtend($item)
    {
        $item->category_name = $item->category['name'] ?? '';
        $item->status = UiService::switchTpl('status', $item,0,"是|否");
        $item->buyAmount = $item->buyers * $item->game_gold;
        return $item;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        $thumbs = $this->rq->input('thumbs','');
        $model->game_gold = $this->rq->input('game_gold',0);
        if(!$thumbs){
            $model->thumbs = '[]';
        } else {
            $fixPic = [];
            $raw = json_decode($thumbs,true);
            foreach ($raw as $item) {
                $fixPic[] = $item['path']??$item;
            }
            $model->thumbs = json_encode($fixPic);
        }

        $video = $this->rq->input('video','[]');
        if(empty($video) || ($video == '[]')){
            $model->video = '[]';
        } else {
            $pathInfo = pathinfo($video);
            if($pathInfo['extension']=='mp4'){
                $filename = $pathInfo['filename'];
                $video = '/public'.env('SLICE_DIR','/slice').'/hls/'.$filename.'/'.$filename.'.m3u8';
            }
            $model->video = json_encode([$video]);
        }

        $videoPicture = $this->rq->input('video_picture','');
        if(!$videoPicture){
            $model->video_picture = '[]';
        }
        $model->sync = env('SFTP_SYNC',2);
        $userInfo = CacheUser::user($this->rq->input('author_id'));
        $model->author_avatar = $userInfo->avatar;
        $model->author_nickname = $userInfo->nickname;
        $model->author_location_name = $userInfo->location_name;
        $model->author_level = $userInfo->level;
        $model->author_vip = $userInfo->vip;
        $model->author_member_card_type = $userInfo->member_card_type;
        $model->author_sex = $userInfo->sex;
        $model->author_is_office = $userInfo->is_office;
    }

    protected function afterSaveSuccessEvent($model, $id = '')
    {
        $isVideo = ($_REQUEST['callback_upload'] ?? 0);
        $job = new ProcessBbs($model, 1, $isVideo);
        $this->dispatch($job->onQueue('high'));
        return $model;
    }
}