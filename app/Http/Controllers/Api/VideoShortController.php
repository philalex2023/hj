<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\AdminVideo;
use App\Models\AdminVideoShort;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Video;
use App\Models\VideoShort;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CommTrait;
use App\TraitClass\DataSourceTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\StatisticTrait;
use App\TraitClass\TopicTrait;
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
    use VideoTrait,PHPRedisTrait,VipRights,StatisticTrait,MemberCardTrait,ApiParamsTrait,CommTrait,DataSourceTrait,TopicTrait;

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
        '-1' => 75,
        '-2' => 76,
        '-3' => 77,
        '-4' => 78,
        '-5' => 79,
        '-6' => 80,
        '-7' => 81,
        '-8' => 82,
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
        $short = DB::table('video')->where('id',$validated['id'])->first();
        if(!$short){
            return response()->json(['state' => -2, 'data' =>['status'=>-2],'msg'=>'记录不存在']);
        }

        $userGold = $user->gold;
        $shortGold = $short->gold/100;
        Log::info('buyShortWithGold',[$user->id,$validated['id'],$userGold,$shortGold,$short->gold]);
        if($userGold < $shortGold){
            return response()->json(['state' => -1, 'data' =>['status'=>-1],'msg'=>'余额不足请充值']);
        }else{
            DB::table('users')->where('id', '=', $user->id)
                ->where('gold', '>=', $shortGold)
                ->update(['gold' => $userGold - $shortGold]);
            $videoRedis->sAdd($buyShortKey,$validated['id']);
            $videoRedis->expire($buyShortKey,90*24*3600);
            Cache::forget('cachedUser.'.$user->id);
            //插入历史记录
            /*$view_history_key = 'viewShortHistory_'.$user->id;
            $videoRedis->zAdd($view_history_key,time(),$validated['id']);
            $videoRedis->expire($view_history_key,7*24*3600);*/
            //插入收藏
            $shortCollectsKey = 'shortCollects_'.$user->id;
            $videoRedis->zAdd($shortCollectsKey,time(),$validated['id']);
            $videoRedis->expire($shortCollectsKey,7*24*3600);
            DB::table('video')->where('id',$validated['id'])->increment('buyers');
        }
        return response()->json(['state' => 0, 'data' =>['status'=>0],'msg'=>'购买成功']);
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
                    'page' => 'required|integer',
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
                $page = $validated['page'] ?? 1;
                $cateId = $validated['cate_id'] ?? "";
                $tagId = $validated['tag_id'] ?? "";
//                $starId = $validated['start_id'] ?? '0';
                //关键词搜索
                $words = $validated['keyword'] ?? '';

                $tagId!="" && $cateId = $this->cateMapAlias[$tagId];

                $total = 0;
                $perPage = 8;
                $offset = ($page-1)*$perPage;

                $catVideoList = [];
                if (!empty($words)) {
                    $query = [
                        'bool'=>[
                            'must' => [
                                ['match'=> ['name' => $words],],
                                ['term' => ['dev_type'=>1]],
                            ]
                        ]
                    ];
                }else{
                    if(!$cateId){
                        return response()->json(['state'=>0, 'data'=>['list'=>[], 'hasMorePages'=>false]]);
                    }
                    $containVidStr = $this->getTopicVideoIdsById($cateId);
                    if(!$containVidStr){
                        return response()->json(['state'=>0, 'data'=>['list'=>[], 'hasMorePages'=>false]]);
                    }
                    $ids = explode(',',$containVidStr);
                    if(!empty($ids)){
                        $idParams = [];
                        $length = count($ids);
                        foreach ($ids as $key => $id) {
                            $idParams[] = ['id' => (int)$id, 'score' => $length - $key];
                        }
                        $query = [
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
                                        'params' => [
                                            'scoring' => $idParams
                                        ],
                                        'source' => "for(i in params.scoring) { if(doc['id'].value == i.id ) return i.score; } return 0;"
                                    ]
                                ]
                            ]
                        ];

                    }
                }

                if(!isset($query)){
                    return response()->json(['state'=>0, 'data'=>['list'=>[], 'hasMorePages'=>false]]);
                }
                $searchParams = [
                    'index' => 'video_index',
                    'body' => [
                        'track_total_hits' => true,
                        'size' => $perPage,
                        'from' => $offset,
                        '_source' => $this->videoFields,
                        'query' => $query,
                    ],
                ];
                $es = $this->esClient();
                $response = $es->search($searchParams);
                if(isset($response['hits']) && isset($response['hits']['hits'])){
                    $total = $response['hits']['total']['value'];
                    foreach ($response['hits']['hits'] as $item) {
                        $catVideoList[] = $item['_source'];
                    }
                }
                $res['total'] = $total;
                $hasMorePages = $total >= $perPage*$page;

                //Log::info('==ShortList==',$catVideoList);
                if(!empty($catVideoList)){
                    $res['list'] = $this->handleVideoItems($catVideoList,true,$user->id);
                    $rights = $this->getUserAllRights($user);
                    //判断是否购买或是vip
                    foreach ($res['list'] as &$one){
                        $restricted = (int)$one['restricted'];
                        $one['limit'] = 0;
                        switch ($restricted){
                            case 2: //金币
                                if(!isset($rights[4])){ //如果没有免费观看金币视频的权益
                                    $buy = $this->isBuyShortVideo($one,$user);
                                    !$buy && $one['limit'] = 2;
                                }
                                break;
                            case 1: //vip会员
                                if ($one['restricted'] == 1  && (!isset($rights[1]))) {
                                    $one['limit'] = 1;
                                }
                                break;
                        }

                    }

                    $res['hasMorePages'] = $hasMorePages;
                }else{
                    $res['list'] = [];
                }

                return response()->json(['state'=>0, 'data'=>$res??[]]);

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
            $videoBuild = DB::table('video')->where('id', $id);
            if ($is_love) {
                $videoRedis->sAdd($shortLoveKey,$id);
                $videoRedis->expire($shortLoveKey,7*24*3600);
                $videoBuild->increment('likes');
            } else {
                $videoRedis->sRem($shortLoveKey,$id);
                $videoBuild->value('likes')>0 && $videoBuild->decrement('likes');
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
        $videoBuild = DB::table('video')->where('id', $id);
        if ($isCollect) {
            $videoRedis->zAdd($shortCollectsKey,time(),$id);
            $videoRedis->expire($shortCollectsKey,7*24*3600);
            $videoBuild->increment('likes');
        } else {
            $videoRedis->zRem($shortCollectsKey,$id);
            $videoBuild->value('likes')>0 && DB::table('video')->where('id', $id)->decrement('likes');
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