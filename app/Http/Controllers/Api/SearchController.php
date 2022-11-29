<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Jobs\UpdateKeyWords;
use App\Models\Category;
use App\Models\KeyWords;
use App\Models\Tag;
use App\Models\Video;
use App\TraitClass\AdTrait;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\DataSourceTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\TopicTrait;
use App\TraitClass\VideoTrait;
use Elasticsearch\ClientBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    use VideoTrait,PHPRedisTrait,AdTrait,ApiParamsTrait,DataSourceTrait,TopicTrait;

    /**
     * 搜索功能
     * @param Request $request
     */
    public function index(Request $request): JsonResponse
    {
        if (isset($request->params)) {
            $params = self::parse($request->params);
            $validated = Validator::make($params, [
                'words' => 'nullable',
                'page' => 'required|integer',
                "tag" => 'array', // 标签
                "type" => 'nullable', // 类型
                "sort" => 'nullable', // 排序
                "project" => 'nullable', // 项目
            ])->validate();
            $perPage = 16;
            $page = $validated['page'];
            $offset = ($page-1)*$perPage;

            $words = $validated['words']??false;
            if(empty($words)){
                return response()->json([
                    'state' => -1,
                    'msg' => '请输入关键词',
                    'data' => ['list'=>[]]
                ]);
            }
            $words = substr($words,0,40);
            $project = intval($validated['project'] ?? 1);
            $project = $project>0 ? $project : 1;

            $es = $this->esClient();
            $body = [
//                        'track_total_hits' => true,
                'size' => 500,
                '_source' => $this->videoFields,
                'query' => [
                    'match_phrase'=>[
                        'name' => ['query' => $words?:"*",'slop'=>50]
                    ]
                ],
                'sort' => [
                    ['id' => 'desc']
                ]
            ];

            $searchParams = [
                'index' => 'video_index',
                'body' => $body,
            ];

            //Log::info('ES_keyword_params',[json_encode($searchParams)]);
            $response = $es->search($searchParams);
            $videoList = [];
            if(isset($response['hits']) && isset($response['hits']['hits'])){
                $total = $response['hits']['total']['value'];
                foreach ($response['hits']['hits'] as $item) {
                    $videoList[] = $item['_source'];
                }
                unset($response);
            }

            if(!empty($videoList)){
                $res['total'] = $total ?? 0;
                $pageLists = array_slice($videoList,$offset,$perPage);
                $hasMorePages = count($videoList) > $perPage*$page;
                unset($videoList);
                $res['list'] = $this->handleVideoItems($pageLists,false,$request->user()->id);
                $res['hasMorePages'] = $hasMorePages;
            }else{
                $res['list'] = [];
                $res['hasMorePages'] = false;
            }

            if ($words && $words!='') {
                $this->incrTagWeightsByWords($project,$words);
            }

            return response()->json([
                'state' => 0,
                'data' => $res
            ]);
        }
        return response()->json([]);
        /*try {
            
        } catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }*/

    }

    public function incrTagWeightsByTag($project,$id)
    {
        $key = 'projectTag_'.$project;
        $tagKey = 'tagIdName';
        $redis = $this->redis();
        $hasProjectTag = $redis->exists($key);
        $hasTagNames = $redis->exists($tagKey);
        $cacheName = $redis->hGet($tagKey,$id);
        Redis::pipeline(function($pipe) use ($hasProjectTag,$hasTagNames,$tagKey,$key,$id,$cacheName) {
            if($hasProjectTag){
                if(!$hasTagNames){
                    $nameIdArr = array_column(Tag::query()->get(['id','name'])->all(),'name','id');
                    $pipe->hMset($tagKey,$nameIdArr);
                    $pipe->expire($tagKey,14400);
                    $name = $nameIdArr[$id] ?? 0;
                }else{
                    $name = $cacheName;
                }
                $id && $pipe->zIncrBy($key,1,json_encode(['id'=>(int)$id,'name'=>$name],JSON_UNESCAPED_UNICODE));
            }
        });
    }

    public function incrTagWeightsByWords($project,$words,$isTag=false)
    {
        //增加标签权重
        $key = 'projectTag_'.$project;
        $tagKey = 'tag_names';
        $redis = $this->redis();
        $hasProjectTag = $redis->exists($key);
        $hasTagNames = $redis->exists($tagKey);
        $cacheId = $redis->hGet($tagKey,$words);
        Redis::pipeline(function($pipe) use ($hasProjectTag,$hasTagNames,$tagKey,$key,$words,$cacheId) {
            if($hasProjectTag){
                if(!$hasTagNames){
                    $nameIdArr = array_column(Tag::query()->get(['id','name'])->all(),'id','name');
                    $pipe->hMset($tagKey,$nameIdArr);
                    $pipe->expire($tagKey,14400);
                    $id = $nameIdArr[$words] ?? 0;
                }else{
                    $id = $cacheId;
                }
                $id && $pipe->zIncrBy($key,1,json_encode(['id'=>(int)$id,'name'=>$words],JSON_UNESCAPED_UNICODE));
            }
        });
    }

    //标签
    public function tag(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $params = self::parse($request->params);
            $perPage = 16;
            $page = $params['page'] ?? 1;
            $offset = ($page-1)*$perPage;
            //Log::info('SearchTagParams:',[$params]);
            if (isset($params['pageSize']) && ($params['pageSize'] < $perPage)) {
                $perPage = $params['pageSize'];
            }

            $id = $params['id'] ?? 0;
            //$words = '*';
            $project = intval($params['project'] ?? 1);
            //
            $project = $project>0 ? $project : 1;

            $tagName = DB::table('tag')->where('id',$id)->value('name');
            if(!$tagName){
                Log::info('SearchTagParams:',[$params,$tagName]);
                return response()->json([
                    'state'=>-1,
                    'msg'=>'此标签不存在或被删除',
                    'data'=>['list'=>[], 'hasMorePages'=>false]
                ]);
            }
            $searchParams = [
                'index' => 'video_index',
                'body' => [
//                    'track_total_hits' => true,
                    'size' => $perPage,
                    'from' => $offset,
                    //'_source' => false,
                    'query' => [
                        'bool'=>[
                            "should" => [
                                [ "match"=>["name"=>$tagName]],
                                [ "match"=> ["tag_kv"=>$tagName]],
                            ]
                        ]
                    ],
                ],
            ];
            $es = $this->esClient();
            $response = $es->search($searchParams);
            $videoList = [];
            $total = 0;
            if(isset($response['hits']) && isset($response['hits']['hits'])){
                $total = $response['hits']['total']['value'];
                foreach ($response['hits']['hits'] as $item) {
                    $videoList[] = $item['_source'];
                }
                unset($response);
            }
            $res['total'] = $total;
            $hasMorePages = $total >= $perPage*$page;


            if(!empty($videoList)){
                $res['list'] = $this->handleVideoItems($videoList,false,$request->user()->id);
                //广告
                $res['list'] = $this->insertAds($res['list'],'tag_page',true, $page, $perPage);
                $res['hasMorePages'] = $hasMorePages;
            }
            if(isset($res['list']) && !empty($res['list'])){
                $domain = env('RESOURCE_DOMAIN2');
                foreach ($res['list'] as &$d){
                    if(!empty($d['ad_list'])){
                        $this->frontFilterAd($d['ad_list'],$domain);
                    }else{
                        $d['ad_list'] = [];
                    }
                }
            }

            $this->incrTagWeightsByTag($project,$id);

            return response()->json([
                'state'=>0,
                'data'=>$res??[]
            ]);

        }
        return response()->json([]);
    }

    //更多

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function cat(Request $request): JsonResponse
    {
        try {
            if(isset($request->params)){
                $params = self::parse($request->params);
                $validated = Validator::make($params,[
                    'cid' => 'required',
                    'page' => 'required|integer',
                ])->validated();
                $user = $request->user();
                $tid = $validated['cid'];
                $page = $validated['page'];
                $perPage = 16;
                $offset = ($page-1)*$perPage;

//                $containVidStr = DB::table('topic')->where('id',$tid)->value('contain_vids');

                if(!$tid){
                    return response()->json(['state'=>0, 'data'=>['list'=>[], 'hasMorePages'=>false]]);
                }
                $containVidStr = $this->getTopicVideoIdsById($tid);
                if(!$containVidStr){
                    Log::info('SearchNoCat',[$tid]);
                    return response()->json(['state'=>0, 'data'=>['list'=>[], 'hasMorePages'=>false]]);
                }
                $ids = explode(',',$containVidStr);
                $idParams = [];
                $length = count($ids);
                foreach ($ids as $key => $id) {
                    $idParams[] = ['id' => (int)$id, 'score' => $length - $key];
                }

                $searchParams = [
                    'index' => 'video_index',
                    'body' => [
//                        'track_total_hits' => true,
                        'size' => 500,
                        '_source' => $this->videoFields,
                        'query' => [
                            'function_score' => [
                                'query' => [
                                    'bool'=>[
                                        'must' => [
                                            ['terms' => ['id'=>$ids]],
                                        ]
                                    ]
                                ],
                                'script_score' => [
                                    'script' => [
                                        //'lang' => 'painless',
                                        'params' => [
                                            'scoring' => $idParams
                                        ],
                                        'source' => "for(i in params.scoring) { if(doc['id'].value == i.id ) return i.score; } return 0;"
                                    ]
                                ]
                            ]
                        ]
                    ],
                ];
                $es = $this->esClient();
                $response = $es->search($searchParams);
                $catVideoList = [];
                $total = 0;
                if(isset($response['hits']) && isset($response['hits']['hits'])){
                    $total = $response['hits']['total']['value'];
                    foreach ($response['hits']['hits'] as $item) {
                        $catVideoList[] = $item['_source'];
                    }
                    unset($response);
                }
                if(!empty($catVideoList)){
                    $res['total'] = $total;
                    $pageLists = array_slice($catVideoList,$offset,$perPage);
                    $hasMorePages = count($catVideoList) > $perPage*$page;
                    unset($catVideoList);
                    $res['list'] = $this->handleVideoItems($pageLists,false,$user->id);
                    unset($pageLists);
                    //广告
                    $res['list'] = $this->insertAds($res['list'],'more_page',true, $page, $perPage);
                    //Log::info('==CatList==',$res['list']);
                    $res['hasMorePages'] = $hasMorePages;
                }else{
                    $res['list'] = [];
                    $res['hasMorePages'] = false;
                }

                if(isset($res['list']) && !empty($res['list'])){
                    $domain = env('RESOURCE_DOMAIN2');
                    foreach ($res['list'] as &$d){
                        if(!empty($d['ad_list'])){
                            $this->frontFilterAd($d['ad_list'],$domain);
                        }else{
                            $d['ad_list'] = [];
                        }
                    }
                }
                return response()->json(['state'=>0, 'data'=>$res??[]]);
            }
        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }
        return response()->json([]);
    }

    //推荐

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function recommend(Request $request): JsonResponse
    {
        try {
            if(isset($request->params)){
                $params = self::parse($request->params);
                $validated = Validator::make($params,[
                    'vid' => 'required|integer',
                ])->validated();
                $page = $validated['page'] ?? 1;
                $vid = $validated['vid'];
                $cid = $this->getVideoById($vid)->cid;
                $res = ['list'=>[], 'hasMorePages'=>false];
                $perPage = 8;
                if($cid > 0){
                    $searchParams = [
                        'index' => 'video_index',
                        'body' => [
                            'track_total_hits' => true,
                            'size' => $perPage,
                            //'_source' => false,
                            'query' => [
                                'bool'=>[
                                    'must' => [
//                                        ['terms' => ['id'=>$ids]],
//                                    ['term' => ['status'=>1]],
                                        ['term' => ['cid'=>$cid]],
                                    ]
                                ]
                            ],
                            'sort' => [
                                '_script'=>[
                                    'script' => 'Math.random()',
                                    'type' => 'number',
                                    'order' => 'asc',
                                ]
                            ]
                        ],
                    ];
                    $es = $this->esClient();
                    $response = $es->search($searchParams);
                    $catVideoList = [];
                    $total = 0;
                    if(isset($response['hits']) && isset($response['hits']['hits'])){
                        $total = $response['hits']['total']['value'];
                        foreach ($response['hits']['hits'] as $item) {
                            $catVideoList[] = $item['_source'];
                        }
                    }
                    $res['total'] = $total;
                    $hasMorePages = $total >= $perPage*$page;

                    if(!empty($catVideoList)){
                        $res['list'] = $this->handleVideoItems($catVideoList,false,$request->user()->id);
                        //广告
                        $res['list'] = $this->insertAds($res['list'],'recommend',true, $page, $perPage);
                        $res['hasMorePages'] = $hasMorePages;
                    }
                    if(!empty($res['list'])){
                        $domain = env('RESOURCE_DOMAIN2');
                        foreach ($res['list'] as &$d){
                            if(!empty($d['ad_list'])){
                                $this->frontFilterAd($d['ad_list'],$domain);
                            }
                        }
                    }
                }
                return response()->json(['state'=>0, 'data'=>$res]);
            }
            return response()->json(['state' => -1, 'msg' => "参数错误"]);
        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }

    }

    public function hotWords(): JsonResponse
    {
        $words = KeyWords::query()
            ->orderByDesc('hits')
            ->limit(8)
            ->pluck('words');
        return response()->json([
            'state'=>0,
            'data'=>$words
        ]);
    }

    public function hotTags(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $params = self::parse($request->params);
            $project = intval($params['project'] ?? 1);
        }

        $project = $project ?? 0;
        $key = 'projectTag_'.$project;
        $freshKey = 'freshTag_'.$project;
        $redis = $this->redis();
        $tagFromRedis = (array) $redis->zRevRangeByScore($key,0,-1);
        $tags = [];
        if(!$redis->get($freshKey) || empty($tagFromRedis)){
            $videoAll = DB::table('video')->where('status',1)->where('dev_type',$project)->get(['tag_kv']);
            $tagAll = DB::table('tag')->pluck('name','id')->all();
            $videoTag = [];
            foreach ($videoAll as $item){
                $tagKvJson = json_decode($item->tag_kv,true);
                $tagKv = $tagKvJson ?? [];
                $intersection = array_intersect($tagAll,$tagKv);
                if(!empty($intersection)){
                    $videoTag = $videoTag + $intersection;
                }
            }

            Redis::pipeline(function ($pipe) use ($videoTag,$key,&$tags,$freshKey) {
                foreach ($videoTag as $k => $t){
                    $item = ['id'=>(int)$k,'name'=>$t];
                    $tags[] = $item;
                    $pipe->zAdd($key,1,json_encode($item,JSON_UNESCAPED_UNICODE));
                }
                $pipe->set($freshKey,1);
                $pipe->expire($freshKey,14400);
            });

        }else{
            $tagFromRedisKeys = array_keys($tagFromRedis);
            foreach ($tagFromRedisKeys as $r){
                $tags[] = json_decode($r,true);
            }
        }
        //$tags = array_slice($tags,0,5);
        return response()->json([
            'state'=>0,
            'data'=>$tags
        ]);
    }

    /**
     * 得到排序标识
     * @param string $sort
     * @return string
     */
    private function getOrderColumn(string $sort): string
    {
        return match ($sort) {
            '0' => 'views',
            '1' => 'id',
            '2' => 'collects',
            '3' => 'likes',
            default => '',
        };
    }

    /**
     * 得到搜索选项
     */
    public function getOption()
    {
        $data = Category::with('childs:id,name,parent_id')
            ->where('parent_id','2')
            ->where('is_checked',1)
            ->select('id','name','parent_id')
            ->orderBy('sort')
            ->get();
        return response()->json([
            'state'=>0,
            'data'=>$data
        ]);
    }

}
