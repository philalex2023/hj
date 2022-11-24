<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

Trait DataSourceTrait
{
    use EsTrait,TagTrait;
    public function getDataSourceIdsForVideo(&$model,$args=null)
    {
        if($args === null){
            $tagIds = $model->tag ? (json_decode($model->tag,true)??[]) : [];
            $dataType = $model->data_type;
            $videoType = $model->video_type;
            $cid = $model->cid;
            $dataValue = $model->data_value;
        }else{
            $tagIds = $args['tagIds'];
            $dataType = $args['dataType'];
            $videoType = $args['videoType'];
            $cid = $args['cid'];
            $dataValue = $args['dataValue'];
        }

        $videoIds = [];
        switch ($dataType){
            case 1: //标签
                if(!empty($tagIds)){
                    $tagName = [];
                    $tags = $this->getTagData();
                    foreach ($tagIds as $v){
                        $tagName[] = $tags[$v]['name'];
                    }
                    $model->data_value = implode(',',$tagName);
                    $model->tag = json_encode($tagIds);
                    //Log::info('testTag_dev_type',[$videoType]);
                    DB::table('video')
                        ->where('dev_type',$videoType)
                        ->where('status',1)
                        ->orderByDesc('created_at')
                        ->chunk(100,function ($items) use ($tagIds,&$videoIds,$model){
                            foreach ($items as $item){
                                $jsonArr = json_decode($item->tag,true);
                                !$jsonArr && $jsonArr = [];
                                /*if($item->id==30551){
                                    Log::info('testTag_30551_',[$jsonArr,$tagIds]);
                                }*/
                                $intersect = array_intersect($jsonArr,$tagIds); //交集
                                if(!empty($intersect)){
                                    $videoIds[] = $item->id;
                                }
                            }
                        });
                }

                break;
            case 2: //关键字
                if(!empty($dataValue)){
                    $keywords = explode(',',$dataValue);
                    Log::info('ES_keywords',$keywords);
                    $must = [];
                    $should = [];
                    foreach ($keywords as $keyword){
                        $should[] = ['match_phrase'=>['name'=>$keyword]];
                    }
                    $must['bool'] = ['should'=>$should];
                    $es = $this->esClient();
                    $searchParams = [
                        'index' => 'video_index',
                        'body' => [
//                            'track_total_hits' => true,
                            'size' => 10000,
//                            '_source' => ['id','name'],
                            '_source' => false,
                            'query' => [
                                'bool'=>[
                                    'must' => $must
                                ]
                            ],
                            'sort' => [
                                'id'=>[
                                    'order' => 'desc',
                                ]
                            ]
                        ],
                    ];

                    //Log::info('ES_keyword_params',[json_encode($searchParams)]);
                    $response = $es->search($searchParams);
                    if(isset($response['hits']) && isset($response['hits']['hits'])){
                        $searchGet = $response['hits']['hits'];
                        foreach ($searchGet as $item){
                            $videoIds[] = $item['_id'];
                        }
                        //排序
                        $videoIds = DB::table('video')->whereIn('id',$videoIds)->orderByDesc('created_at')->pluck('id')->all();
                        //dd($videoIds);
                    }

                }
                break;
            case 3: //分类
                $model->cid = $cid;
                break;
            case 4: //最新上架
                $model->data_value = '最新';
                $videoIds = DB::table('video')->where('dev_type',$videoType)->where('status',1)->orderByDesc('created_at')->take(64)->pluck('id')->all();
                break;
            case 5: //自定义
                $videoIds = explode(',',$dataValue);
                break;

        }

        //去重
        !$videoIds && $videoIds=[];
        $videoIds = array_unique($videoIds);
        $model->contain_vids = implode(',',$videoIds);
        $model->video_num = count($videoIds);
        return $model;
    }
}