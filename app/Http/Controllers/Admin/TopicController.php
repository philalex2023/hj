<?php

namespace App\Http\Controllers\Admin;

use App\Models\DataSource;
use App\Models\Tag;
use App\Models\Topic;
use App\TraitClass\CatTrait;
use App\TraitClass\CommTrait;
use App\TraitClass\TagTrait;
use Illuminate\Support\Facades\DB;

class TopicController extends BaseCurlController
{
    use CatTrait,TagTrait,CommTrait;

    public $pageName = '专题';

    public array $cats=[];

    public array $tags=[];

    public array $showTypes=[];

    public array $dataSource=[];

    public function setModel(): Topic
    {
        $this->cats = $this->getCatNavData();
        $this->tags = $this->getTagData();
        $this->showTypes = $this->getAppModuleShowType();
        $this->dataSource = $this->getDataSource();
        return $this->model = new Topic();
    }

    public function getDataSource(): array
    {
        return array_column([''=>['id'=>'','name'=>'选择数据源']]+DataSource::query()->get(['id','name'])->all(),null,'id');
    }

    public function indexCols(): array
    {
        return [
            /*[
                'type' => 'checkbox'
            ],*/
            [
                'field' => 'id',
                'width' => 80,
                'title' => '编号',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'name',
                'minWidth' => 150,
                'title' => '专题名称',
                'align' => 'center',
            ],
            [
                'field' => 'cid',
                'minWidth' => 150,
                'title' => '分类',
                'align' => 'center',
            ],
            [
                'field' => 'tag',
                'minWidth' => 100,
                'title' => '标签',
                'align' => 'center',
            ],
            [
                'field' => 'show_type',
                'minWidth' => 100,
                'title' => '展示样式',
                'align' => 'center',
            ],
            [
                'field' => 'data_source_id',
                'minWidth' => 100,
                'title' => '数据源',
                'align' => 'center',
            ],
            [
                'field' => 'sort',
                'width' => 80,
                'title' => '排序',
                'sort' => 1,
                'align' => 'center',
                'edit' => 1
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '创建时间',
                'align' => 'center'
            ],
            [
                'field' => 'updated_at',
                'minWidth' => 150,
                'hide' => true,
                'title' => '更新时间',
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

    public function beforeSaveEvent($model, $id = '')
    {
        $tag = $this->rq->input('tags',[]);
        $cid = $this->rq->input('cid',0);
        $dataSourceId = $this->rq->input('data_source_id',0);
        $dataSource = $this->rq->input('source',[]);
        $model->tag = json_encode($tag);
        $model->data_source = json_encode($dataSource);
        $videoIds = [];
        if(!empty($tag)){
            DB::table('video')->where('cid',$cid)->where('status',1)->chunkById(100,function ($items) use ($tag,&$videoIds,$model){
                foreach ($items as $item){
                    $jsonArr = json_decode($item->tag,true);
                    $intersect = array_intersect($jsonArr,$tag); //交集
                    if(!empty($intersect)){
                        $videoIds[] = $item->id;
                    }
                }
            });
        }
        /*if(!empty($dataSource)){
            $dataSources = DB::table('data_source')->whereIn('id',$dataSource)->pluck('contain_vids')->all();
            $idStr = implode('', $dataSources);
            $videoIds = array_unique([...$videoIds,...explode(',',$idStr)]);
        }*/
        if($dataSourceId>0){
            $idStr = DB::table('data_source')->where('id',$dataSourceId)->value('contain_vids');
            $videoIds = array_unique([...$videoIds,...explode(',',$idStr)]);
        }
        if(!empty($videoIds)){
            $model->contain_vids = implode(',',$videoIds);
        }
    }

    public function afterSaveSuccessEvent($model, $id = '')
    {
        //请除缓存 todo
        $redis = $this->redis();
        $redis->set('homeLists_fresh',1);
        $redis->del('short_category');
    }

    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '专题名称',
                'must' => 1,
                'verify' => 'rq',
                'default' => '',
            ],
            [
                'field' => 'cid',
                'type' => 'select',
                'name' => '分类',
                'must' => 1,
                'verify' => 'rq',
                'data' => $this->cats
            ],
            [
                'field' => 'tags', //这里不要跟字段一样，在事件中处理
                'type' => 'checkbox',
                'name' => '标签',
                'value' => ($show && ($show->tag)) ? json_decode($show->tag,true) : [],
                'data' => $this->tags
            ],
            [
                'field' => 'show_type',
                'type' => 'select',
                'name' => '展示样式',
                'default' => 7,
                'data' => $this->showTypes
            ],
            /*[
                'field' => 'source',
                'type' => 'checkbox',
                'name' => '数据源',
                'value' => ($show && ($show->data_source)) ? json_decode($show->data_source,true) : [],
                'data' => $this->dataSource
            ],*/
            [
                'field' => 'data_source_id',
                'type' => 'select',
                'name' => '数据源',
                'data' => $this->dataSource
            ],
            [
                'field' => 'sort',
                'type' => 'number',
                'name' => '排序',
                'default' => 0,
            ],
            [
                'field' => 'status',
                'type' => 'radio',
                'name' => '状态',
                'verify' => '',
                'default' => 1,
                'data' => $this->uiService->trueFalseData()
            ],
        ];
        $this->uiBlade['form'] = $data;
    }

    //表单验证
   /* public function checkRule($id = '')
    {
        $data = [
            'name'=>'required|unique:tag,name',
        ];
        //$id值存在表示编辑的验证
        if ($id) {
            $data['name'] = 'required|unique:tag,name,' . $id;
        }
        return $data;
    }

    public function checkRuleFieldName($id = '')
    {
        return [
            'name'=>'标签名称',
        ];
    }*/
    public function setListOutputItemExtend($item)
    {
        $tagArr = json_decode($item->tag,true)??[];
        $item->tag = $this->transferJsonFieldName($this->tags,$tagArr);
        $item->data_source_id = !isset($this->dataSource[$item->data_source_id])? '-' : $this->dataSource[$item->data_source_id]['name'];
        $item->show_type = $this->showTypes[$item->show_type]['name'];
        //$item->cid = $this->cats[$item->cid]['name'];
        $item->cid = !isset($this->cats[$item->cid])? '-' : $this->cats[$item->cid]['name'];
        return $item;
    }

    //弹窗大小
    public function layuiOpenWidth()
    {
        return '75%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight()
    {
        return '75%'; // TODO: Change the autogenerated stub
    }

}