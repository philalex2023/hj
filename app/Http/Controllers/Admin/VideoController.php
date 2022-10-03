<?php
namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessPreviewVideo;
use App\Jobs\ProcessResetRedisVideo;
use App\Jobs\ProcessSyncMiddleSectionTable;
use App\Jobs\ProcessSyncMiddleTable;
use App\Jobs\ProcessSyncMiddleTagTable;
use App\Jobs\ProcessVideoShortMod;
use App\Jobs\ProcessVideoSlice;
use App\Jobs\VideoSlice;
use App\Models\AdminVideo;
use App\Models\AdminVideoShort;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\Video;
use App\Services\UiService;
use App\TraitClass\CatTrait;
use App\TraitClass\CommTrait;
use App\TraitClass\GoldTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\TagTrait;
use App\TraitClass\TopicTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VideoController extends BaseCurlController
{
    use VideoTrait,CatTrait,TagTrait,GoldTrait,PHPRedisTrait,CommTrait,TopicTrait;

    public $pageName = '视频管理';

    public array $cats=[];

    public array $tags=[];

    public array $topics=[];

    public array $video_source = [
        ''=>['id'=>'','name'=>'全部'],
        1=>['id'=>1,'name'=>'上传'],
        3=>['id'=>3,'name'=>'萌堆采集'],
        4=>['id'=>4,'name'=>'up主上传'],
        5=>['id'=>5,'name'=>'海角采集'],
    ];

    public function setModel(): AdminVideo
    {
        $this->cats = $this->getCatNavData();
        $this->tags = $this->getTagData();
        $this->topics = [''=>['id'=>'','name'=>'请选择专题']]+$this->getSelectTopicData();
        return $this->model = new AdminVideo();
    }

    public function indexCols(): array
    {
        return [
            [
                'type' => 'checkbox',
                'fixed' => 'left'
            ],
            [
                'field' => 'id',
                'width' => 80,
                'title' => '编号',
                'sort' => 1,
                'fixed' => 'left',
                'align' => 'center'
            ],
            [
                'field' => 'name',
                'minWidth' => 150,
                'title' => '片名',
                'align' => 'center',
            ],
            [
                'field' => 'cid',
                'width' => 150,
                'title' => '分类',
                'align' => 'center',
                'edit' => 1
            ],
            /*[
                'field' => 'category_name',
                'width' => 150,
                'title' => '版块',
                'align' => 'center',
            ],*/
            [
                'field' => 'dev_type',
                'minWidth' => 95,
                'title' => '视频类型',
                'align' => 'center',
                'hide' => false
            ],
            [
                'field' => 'tag_name',
                'minWidth' => 100,
                'title' => '标签',
                'align' => 'center',
            ],
            [
                'field' => 'restricted',
                'minWidth' => 100,
                'title' => '观看限制',
                'align' => 'center',
            ],
            [
                'field' => 'gold',
                'minWidth' => 100,
                'title' => '所需金币',
//                'edit' => 1,
                'sort' => 1,
                'align' => 'center',
            ],
            [
                'field' => 'buyers',
                'minWidth' => 100,
                'title' => '购买次数',
                'sort' => 1,
                'align' => 'center',
            ],
            [
                'field' => 'author',
                'minWidth' => 80,
                'title' => '作者',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'sync',
                'minWidth' => 80,
                'title' => '线路',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'duration',
                'minWidth' => 150,
                'title' => '时长',
                'align' => 'center',
            ],
            [
                'field' => 'duration_seconds',
                'minWidth' => 150,
                'title' => '时长秒',
                'align' => 'center',
                'hide' => true,
            ],
            [
                'field' => 'views',
                'minWidth' => 150,
                'title' => '播放次数',
                'sort' => 1,
                'align' => 'center',
            ],
            [
                'field' => 'cover_img',
                'minWidth' => 150,
                'title' => '封面图',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'url',
                'minWidth' => 150,
                'title' => '源视频',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'hls_url',
                'minWidth' => 80,
                'title' => 'hls地址',
                'align' => 'center',
                'hide' => true
            ],
            /*[
                'field' => 'dash_url',
                'minWidth' => 80,
                'title' => 'dash地址',
                'align' => 'center',
                'hide' => true
            ],*/
            [
                'field' => 'type',
                'minWidth' => 120,
                'title' => '视频来源',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'cat',
                'minWidth' => 100,
                'title' => '版块类别(JSON)',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'tag',
                'minWidth' => 100,
                'title' => '标签(JSON)',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'tagNames',
                'minWidth' => 100,
                'title' => '自动标签内容',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'status',
                'minWidth' => 95,
                'title' => '是否上架',
                'align' => 'center',
            ],
            [
                'field' => 'sort',
                'minWidth' => 80,
                'title' => '排序',
                'edit' => 1,
                'sort' => 1,
                'align' => 'center',
            ],
            /*[
                'field' => 'is_top',
                'minWidth' => 80,
                'title' => '是否置顶',
                'sort' => 1,
                'align' => 'center',
            ],*/
            /*[
                'field' => 'is_recommend',
                'minWidth' => 80,
                'title' => '是否推荐',
                'align' => 'center',
            ],*/
            [
                'field' => 'created_at',
                'sort' => 1,
                'minWidth' => 170,
                'title' => '创建时间',
                'align' => 'center',
            ],
            [
                'field' => 'updated_at',
                'sort' => 1,
                'minWidth' => 170,
                'title' => '更新时间',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'handle',
                'minWidth' => 150,
                'title' => '操作',
                'fixed' => 'right',
                'align' => 'center'
            ]
        ];
    }

    /*public function getCateGoryData()
    {
        return array_merge($this->uiService->allDataArr('请选择分类'), $this->uiService->treeData(Category::checked()->get()->toArray(), 0));//树形select
    }*/

    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'cid',
                'type' => 'select',
                'name' => '分类',
                'must' => 1,
                'verify' => 'rq',
                'default' => 0,
                'data' => $this->cats
            ],
            /*[
                'field' => 'cats',
                'type' => 'checkbox',
                'name' => '版块',
                'verify' => '',
                'value' => ($show && ($show->cat)) ? json_decode($show->cat,true) : [],
                'data' => $cats
            ],*/
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '片名',
                'must' => 1,
                'verify' => 'rq',
            ],
            /*[
                'field' => 'tagNames',
                'type' => 'text',
                'tips' => '输入包含标签词的内容即可,格式不限,如:#内射#口交#人妻...',
                'name' => '自动标签内容'
            ],*/
            [
                'field' => 'tags',
                'type' => 'checkbox',
                'name' => '标签',
                'verify' => '',
                'value' => ($show && ($show->tag)) ? json_decode($show->tag,true) : [],
                'data' => $this->tags
            ],
            [
                'field' => 'cover_img',
                'type' => 'img',
                'name' => '封面图片',
                'value' => ($show && ($show->cover_img)) ? self::getDomain($show->sync).$show->cover_img: ''
//                'verify' => 'img'
            ],
            [
                'field' => 'url',
                'type' => 'video',
                'name' => '视频',
                'sync' => $show ? $show->sync : 0,
//                'value' => $show ? \App\Jobs\VideoSlice::get_slice_url($show->url,'dash',$show->sync) :''
            ],
            /*[
                'field' => 'title',
                'type' => 'text',
                'name' => '标题',
                'must' => 0,
                'default' => '',
            ],*/
            [
                'field' => 'restricted',
                'type' => 'radio',
                'name' => '观看限制',
                'must' => 0,
                'default' => 1,
                'verify' => 'rq',
                'data' => $this->restrictedType
            ],
            [
                'field' => 'gold',
                'type' => 'number',
                'name' => '所需金币',
                'value' => ($show && ($show->gold>0)) ? $show->gold/$this->goldUnit : 0,
                'verify' => 'rq',
            ],
            [
                'field' => 'status',
                'type' => 'radio',
                'name' => '是否上架',
                'verify' => '',
                'default' => 0,
                'data' => $this->uiService->trueFalseData()
            ],
            /*[
                'field' => 'is_recommend',
                'type' => 'radio',
                'name' => '推荐',
                'verify' => '',
                'default' => 0,
                'data' => $this->uiService->trueFalseData()
            ],*/
            /*[
                'field' => 'sync',
                'type' => 'radio',
                'name' => '启用专线',
                'verify' => '',
                'default' => 1,
                'data' => $this->uiService->trueFalseData()
            ],*/

        ];
        //赋值给UI数组里面,必须是form为key
        $this->uiBlade['form'] = $data;

    }

    //表单验证
    /*public function checkRule($id = '')
    {
        $data = [
            'name'=>'required|unique:video,name',
//            'cover_img'=>'required',
//            'cid'=>'required',
        ];
        //$id值存在表示编辑的验证
        if ($id) {
            $data['name'] = 'required|unique:video,name,' . $id;
        }
        return $data;
    }

    public function checkRuleFieldName($id = '')
    {
        return [
            'name'=>'片名',
//            'cover_img'=>'封面图片',
//            'cid'=>'分类',
        ];
    }*/

    /*public function setModelRelaction($model)
    {
        return $model->with('category');
    }*/

    public function setListOutputItemExtend($item)
    {
        $item->cid = !isset($this->cats[$item->cid])? '-' : $this->cats[$item->cid]['name'];
        $item->category_name = $this->getCatName($item->cat);
        $item->tag_name = $this->getTagName($item->tag_kv??[]);
        $item->status = UiService::switchTpl('status', $item,'','上架|下架');
        $item->is_top = UiService::switchTpl('is_top', $item,'','置顶|取消');
        $item->type = $item->type==0 ? '' : $this->video_source[$item->type]['name'];
        $item->dev_type = match ($item->dev_type){
            1 => '竖屏',
            default => '横屏'
        };
        $item->restricted = $this->restrictedType[$item->restricted]['name'];
        $item->gold = $item->gold/$this->goldUnit;
        return $item;
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function afterSaveSuccessEvent($model, $id = '')
    {
        if( isset($_REQUEST['callback_upload']) && ($_REQUEST['callback_upload']==1)){
            $job = new ProcessVideoSlice($model);
            $this->dispatch($job->onQueue('high'));
        }
        /*$resetJob = new ProcessResetRedisVideo($model->cat,$model->tag,$model);
        $this->dispatch($resetJob->onQueue('high'));*/

        Cache::forget('cachedVideoById.'.$model->id);
        $this->redis()->set('freshTag_'.$model->type,1);
        return $model;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        $cats = $this->rq->input('cats',[]);
        $model->cat = json_encode($cats);
        $tags = $this->rq->input('tags',[]);
        $model->tag = json_encode($tags);
        $model->author = admin('nickname');
        $model->gold = $this->rq->input('gold',0);
        $model->gold *= $this->goldUnit;
        //$model->sync = env('SFTP_SYNC',1);
        $model->updated_at = date('Y-m-d H:i:s');
        /*if($id > 0){ //编辑
            $originalData = $model->getOriginal();
            if($model->status != $originalData['status']){
                $model->is_top = 0;
            }
            $originalCat = json_decode($originalData['cat'],true);
            if($originalCat!=$cats){
                $model->is_top = 0;
            }
            if($model->status==0){
                $model->is_top = 0;
            }
        }*/
        if(isset($model->url)){
            //$model->dash_url = self::get_slice_url($model->url);
            $model->hls_url = self::get_slice_url($model->url,'hls');
            $model->cover_img = str_replace(self::getDomain($model->sync),'',$model->cover_img??'');
            if(!$model->cover_img){
                $model->cover_img = self::get_slice_url($model->url,'cover');
            }else{
                $this->syncUpload($model->cover_img,$model->sync);
            }
        }

        $tagsArr = [];
        foreach ($tags as $tagId){
            $tagsArr[$tagId] = $this->tags[$tagId]['name'];
        }
        $model->tag_kv = json_encode($tagsArr);
    }

    /*public function afterEditTableSuccessEvent($field, array $ids)
    {
        if($field==='sync'){
            foreach ($ids as $id){
                $row = AdminVideo::query()->find($id,['id','sync','url']);
                if($row->sync==1){
                    $job = new ProcessSyncVideo($row);
                    $this->dispatch($job);
                }
            }
        }
    }*/
    /*public function setOutputHandleBtnTpl($shareData)
    {
        $data = $this->defaultHandleBtnAddTpl($shareData);
        $data[] = [
            'name' => '同步',
            'id' => 'btn-sync',
        ];
        //赋值到ui数组里面必须是`btn`的key值
        $this->uiBlade['btn'] = $data;
    }*/

    //弹窗大小
    public function layuiOpenWidth()
    {
        return '75%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight()
    {
        return '95%'; // TODO: Change the autogenerated stub
    }

    public function handleResultModel($model): array
    {
        $cid = $this->rq->input('cid');
        //$cat = $this->rq->input('cat');
        //$tag = $this->rq->input('tag');
        $type = (int)$this->rq->input('type',0);

        $type>0 && $model=$model->where('type',$type);
        $cid>0 && $model=$model->where('cid',$cid);

        return parent::handleResultModel($model);

    }

    public function setOutputSearchFormTpl($shareData)
    {
        $data = [
            /*[
                'field' => 'cat',
                'type' => 'checkbox',
                'name' => '版块',
                'default' => [],
                'data' => array_merge($this->getCats(),[[
                    'id' => 0,
                    'name' => '无'
                ]])
            ],*/
            /*[
                'field' => 'tag',
                'type' => 'checkbox',
                'name' => '标签',
                'default' => [],
                'data' => array_merge($this->tags,[[
                    'id' => 0,
                    'name' => '无'
                ]])
            ],*/
            [
                'field' => 'id',
                'type' => 'text',
                'name' => '编号',
            ],
            [
                'field' => 'query_like_name',//这个搜索写的查询条件在app/TraitClass/QueryWhereTrait.php 里面写
                'type' => 'text',
                'name' => '片名',
            ],
            [
                'field' => 'query_status',
                'type' => 'select',
                'name' => '是否上架',
                'default' => '',
                'data' => $this->uiService->trueFalseData(1)
            ],
            [
                'field' => 'cid',
                'type' => 'select',
                'name' => '分类',
                'default' => '',
                'data' => $this->cats
            ],
            [
                'field' => 'topic',
                'type' => 'select',
                'name' => '专题',
                'default' => '',
                'data' => $this->topics
            ],
            [
                'field' => 'type',
                'type' => 'select',
                'name' => '来源',
                'default' => '',
                'data' => $this->video_source
            ],
        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function setOutputHandleBtnTpl($shareData)
    {
        $data = $this->defaultHandleBtnAddTpl($shareData);
        /*if($this->isCanDel()){
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '修正时长',
                'id' => 'btn-autoUpDuration',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "duration_seconds",
                    'data-value' => 0,
                ]
            ];
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '同步封面',
                'id' => 'btn-syncCoverImg',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "cover_img",
                    'data-value' => 0,
                ]
            ];
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '批量预览',
                'id' => 'btn-preview',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "preview",
                    'data-value' => 0,
                ]
            ];
        }*/
        if ($this->isCanEdit()) {
            $data[] = [
                'class' => 'layui-btn-success',
                'name' => '批量上架',
                'id' => 'btn-putOnTheShelf',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量上架吗",
                    'data-field' => "status",
                    'data-value' => 1,
                ]
            ];
            $data[] = [
                'class' => 'layui-btn-success',
                'name' => '批量下架',
                'id' => 'btn-downOnTheShelf',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量下架吗",
                    'data-field' => "status",
                    'data-value' => 0,
                ]
            ];
            $data[] = [
                'class' => 'layui-btn-success',
                'name' => '批量专题',
                'id' => 'btn-moreTopic',
                'data'=>[
                    'data-type' => "batchHandle",
                    'data-title' => "确定将所选视频添加到当前专题吗",
                    'data-field' => "batch_topic",
                    'data-value' => 0,
                ]
            ];
            /*$data[] = [
                'class' => 'layui-btn-dark',
                'name' => '智能打标签',
                'id' => 'btn-autoTag',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "tag_match",
                    'data-value' => 0,
                ]
            ];*/

            /*$data[] = [
                'class' => 'layui-btn-dark',
                'name' => '批量版块',
                'id' => 'btn-batchCat',
                'data'=>[
                    'data-type' => "batchHandle",
                    'data-input-type' => "checkbox",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "cat",
                ]
            ];*/
            /*$data[] = [
                'class' => 'layui-btn-dark',
                'name' => '批量标签',
                'id' => 'btn-batchTag',
                'data'=>[
                    'data-type' => "batchHandle",
                    'data-input-type' => "checkbox",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "tag",
                ]
            ];*/
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => 'VIP限制',
                'id' => 'btn-vipRestricted',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "vip_restricted",
                    'data-value' => 1,
                ]
            ];
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '金币限制',
                'id' => 'btn-goldRestricted',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "gold_restricted",
                    'data-value' => 2,
                ]
            ];
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '设置免费',
                'id' => 'btn-setFree',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "free_restricted",
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
        //金币处理
        if($field == 'gold'){
            $value *= $this->goldUnit;
        }

        $id = 'id'; // 表主键id值

        $type_r = $this->editTableTypeEvent($id_arr, $field, $value);

        if ($type_r) {
            return $type_r;
        } else {
            switch ($field){
                case 'cover_img':
                    $covers = Video::query()->whereIn($id, $id_arr)->get(['id','cover_img']);
                    foreach ($covers as $cover){
                        $this->syncUpload($cover->cover_img);
                    }
                    $r=true;
                    break;
                /*case 'preview':
                    $previews = Video::query()->whereIn($id, $id_arr)->get(['id','url','sync','dash_url','hls_url']);
                    //ProcessPreviewVideo::dispatchAfterResponse($previews);
                    $job = new ProcessPreviewVideo($previews);
                    $this->dispatch($job);
                    $r=true;
                    break;*/
                case 'vip_restricted':
                    Video::query()->whereIn($id, $id_arr)->update(['restricted'=>1]);
                    $r=true;
                    break;
                case 'gold_restricted':
                    Video::query()->whereIn($id, $id_arr)->update(['restricted'=>2]);
                    $r=true;
                    break;
                case 'free_restricted':
                    Video::query()->whereIn($id, $id_arr)->update(['restricted'=>0]);
                    $r=true;
                    break;
                case 'cat':
                    $value_arr = explode(',',$value);
                    $buildQueryVideo = Video::query()->whereIn($id, $id_arr);
                    $buildQueryVideo->update(['cat'=>json_encode($value_arr),'is_top'=>0]);
                    //队列执行更新版块中间表
                    ProcessSyncMiddleSectionTable::dispatchAfterResponse();
                    $r=true;
                    break;
                case 'tag':
                    $value_arr = explode(',',$value);
                    $buildQueryVideo = Video::query()->whereIn($id, $id_arr);
                    $tagPluck = DB::table('tag')->whereIn('id',$value_arr)->pluck('name','id');
                    $buildQueryVideo->update(['tag'=>json_encode($value_arr),'tag_kv'=>json_encode($tagPluck)]);
                    $redis = $this->redis();
                    $redis->set('freshTag_1',1);
                    $redis->set('freshTag_3',1);
                    $r=true;
                    break;
                case 'batch_topic':
                    $idArr = Video::query()->whereIn($id, $id_arr)->pluck('id')->all();
                    $topicBuild = Topic::query()->where('id',$value);
                    $originIdArr = explode(',',$topicBuild->value('contain_vids'));
                    $updateIdStr = array_unique([...$idArr,...$originIdArr]);
                    $topicBuild->update(['contain_vids'=>implode(',',$updateIdStr)]);
                    $r=true;
                    break;
                /*case 'tag_match':
                    $videos = Video::query()->whereIn($id, $id_arr)->get(['id','tag','name'])->toArray();
                    $tags = $this->tags;
                    foreach ($videos as $video){
                        $tagIds = [];
                        $tagArr = @json_decode($video['tag'],true);
                        if(empty($tagArr)){
                            foreach ($tags as $tag) {
                                $pos = strpos($video['name'], $tag['name']);
                                if($pos){
                                    $tagIds[] = $tag['id'];
                                }
                            }
                        }
                        if(!empty($tagIds)){
                            $tagStore = json_encode($tagIds);
                            Video::query()->where('id',$video['id'])->update(['tag'=>$tagStore]);
                        }
                    }
                    $r = true;
                    break;*/
                case 'duration_seconds':
                    $videos = Video::query()->whereIn($id, $id_arr)->get(['id','duration','duration_seconds'])->toArray();
                    foreach ($videos as $video){
                        if(!empty($video['duration'])){
                            if($video['duration_seconds']==0){
                                $duration_seconds = $this->transferSeconds($video['duration']);
                                Video::query()->where('id',$video['id'])->update(['duration_seconds' => $duration_seconds]);
                            }
                        }else{
                            if(!empty($video['duration_seconds'])){
                                $format = $this->formatSeconds($video['duration_seconds']);
                                Video::query()->where('id',$video['id'])->update(['duration' => $format]);
                            }
                        }
                    }
                    $r = true;
                    break;
                case 'status':
                    if($value==0){
                        $r = $this->editTableAddWhere()->whereIn($id, $id_arr)->update(['status' => $value,'is_top'=>0]);
                    }else{
                        $r = $this->editTableAddWhere()->whereIn($id, $id_arr)->update(['status' => $value]);
                    }
                    break;
                default:
                    $r = $this->editTableAddWhere()->whereIn($id, $id_arr)->update([$field => $value]);
                    break;
            }

            if ($r) {
                $this->insertLog($this->getPageName() . lang('成功修改ids') . '：' . implode(',', $id_arr));
                //清除缓存
                $this->resetHomeRedisData();
            } else {
                $this->insertLog($this->getPageName() . lang('失败ids') . '：' . implode(',', $id_arr));
            }
            foreach ($id_arr as $idItem){
                Cache::forget('cachedVideoById.'.$idItem);
            }
            return $this->editTablePutLog($r, $field, $id_arr);
        }

    }

}
