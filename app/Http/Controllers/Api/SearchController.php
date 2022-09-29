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
use App\TraitClass\EsTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Elasticsearch\ClientBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    use VideoTrait,PHPRedisTrait,AdTrait,ApiParamsTrait,EsTrait;

    /**
     * 搜索功能
     * @param Request $request
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (isset($request->params)) {
                $params = self::parse($request->params);
                $validated = Validator::make($params, [
                    'words' => 'nullable',
                    'page' => 'required|integer',
                    "cid" => 'array',// 分类
                    "bid" => 'array',// 版块
                    "tag" => 'array', // 标签
                    "type" => 'nullable', // 类型
                    "sort" => 'nullable', // 排序
                    "project" => 'nullable', // 项目
                ])->validate();
                $perPage = 16;
                $cats = $params['cid']??[];
                $bids = $params['bid']??[];
                if(isset($bids[0]) && $bids[0]=='-1'){
                    $bids = [];
                }

                $page = $validated['page'];
                $order = $this->getOrderColumn(isset($validated['sort']) ? (string)$validated['sort'] : -1);
                $type = $validated['type']??-1;
                $words = $validated['words']??false;
                $project = intval($validated['project'] ?? 1);
                $project = $project>0 ? $project : 1;
//                $model = Video::search($words?:"*")->where('status', 1)->where('type',$project);
                $model = Video::search($words?:"*")->where('status', 1)->where('type',$project);
                // 分类
                if (!empty($cats) || !empty($bids)) {
                    $cats = !empty($bids) ? $bids : $cats;
                    $catsWords = [];
                    if(isset($cats[0])){
                        $redis = $this->redis();
                        $catsKey = 'searchCats_'.$cats[0];
                        $catsWords = $redis->sMembers($catsKey);
                        if(!$catsWords){
                            $catsWords = DB::table('categories')->where('parent_id',$cats[0])->pluck('id')->all();
                            $redis->sAddArray($catsKey,$catsWords);
                            $redis->expire($catsKey,24*3600);
                        }
                    }
                    if(!empty($bids)){
                        $catsWords = $bids;
                    }
                    $catsWords = @implode(' ',$catsWords);
//                    Log::info('TestSearchCat2',[$catsWords]);
                    $model = Video::search($catsWords)->where('status', 1);
                }
                // 类别
                if ($type != -1) {
                    $model->where('restricted',$type);
                }
                // 排序
                if ($order) {
                    $model->orderBy($order,'desc');
                }
                // 标签 预留
                $paginator =$model->simplePaginate($perPage, 'searchPage', $page);
                $paginatorArr = $paginator->toArray()['data'];

                //$client = ClientBuilder::create()->build();
                $res['list'] = $this->handleVideoItems($paginatorArr,false,$request->user()->id);

                $res['hasMorePages'] = $paginator->hasMorePages();
                if ($words && $words!='') {
//                    UpdateKeyWords::dispatchAfterResponse($validated['words']);
                    //增加标签权重
                    $key = 'projectTag_'.$project;
                    $redis = $this->redis();
                    if($redis->exists($key)){
                        $id = DB::table('tag')->where('name',$words)->value('id');
                        $id && $redis->zIncrBy($key,1,json_encode(['id'=>(int)$id,'name'=>$words],JSON_UNESCAPED_UNICODE));
                    }
                }

                return response()->json([
                    'state' => 0,
                    'data' => $res
                ]);
            }
            return response()->json([]);
        } catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }

    }

    //标签
    public function tag(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $perPage = 16;
            $params = self::parse($request->params);
            if (isset($params['pageSize']) && ($params['pageSize'] < $perPage)) {
                $perPage = $params['pageSize'];
            }
            $page = $params['page'] ?? 1;
            $id = $params['id'] ?? 0;
            //$words = '*';
            $project = intval($params['project'] ?? 1);
            //
            /*$key = 'projectTag_'.$project;
            $redis = $this->redis();
            $tagFromRedis = (array) $redis->zRevRange($key,0,-1,true);
            if(!empty($tagFromRedis)){
                $tagFromRedisKeys = array_keys($tagFromRedis);
                foreach ($tagFromRedisKeys as $r){
                    $tagItem = json_decode($r,true);
                    if($tagItem['id'] == $id){
                        $words = $tagItem['name'];
                        break;
                    }
                }

            }*/

            $project = $project>0 ? $project : 1;
            $paginator = Video::search((string)$id)->where('status',1)->where('type',$project)->simplePaginate($perPage,'searchTag',$page);
            $paginatorArr = $paginator->toArray()['data'];
            if(!empty($paginatorArr)){
                $res['list'] = $this->handleVideoItems($paginatorArr,false,$request->user()->id);
                //广告
                $res['list'] = $this->insertAds($res['list'],'tag_page',true, $page, $perPage);
                $res['hasMorePages'] = $paginator->hasMorePages();
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
            //增加标签权重
            $key = 'projectTag_'.$project;
            $redis = $this->redis();
            if($id>0 && $redis->exists($key)){
                $tagName = DB::table('tag')->where('id',$id)->value('name');
                $tagName && $redis->zIncrBy($key,1,json_encode(['id'=>(int)$id,'name'=>$tagName],JSON_UNESCAPED_UNICODE));
            }
//            DB::table('tag')->where('id',$id)->increment('hits');
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
                $cid = $validated['cid'];
                $page = $validated['page'];
                $perPage = 16;
                $offset = ($page-1)*$perPage;

                $ids = explode(',',DB::table('topic')->where('id',$cid)->value('contain_vids'));
                $searchParams = [
                    'index' => 'video_index',
                    'body' => [
                        'size' => $perPage,
                        'from' => $offset,
                        //'_source' => false,
                        'query' => [
                            'bool'=>[
                                'must' => [
                                    'terms' => ['id'=>$ids],
                                ]
                            ]
                        ],
                    ],
                ];
                $es = $this->esClient();
                $response = $es->search($searchParams);
                $catVideoList = [];
                $total = 0;
                if(isset($response['hits']) && isset($response['hits']['hits'])){
                    $total = $response['hits']['total'];
                    foreach ($response['hits']['hits'] as $item) {
                        $catVideoList[] = $item['_source'];
                    }
                }
                $res['total'] = $total;
                $hasMorePages = $total >= $perPage*$page;
                /*$catVideoList = Video::search((string)$cid)->where('status',1)->simplePaginate(10000,'catVideo',1)->toArray()['data'];
                foreach ($catVideoList as &$item){
                    $item['updated_time'] = strtotime($item['updated_at']);
                }
                $updatedAt = array_column($catVideoList,'updated_time');
                array_multisort($updatedAt,SORT_DESC,$catVideoList);*/




//                $pageLists = array_slice($catVideoList,$offset,$perPage);
//                $hasMorePages = count($catVideoList) > $perPage*$page;

                if(!empty($catVideoList)){
                    $res['list'] = $this->handleVideoItems($catVideoList,false,$user->id);
//                    $res['list'] = $this->handleVideoItems($pageLists,false,$user->id);
                    //广告
                    $res['list'] = $this->insertAds($res['list'],'more_page',true, $page, $perPage);
                    //Log::info('==CatList==',$res['list']);
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
                $perPage = 9;
                $vid = $validated['vid'];
                $cat = $this->getVideoById($vid)->cat;
                $res = ['list'=>[], 'hasMorePages'=>false];

                if(!empty($cat)){
                    $keyWordsArr = (array)@json_decode($cat,true);
                    $keyWords = implode(' ',$keyWordsArr);
                    $paginator = Video::search($keyWords)->where('status',1)->simplePaginate($perPage,'searchCat',$page);
                    $paginatorArr = $paginator->toArray()['data'];
                    foreach ($paginatorArr as $key=>$value){
                        if($value['id']==$vid){
                            unset($paginatorArr[$key]);
                        }
                    }
                    $paginatorArr = array_slice($paginatorArr,0,8);
                    if(!empty($paginatorArr)){
                        $res['list'] = $this->handleVideoItems($paginatorArr,false,$request->user()->id);
                        //广告
                        $res['list'] = $this->insertAds($res['list'],'recommend',true, $page, $perPage);
                        $res['hasMorePages'] = false;
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
        $project = 1;
        if(isset($request->params)){
            $params = self::parse($request->params);
            $project = intval($params['project'] ?? 1);
        }
        $project = $project>0 ? $project : 1;
        $key = 'projectTag_'.$project;
        $freshKey = 'freshTag_'.$project;
        $redis = $this->redis();
        $tagFromRedis = (array) $redis->zRevRange($key,0,-1,true);
        $tags = [];
        if(!empty($tagFromRedis)){
            $tagFromRedisKeys = array_keys($tagFromRedis);
            foreach ($tagFromRedisKeys as $r){
                $tags[] = json_decode($r,true);
            }
        }
        if($redis->get($freshKey)==1){
            $videoAll = DB::table('video')->where('status',1)->where('type',$project)->get(['tag_kv']);
            $videoTag = [];
            foreach ($videoAll as $item){
                $videoTag = $videoTag + (array)json_decode($item->tag_kv,true);
            }
            $tagNamesFromDb = $videoTag;
            if(!empty($tags)){
                $tagNamesFromRedis = array_column($tags,'name','id');
                foreach ($tagNamesFromDb as $id => $name){
                    if(!isset($tagNamesFromRedis[$id])){
                        $redis->zAdd($key,1,json_encode(['id'=>(int)$id,'name'=>$name],JSON_UNESCAPED_UNICODE));
                    }
                }
                foreach ($tagNamesFromRedis as $idKey => $nameVal){
                    if(!isset($tagNamesFromDb[$idKey])){
                        $redis->zRem($key,json_encode(['id'=>(int)$idKey,'name'=>$nameVal],JSON_UNESCAPED_UNICODE));
                    }
                }
            }else{
                foreach ($videoTag as $k => $t){
                    $redis->zAdd($key,1,json_encode(['id'=>(int)$k,'name'=>$t],JSON_UNESCAPED_UNICODE));
                }
            }

            $redis->del($freshKey);
            !$redis->exists($key) && $tags = $videoTag;
        }
        /*$tags = Cache::remember('hotTags',3600*24,function (){
            return Tag::query()
                ->orderBy('hits','desc')
                ->limit(15)
                ->get(['id','name']);
        });
        foreach ($tags->toArray() as &$item){
            $item = (array)$item;
        }*/
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
