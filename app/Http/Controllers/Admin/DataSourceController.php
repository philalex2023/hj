<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessDataSource;
use App\Models\AdminVideo;
use App\Models\DataSource;
use App\Models\Topic;
use App\Models\Video;
use App\Services\UiService;
use App\TraitClass\CatTrait;
use App\TraitClass\CommTrait;
use App\TraitClass\EsTrait;
use App\TraitClass\TagTrait;
use Illuminate\Http\Request;
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
        //dd(request()->all());
        $request = request()->all();
        if($show!==''){
            $indexUrl = action([VideoController::class, 'getList'],['data_source_id'=>$show->id]);
            $dataConfig = [
                'index_url' => $indexUrl,//首页列表JSON地址
                'table_name' => 'video',
                'page_name' => '视频数据',
                'edit_field_url' => action([VideoController::class, 'editTable']),//表格编辑提交地址
                'open_height' => $this->layuiOpenHeight(),//Layui 弹窗弹出高度
                'open_width' => $this->layuiOpenWidth(),//Layui 弹窗高度窗口
            ];
        }

        $cols = [
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
                'field' => 'sort',
                'minWidth' => 80,
                'title' => '排序',
                'edit' => 1,
                'sort' => 1,
                'align' => 'center',
            ],
        ];

        if(isset($request['getVideo'])){
            $this->pageName = '视频数据';
            $data = [
                [
                    'field' => '_method',
                    'type' => 'hidden',
                    'name' => '_method',
                    'default' => 'PUT',
                ],
                [
                    'field' => 'video_list',
                    'type' => 'childVideo',
                    'name' => '数据源名称',
                    'must' => 1,
                    'data' => $this->deviceType,
                    'list_config' => $dataConfig ?? [],
                    'cols' => $cols,
                    'default' => '',
                ],
            ];
        }else{
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
                    'field' => 'show_num',
                    'type' => 'text',
                    'name' => '首页展示数量',
                    'value' => ($show && ($show->show_num>0)) ? $show->show_num : '',
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
        }

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
                            !$jsonArr && $jsonArr = [];
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
                    Log::info('ES_keywords',$keywords);
                    $must = [
                        //'term' => ['status'=>1],
                        //'term' => ['dev_type'=>$videoType],
                        //['match' => ['name'=>$dataValue]]
                    ];
                    $should = [];
                    foreach ($keywords as $keyword){
                        $should[] = ['match_phrase'=>['name'=>$keyword]];
                    }
                    $must['bool'] = ['should'=>$should];
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
                                    'must' => $must
                                ]
                            ],
                        ],
                    ];

                    //Log::info('ES_keyword_params',[json_encode($searchParams)]);
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
                /*if($cid>0){
                    $videoIds = DB::table('video')->where('status',1)->where('cid',$cid)->pluck('id')->all();
                    //dd($videoIds);
                    $model->data_value = $this->cats[$cid]['name'];
                    $model->contain_vids = implode(',',$videoIds);
                }*/
                $model->cid = $cid;
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

    protected function afterSaveSuccessEvent($model, $id = '')
    {
        $job = new ProcessDataSource($model);
        $this->dispatch($job->onQueue('default'));
        return $model;
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

    public function updatePost(Request $request, $id)
    {
        $model = DataSource::query()->where('id',$id)->first();
        $job = new ProcessDataSource($model);
        $this->dispatch($job->onQueue('default'));
        return $this->returnSuccessApi();
    }

    //编辑链接赋值检查权限
    public function editUrlShow($item)
    {
        $item['edit_url'] = '';
        $item['edit_post_url'] = '';
        $edit_true=0;


        if (acan($this->getRouteInfo('controller_route_name') . 'edit' )) {
            $edit_true = 1;
        }
        if ($edit_true) {
            $item['edit_url'] = action($this->route['controller_name'] . '@edit', ['id' => $item->id]);
            $item['edit_post_url'] = action($this->route['controller_name'] . '@update', ['id' => $item->id]);
            $item['edit_video_list_url'] = action($this->route['controller_name'] . '@edit', ['id' => $item->id,'getVideo'=>1]);
            $item['edit_video_list_post_url'] = action($this->route['controller_name'] . '@updatePost', ['id' => $item->id,'getVideo'=>1]);
        }
        return $item;

    }

    public function setListOutputItemExtend($item)
    {
        $item->video_type = $this->deviceType[$item->video_type]['name'];
        $item->data_type = $this->dataType[$item->data_type]['name'];
        $item->show_num = match ($item->show_num){
            0 => '-',
            default => $item->show_num,
        };
        //$url = action([VideoController::class, 'getList'],request()->all()+['did'=>$item->id]);
        $item->video_num = match ($item->video_num){
            0 => '-',
            default => '<a class="event-link" data-title="视频数据" lay-event="editVideoList" data-w="75%" data-h="75%" href="javascript:void(0)" >' . $item->video_num . '</a> ',
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