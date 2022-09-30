<?php

namespace App\Http\Controllers\Admin;

use App\Models\DataSource;
use App\Models\Video;
use App\TraitClass\CatTrait;
use App\TraitClass\CommTrait;
use App\TraitClass\EsTrait;
use App\TraitClass\TagTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class DataSourceController extends BaseCurlController
{
    use CatTrait,TagTrait,CommTrait,EsTrait;

    public $pageName = '数据源';

    public array $cats=[];

    public array $tags=[];

    public array $deviceType=[
        0 => ['id'=>0,'name'=>'横版'],
        1 => ['id'=>1,'name'=>'竖版'],
    ];

    public array $dataType = [
        1 => ['id'=>1,'name'=>'标签'],
        2 => ['id'=>2,'name'=>'关键字'],
        3 => ['id'=>3,'name'=>'分类'],
        4 => ['id'=>4,'name'=>'最新上架'],
        5 => ['id'=>5,'name'=>'自定义'],
    ];

    public function setModel(): DataSource
    {
        $this->cats = $this->getCatNavData();
        $this->tags = $this->getTagData();
        return $this->model = new DataSource();
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
                'title' => '数据源名称',
                'align' => 'center',
            ],
            [
                'field' => 'video_type',
                'minWidth' => 150,
                'title' => '视频类型',
                'align' => 'center',
            ],
            [
                'field' => 'data_type',
                'minWidth' => 150,
                'title' => '数据类型',
                'align' => 'center',
            ],
            [
                'field' => 'data_value',
                'minWidth' => 150,
                'title' => '数据值',
                'align' => 'center',
            ],
            [
                'field' => 'video_num',
                'minWidth' => 150,
                'title' => '视频数量',
                'align' => 'center',
            ],
            [
                'field' => 'show_num',
                'minWidth' => 150,
                'title' => '首页展示量',
                'align' => 'center',
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

    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '数据源名称',
                'must' => 1,
                'verify' => 'rq',
                'default' => '',
            ],
            [
                'field' => 'video_type',
                'type' => 'select',
                'name' => '视频类型',
                'must' => 1,
                'verify' => 'rq',
                'data' => $this->deviceType
            ],
            [
                'field' => 'data_type',
                'type' => 'select',
                'name' => '数据类型',
                'data' => $this->dataType
            ],
            [
                'field' => 'data_value',
                'type' => 'text',
                'name' => '数据值',
            ],
            [
                'field' => 'cid',
                'type' => 'select',
                'name' => '分类',
                'must' => 0,
                'verify' => '',
                'data' => $this->cats
            ],
            [
                'field' => 'tags',
                'type' => 'checkbox',
                'name' => '标签',
                'value' => ($show && ($show->tag)) ? json_decode($show->tag,true) : [],
                'data' => $this->tags
            ],
            /*[
                'field' => 'sort',
                'type' => 'number',
                'name' => '排序',
                'default' => 0,
            ],*/
            /*[
                'field' => 'status',
                'type' => 'radio',
                'name' => '状态',
                'verify' => '',
                'default' => 1,
                'data' => $this->uiService->trueFalseData()
            ],*/
        ];
        $this->uiBlade['form'] = $data;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        $tagIds = $this->rq->input('tags',[]);
        $dataType = $this->rq->input('data_type',0);
        $videoType = $this->rq->input('video_type',0);
        $dataValue = $this->rq->input('data_value','');
        $cid = $this->rq->input('cid',0);
        $model->tag = json_encode([]);
        $videoIds = [];
        switch ($dataType){
            case 1: //标签
                if(!empty($tagIds)){
                    $tagName = [];
                    foreach ($tagIds as $v){
                        $tagName[] = $this->tags[$v]['name'];
                    }
                    $model->data_value = implode(',',$tagName);
                    $model->tag = json_encode($tagIds);
                    //
                    DB::table('video')
                        ->where('dev_type',$videoType)
                        ->where('status',1)
                        ->chunkById(1000,function ($items) use ($tagIds,&$videoIds,$model){
                        foreach ($items as $item){
                            $jsonArr = json_decode($item->tag,true);
                            $intersect = array_intersect($jsonArr,$tagIds); //交集
                            if(!empty($intersect)){
                                $videoIds[] = $item->id;
                            }
                        }
                    });
                    $model->contain_vids = implode(',',$videoIds);
                }
                break;
            case 2: //关键字
                if(!empty($dataValue)){
                    $keywords = explode(',',$dataValue);
                    $must = [
                        ['term' => ['status'=>1]],
                        ['term' => ['dev_type'=>$videoType]],
                        //['match' => ['name'=>$dataValue]]
                    ];
                    $should = [];
                    foreach ($keywords as $keyword){
                        $should[] = ['match'=>['name'=>$keyword]];
                    }
                    $es = $this->esClient();
                    $searchParams = [
                        'index' => 'video_index',
                        'body' => [
                            'track_total_hits' => true,
                            'size' => 10000,
//                            '_source' => ['id','name'],
                            '_source' => false,
                            'query' => [
                                'bool'=>[
                                    'minimum_should_match'=>'60%',
                                    'should' =>$should,
                                    'must' => $must
                                ]
                            ],
                        ],
                    ];
                    $response = $es->search($searchParams);
                    if(isset($response['hits']) && isset($response['hits']['hits'])){
                        $searchGet = $response['hits']['hits'];
                        foreach ($searchGet as $item){
                            $videoIds[] = $item['_id'];
                        }
                        //dd($videoIds);
                        $model->contain_vids = implode(',',$videoIds);
                    }

                }
                break;
            case 3: //分类
                if($cid>0){
                    $videoIds = DB::table('video')->where('status',1)->where('cid',$cid)->pluck('id')->all();
                    //dd($videoIds);
                    $model->data_value = $this->cats[$cid]['name'];
                    $model->contain_vids = implode(',',$videoIds);
                }
                break;
            case 4: //最新上架
                $model->data_value = '最新';
                $videoIds = DB::table('video')->where('dev_type',$videoType)->where('status',1)->orderByDesc('updated_at')->take(64)->pluck('id')->all();
                $model->contain_vids = implode(',',$videoIds);
                break;
            case 5: //自定义
                $videoIds = explode(',',$dataValue);
                if(!empty($videoIds)){
                    $model->contain_vids = $dataValue;
                }
                break;

        }
        $model->video_num = count($videoIds);
    }

    /*protected function afterSaveSuccessEvent($model, $id = '')
    {
        switch ($model->data_type){
            case 1: //标签
                $tagIds = $this->rq->input('tags',[]);

                break;
            case 2: //关键字
                Video::search($model->data_value)->where('status',1)->get();
                break;
        }
        //在ES中创建/更新索引
        //$es = $this->esClient();
        return $model;
    }*/
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
        $item->video_type = $this->deviceType[$item->video_type]['name'];
        $item->data_type = $this->dataType[$item->data_type]['name'];
        $item->show_num = match ($item->show_num){
            0 => '-',
            default => $item->show_num,
        };
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