<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\AdminVideoShort;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Video;
use App\Models\VideoShort;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CommTrait;
use App\TraitClass\EsTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\StatisticTrait;
use App\TraitClass\VideoShortTrait;
use App\TraitClass\VideoTrait;
use App\TraitClass\VipRights;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VideoShortController extends Controller
{
    use VideoTrait,PHPRedisTrait,VipRights,StatisticTrait,MemberCardTrait,ApiParamsTrait,CommTrait,EsTrait;

    private array $mainCateAlias = [
        'short_hot',
        'limit_free',
        'short_rec'
    ];

    /*private array $cateMapAlias = [
        '-1' => 'sub_cat_1',
        '-2' => 'sub_cat_2',
        '-3' => 'sub_cat_3',
        '-4' => 'sub_cat_4',
        '-5' => 'sub_cat_5',
        '-6' => 'sub_cat_6',
        '-7' => 'sub_cat_7',
        '-8' => 'sub_cat_8',
    ];*/
    private array $cateMapAlias = [
        '-1' => 175,
        '-2' => 174,
        '-3' => 173,
        '-4' => 172,
        '-5' => 171,
        '-6' => 170,
        '-7' => 169,
        '-8' => 168,
    ];

    /**
     * 短视频分类
     * @param Request $request
     * @return JsonResponse
     */
    public function cate(Request $request): JsonResponse
    {
        $cacheData = $this->redis()->get('short_category');
        if(!$cacheData){
            $data = $this->resetShortCate();
        }else{
            $data = json_decode($cacheData,true);
        }
        return response()->json([
            'state' => 0,
            'data' => $data
        ]);
    }

    public function getShortVideoIds($cate_id=0): JsonResponse|array
    {
        $redis = $this->redis();
        $key = $cate_id>0 ? 'shortVideoIdsCollections_'.$cate_id : 'shortVideoIdsCollections';
        if(!$redis->exists($key)){
            $lock = Cache::lock('getShortVideoIdsLock_'.$cate_id,10);
            if($lock->get()){
                $buildQuery = AdminVideoShort::query();
                if($cate_id>0){
                    $buildQuery = $buildQuery->where('cat','like','%'.$cate_id.'%');
                }
                $items = $buildQuery->orderByDesc('sort')->get(['id','sort','status']);
                $invalidIds = [];
                foreach ($items as $item){
                    if($item->status==1){
                        $redis->zAdd($key,$item->sort,$item->id);
                    }
                    if($item->status==0){
                        $invalidIds[] = $item->id;
                    }
                }

                $redis->sRem($key,$invalidIds);
                $redis->expire($key,86400);
                $ids = (array) $redis->zRevRange($key,0,-1,true);
                $lock->release();
            }else{
                return $this->returnExceptionContentForLock('无法获取锁');
            }
        }else{
            $ids = (array) $redis->zRevRange($key,0,-1,true);
        }
        return $ids;
    }

    private function isBuyShortVideo($one,$user): bool
    {
        $videoRedis = $this->redis('video');
        $buyShortKey = 'buyShortKey_' . $user->id;
        return $videoRedis->sIsMember($buyShortKey,$one['id']);
    }

    public function buyShortWithGold(Request $request): JsonResponse
    {
        if(!isset($request->params)){
            return response()->json([]);
        }
//        Log::info('testParams==',[$request->params]);
        $validated = Validator::make(self::parse($request->params), [
            'id' => 'integer',
        ])->validated();
        $user = $request->user();
        $videoRedis = $this->redis('video');
        $buyShortKey = 'buyShortKey_' . $user->id;
        if($videoRedis->sIsMember($buyShortKey,$validated['id'])){
            return response()->json(['state' => -3, 'data' =>['status'=>-3],'msg'=>'已购买过该商品']);
        }
        $short = DB::table('video_short')->where('id',$validated['id'])->first();
        if(!$short){
            return response()->json(['state' => -2, 'data' =>['status'=>-2],'msg'=>'记录不存在']);
        }

        $userGold = $user->gold;
        if($userGold < $short->gold){
            return response()->json(['state' => -1, 'data' =>['status'=>-1],'msg'=>'余额不足请充值']);
        }else{
            DB::table('users')->where('id', '=', $user->id)
                ->where('gold', '>=', $short->gold)
                ->update(['gold' => $userGold - $short->gold]);
            $videoRedis->sAdd($buyShortKey,$validated['id']);
            $videoRedis->expire($buyShortKey,7*24*3600);
            Cache::forget('cachedUser.'.$user->id);
            //插入历史记录
            /*$view_history_key = 'viewShortHistory_'.$user->id;
            $videoRedis->zAdd($view_history_key,time(),$validated['id']);
            $videoRedis->expire($view_history_key,7*24*3600);*/
            //插入收藏
            $shortCollectsKey = 'shortCollects_'.$user->id;
            $videoRedis->zAdd($shortCollectsKey,time(),$validated['id']);
            $videoRedis->expire($shortCollectsKey,7*24*3600);
            DB::table('video_short')->where('id',$validated['id'])->increment('buyers');
        }
        return response()->json(['state' => 0, 'data' =>['status'=>0],'msg'=>'购买成功']);
    }

    /**
     * 读取数据
     * @param $page
     * @param $user
     * @param $startId
     * @param $cateId
     * @param $tagId
     * @param $words
     * @return array
     */
    private function items($page, $user, $startId,$cateId,$tagId,$words): array
    {
        $redis = $this->redis();
        $ShortVideoIds = $this->getShortVideoIds($cateId??0);
        $newShortVideoByUidKey = 'shortVideoForUser_'.$cateId.'_'.$user->id;
        if(!$redis->exists($newShortVideoByUidKey) || $page==1){
//            shuffle($ShortVideoIds);
            $shortSortIds = [];
            $shortRangeIds = [];
            foreach ($ShortVideoIds as $value=>$score){
                $score>0 ? $shortSortIds[]=$value : $shortRangeIds[]=$value;
                $redis->zAdd($newShortVideoByUidKey,$score,$value);
            }
            $redis->expire($newShortVideoByUidKey,3600);
            shuffle($shortRangeIds);
            $ShortVideoIds = [...$shortSortIds,...$shortRangeIds];
        }else{
            $ShortVideoIds = $redis->zRevRange($newShortVideoByUidKey,0,-1) ?? [];
        }
        $perPage = 8;
        $model = VideoShort::search("*")->where('status',1);

        if ($tagId) {
            $tagInfo = Tag::query()->where(['mask'=>$this->cateMapAlias[$tagId]])->firstOrFail()?->toArray();
            if(!empty($tagInfo)){
                $model = VideoShort::search('"'.$tagInfo['id'].'"')->where('status',1);
            }
        }else{
            if ($cateId) {
                $model = VideoShort::search('"'.$cateId.'"')->where('status',1);
            }
        }
        /*if ($startId) {
            $model = $model->where('id','<=',$startId)->orderBy('id','desc');
        }*/

        $items = [];
        if(!empty($words)){
            $model = VideoShort::search($words)->where('status', 1);
            $paginator =$model->simplePaginate($perPage, 'searchPage', $page);
            $items = $paginator->items();
            $more = $paginator->hasMorePages();
        }else {
            if (!empty($ShortVideoIds) && (!$tagId) && (!$startId)) {
                //$cacheIds = explode(',', $newIds);
                $start = $perPage * ($page - 1);
                $ids = array_slice($ShortVideoIds, $start, $perPage);
                foreach ($ids as $id) {
                    $mapNum = $id % 300;
                    $cacheKey = "short_video_$mapNum";
                    $raw = $this->redis()->hGet($cacheKey, $id);
                    if (!$raw) {
                        $model = DB::table('video_short')->where('id', $id)->first();
                        $items[] = $this->resetRedisVideoShort($model);
                    }else{
                        $items[] = json_decode($raw, true);
                    }
                }
                $more = false;
                if (count($ids) == $perPage) {
                    $more = true;
                }
            } else {
                $paginator = $model->simplePaginate($perPage, 'shortLists', $page);
                $items = $paginator->items();
                $more = $paginator->hasMorePages();
            }
        }

        $data = [];
        $_v = date('Ymd');
        if(!empty($items)){
//            $vipValue = $this->getVipValue($user);
            $rights = $this->getUserAllRights($user);
            if($startId>0){
                $items[key($items)] = (array)DB::table('video_short')->where('id',$startId)->first();
            }
            foreach ($items as $one) {
                $one['limit'] = 0;
                if ($one['restricted'] == 1  && (!isset($rights[1]))) {
                    $one['limit'] = 1;
                }
                if ($one['restricted'] == 2) {
                    if(!isset($rights[4])){ //如果没有免费观看金币视频的权益
                        $buy = $this->isBuyShortVideo($one,$user);
                        !$buy && $one['limit'] = 2;
                    }
                }
                $videoRedis = $this->redis('video');
                $one['is_love'] = $videoRedis->sIsMember('shortLove_'.$user->id,$one['id']) ? 1 : 0;
                $sync = $one['sync'] ?? 2;
                $sync = $sync>0 ? $sync : 2;
                $resourceDomain = self::getDomain($sync);
                //统计在线
                $videoRedis->sAdd('onlineUser_'.date('Ymd'),$user->id);
                $videoRedis->expire('onlineUser',3600*24);
                //是否收藏
                $one['is_collect'] = $videoRedis->zScore('shortCollects_'.$user->id, $one['id']) ? 1 : 0;
                $one['url'] = $resourceDomain  .$one['url'];
                $one['dash_url'] = $resourceDomain  .$one['dash_url'];
                $one['cover_img'] = $this->transferImgOut($one['cover_img'],$resourceDomain,$_v);
                //hls处理
                $one['hls_url'] = $resourceDomain .$this->transferHlsUrl($one['hls_url'],$one['id'],$_v);
                //标签
                isset($one['tag_kv']) && $one['tag_kv'] = json_decode($one['tag_kv'],true);
                $data[] = $one;
            }
        }
        return [
            'list' => $data,
            'hasMorePages' => $more,
        ];
    }

    /**
     * 观看限制判断
     * @param $one
     * @param $user
     * @return mixed
     */
    public function viewLimit($one, $user): mixed
    {
        /*if($user->long_vedio_times<1){ //没有免费观看次数再限制
            if ($one['restricted'] == 1) {
                if ((!$user->member_card_type) && (time() - $user->vip_expired > $user->vip_start_last)) {
                    $one['limit'] = 1;
                }
            }
        }*/
        if ($one['restricted'] == 1  && ($this->getVipValue($user)==0)) {
            $one['limit'] = 1;
        }
        return $one;
    }

    /**
     * 播放
     * @param Request $request
     * @return JsonResponse
     */
    public function lists(Request $request): JsonResponse
    {
        try {
            if (isset($request->params)) {
                $params = self::parse($request->params);
                $validated = Validator::make($params, [
                    'start_id' => 'nullable',
                    'keyword' => 'nullable',
                    'cate_id' => 'nullable',
                    'tag_id' => 'nullable',
                    'sort' => 'nullable',
                    'use_gold' => [
                        'nullable',
                        'string',
                        Rule::in(['1', '0']),
                    ],
                ])->validated();
                $user = $request->user();
                $page = $params['page'] ?? 1;
                $cateId = $params['cate_id'] ?? "";
                $tagId = $params['tag_id'] ?? "";
                $starId = $validated['start_id'] ?? '0';
                //关键词搜索
                $words = $params['keyword'] ?? '';
                if (!empty($words)) {
                    $cateId = "";
                    $tagId = "";
                    $starId = '0';
                }
                $tagId!="" && $cateId = $this->cateMapAlias[$tagId];

                $total = 0;
                $perPage = 8;
                $offset = ($page-1)*$perPage;
                $hasMorePages = false;
                $idStr = DB::table('topic')->where('id',$cateId)->value('contain_vids');
                $ids = $idStr ? explode(',',$idStr) : [];
                $catVideoList = [];
                Log::info('==ShortListIds==',$ids);
                if(!empty($ids)){
                    $searchParams = [
                        'index' => 'video_index',
                        'body' => [
                            'track_total_hits' => true,
                            'size' => $perPage,
                            'from' => $offset,
//                            '_source' => [],
                            'query' => [
                                'bool'=>[
                                    'must' => [
                                        ['terms' => ['id'=>$ids]],
//                                        ['term' => ['status'=>1]],
                                        ['term' => ['cid'=>['value'=>10000]]],
                                    ]
                                ]
                            ],
                        ],
                    ];
                    $es = $this->esClient();
                    $response = $es->search($searchParams);
                    //Log::info('==ShortResponse==',[$response]);
                    if(isset($response['hits']) && isset($response['hits']['hits'])){
                        $total = $response['hits']['total']['value'];
                        foreach ($response['hits']['hits'] as $item) {
                            $catVideoList[] = $item['_source'];
                        }
                    }
                    $res['total'] = $total;
                    $hasMorePages = $total >= $perPage*$page;
                }

                //Log::info('==ShortList==',$catVideoList);
                if(!empty($catVideoList)){
                    $res['list'] = $this->handleVideoItems($catVideoList,false,$user->id);
                    //广告
                    //$res['list'] = $this->insertAds($res['list'],'short_video',true, $page, $perPage);
                    //Log::info('==CatList==',$res['list']);
                    $res['hasMorePages'] = $hasMorePages;
                }else{
                    $res['list'] = [];
                }

                return response()->json(['state'=>0, 'data'=>$res??[]]);
                //$res = $this->items($page, $user, $starId, $cateId, $tagId, $words);
                //return response()->json(['state' => 0, 'data' => $res]);
            }
            return response()->json(['state'=>-1, 'msg'=>'参数错误']);
        } catch (Exception $exception) {
            return $this->returnExceptionContent($exception->getMessage());
        }

    }

    /**
     * 点赞
     * @param Request $request
     * @return JsonResponse
     */
    public function like(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $params = self::parse($request->params??'');
            $rules = [
                'id' => 'required|integer',
                'like' => 'required|integer',
            ];
            Validator::make($params, $rules)->validate();
            $id = $params['id'];
            $is_love = $params['like'];
            $videoRedis = $this->redis('video');
            $shortLoveKey = 'shortLove_'.$user->id;
            if ($is_love) {
                $videoRedis->sAdd($shortLoveKey,$id);
                $videoRedis->expire($shortLoveKey,7*24*3600);
                VideoShort::query()->where('id', $id)->increment('likes');
            } else {
                $videoRedis->sRem($shortLoveKey,$id);
                VideoShort::query()->where('id', $id)->decrement('likes');
            }
            return response()->json([
                'state' => 0,
                'msg' => '操作成功'
            ]);
        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("actionLike", [$msg]);
            return response()->json([
                'state' => -1,
                'msg' => '操作失败'
            ]);
        }
    }

    /**
     * 收藏
     * @param Request $request
     * @return JsonResponse
     */
    public function collect(Request $request): JsonResponse
    {
        $userInfo = $request->user();
        if(!$this->collectRight($userInfo)){
            return response()->json([
                'state' => -2,
                'msg' => "权限不足",
            ]);
        }

        $params = self::parse($request->params ?? '');
//        Log::info('==collectShort==',[$params]);
        $rules = [
            'id' => 'required|integer',
//            'collect' => 'required|integer',
            'collect' => 'integer',
        ];
        Validator::make($params, $rules)->validate();
        $id = $params['id'];
        $isCollect = $params['collect'] ?? $params['like'];
        $videoRedis = $this->redis('video');
        $shortCollectsKey = 'shortCollects_'.$userInfo->id;
        if ($isCollect) {
            $videoRedis->zAdd($shortCollectsKey,time(),$id);
            $videoRedis->expire($shortCollectsKey,7*24*3600);
            VideoShort::query()->where('id', $id)->increment('favors');
        } else {
            $videoRedis->zRem($shortCollectsKey,$id);
            VideoShort::query()->where('id', $id)->decrement('favors');
        }
        return response()->json([
            'state' => 0,
            'msg' => '操作成功'
        ]);
        /*try {

        } catch (Exception $exception) {
            return $this->returnExceptionContent($exception->getMessage());
        }*/
    }

}