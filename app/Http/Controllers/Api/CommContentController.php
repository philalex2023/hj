<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\CommFocus;
use App\Models\AdminGoldLog;
use App\Models\LoginLog;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\BbsTrait;
use App\TraitClass\LoginTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\UserTrait;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommContentController extends Controller
{
    use PHPRedisTrait,BbsTrait,UserTrait,ApiParamsTrait;

    /**
     * 文章发表
     * @param Request $request
     * @return array|JsonResponse
     */
    public function post(Request $request): JsonResponse|array
    {
        DB::beginTransaction();
        try {
            if(!isset($request->params)){
                return response()->json([]);
            }
            $params = self::parse($request->params);
            Validator::make($params, [
                'content' => 'nullable',
                'thumbs' => 'nullable',
                'video' => 'nullable',
                'video_picture' => 'nullable',
                'category_id' => 'nullable',
                'location_name' => 'nullable',
            ])->validate();
            $content = $params['content'] ?? '';
            $videoPicture = $params['video_picture'] ?? '[]';
            $thumbs = $params['thumbs'] ?? '[]';
            $video = $params['video'] ?? '[]';
            $categoryId = $params['category_id'] ?? '';
            if ($thumbs) {
                $thumbsRaw = json_decode($thumbs,true);
                $thumbsData = [];
                foreach ($thumbsRaw as $item) {
                    $thumbsData[] = str_replace(env('RESOURCE_DOMAIN'),'',$item);
                }
                $thumbs = json_encode($thumbsData);
            }
            if ($video) {
                $videoRaw = json_decode($video,true);
                $videoData = [];
                foreach ($videoRaw as $itemVideo) {
                    $videoData[] = str_replace(env('RESOURCE_DOMAIN'),'',$itemVideo);
                }
                $video = json_encode($videoData);
            }
            if ($videoPicture) {
                $videoThumbsRaw = json_decode($videoPicture,true);
                $videoThumbsData = [];
                foreach ($videoThumbsRaw as $itemPic) {
                    $videoThumbsData[] = str_replace(env('RESOURCE_DOMAIN'),'',$itemPic);
                }
                $videoPicture = json_encode($videoThumbsData);
            }
            $insertData = [
                'thumbs' => $thumbs,
                'video' => $video,
                'video_picture' => $videoPicture,
                'category_id' => $categoryId,
                'author_id' => $request->user()->id,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            DB::table('community_bbs')->insertGetId($insertData);
            DB::commit();
            return response()->json([
                'state' => 0,
                'msg' => '发帖成功'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('bbsPost===' . $e->getMessage());
            return response()->json([
                'state' => -1,
                'msg' => $e->getMessage()
            ]);

        }
    }

    /**
     * @param Request $request
     * @return array|JsonResponse
     */
    public function lists(Request $request): JsonResponse|array
    {
        try {
            if(!isset($request->params)){
                return response()->json([]);
            }
            $params = self::parse($request->params);
            Validator::make($params, [
                'cid_1' => 'nullable',
                'cid_2' => 'nullable',
                'location_name' => 'nullable',
                'page' => 'nullable',
            ])->validate();
            // 一二级分类
            $cid1 = $params['cid_1'] ?? 0;
            $page = $params['page'] ?? 1;
            $locationName = $params['location_name'] ?? '';
            $cid2 = $params['cid_2'] ?? 0;
            //Log::info('===COMMLIST-params==',[$params]);
            // 得到一级分类help
            $help = $this->redis()->hGet('common_cate_help', "c_{$cid1}");
            $uid = $request->user()->id;
            if (in_array($help, ['focus', 'hot'])) {
                $res = $this->$help($uid, $locationName, 6, $page);
            } else {
                $res = $this->other($uid, $locationName, $cid1, $cid2, 6, $page, $help);
            }
            if(isset($res['bbs_list']) && !empty($res['bbs_list'])){
                //Log::info('===CommContent===',[$res['bbs_list']]);
                $this->processArea($res['bbs_list']);
            }
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);
        } catch (Exception $exception) {
            return $this->returnExceptionContent($exception->getMessage());
        }
    }

    /**
     * 处理地理数据
     * @param $data
     */
    private function processArea(&$data)
    {
        /*$ids = array_column($data, 'uid');
        $ids = array_filter($ids);
        $lastLogin = LoginLog::query()
            ->select('uid', DB::raw('max(id) as max_id'))->whereIn('uid', $ids)
            ->groupBy('uid')
            ->get()->toArray();
        if (!$lastLogin) {
            return;
        }
        $lastLoginIds = array_column($lastLogin, 'max_id');
        $areaInfo = LoginLog::query()->whereIn('id', $lastLoginIds)
           // ->groupBy('uid')
            ->get()->toArray();
        if (!$areaInfo) {
            return;
        }
        $areaInfoMap = array_column($areaInfo, null, 'uid');*/
        foreach ($data as $k => $v) {
            $data[$k]['location_name'] = '全国';
        }
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function buy(Request $request): JsonResponse
    {
        if(!isset($request->params)){
            return response()->json([]);
        }
        $validated = Validator::make(self::parse($request->params), [
            'id' => 'integer',
        ])->validated();
        $user = $request->user();
        $redis = $this->redis();
        $collectionKey = 'api_ugb_' . $user->id;

        if($validated['id']==0){
            $buyAll = $redis->sIsMember($collectionKey, $validated['id']) || $this->existsUserGoldGame($user->id, $validated['id']);
            if($buyAll){
                return response()->json(['state' => -1, 'data' =>[],'msg'=>'已购买过该商品']);
            }
        }else{
            $buy = $redis->sIsMember($collectionKey, $validated['id']) || $this->existsUserGoldGame($user->id, $validated['id']);
            if($buy){
                return response()->json(['state' => -1, 'data' =>[],'msg'=>'已购买过该商品']);
            }
        }

        $hashValue = $redis->hGetAll('communityBbsItem:'.$validated['id']);
        if(!$hashValue){
            $model = DB::table('community_bbs')->where('id',$validated['id'])->first();
            $this->resetBBSItem($model);
            $hashValue = $model->toArray();
        }
        $gameGold = $validated['id']==0 ? $this->getAllGameNeedGold() : $hashValue['game_gold'];
        $userGold = $user->gold;
        if($userGold<$gameGold){
            return response()->json(['state' => -1, 'data' =>[],'msg'=>'余额不足请充值']);
        }else{
            DB::table('users')->where('id', '=', $user->id)
                ->where('gold', '>=', $gameGold)
                ->update(['gold' => $userGold-$gameGold]);
            $now = date('Y-m-d H:i:s', time());
            AdminGoldLog::query()->create([
                'uid' => $user->id,
                'goods_id' => $validated['id'],
                'cash' => $gameGold,
                'goods_info' => $validated['id']==0 ? '解锁所有游戏' : '游戏ID:'.$hashValue['id'],
                'before_cash' => $user->gold,
                'use_type' => $validated['id']==0 ? 3 : 2,
                'device_system' => $user->device_system,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $redis->sAdd($collectionKey, $validated['id']);
            $redis->expire($collectionKey,172800);
            $redis->sAdd('uid_bid_collection',$collectionKey);
            Cache::forget('cachedUser.'.$user->id);
            DB::table('community_bbs')->where('id',$validated['id'])->increment('buyers');
        }
        return response()->json(['state' => 0, 'data' =>[],'msg'=>'购买成功']);

    }

    /**
     * 详情
     * @param Request $request
     * @return JsonResponse
     */
    public function detail(Request $request): JsonResponse
    {

        try {
            if(!isset($request->params)){
                return response()->json([]);
            }
            $params = self::parse($request->params);
            Validator::make($params, [
                'id' => 'integer',
            ])->validate();
            $id = $params['id'] ?? 0;
            $redis = $this->redis();

            $user = $request->user();
            $uid = $user->id;
            $bbsItemKey = 'communityBbsItem:'.$id;
            $communityBbsList = $redis->hGetAll($bbsItemKey)??[];
            if(!$communityBbsList){
                $model = CommBbs::query()->where('id',$id)->first();
                if(!$model){
                    return response()->json([
                        'state' => 0,
                        'data' => ['list'=>[],'hasMorePages'=>false]
                    ]);
                }
                $this->resetBBSItem($model);
                $communityBbsList = $model->toArray();
            }
            $communityBbsList['category_id'] = (int)($communityBbsList['category_id'] ?? 0);
            $communityBbsList['likes'] = (int)$communityBbsList['likes'];
            $communityBbsList['comments'] = (int)$communityBbsList['comments'];
            $communityBbsList['rewards'] = (int)$communityBbsList['rewards'];
            $communityBbsList['game_gold'] = (int)$communityBbsList['game_gold'];
            $communityBbsList['user_id'] = (int)$communityBbsList['author_id'];
            $communityBbsList['uid'] = (int)$communityBbsList['author_id'];
            $communityBbsList['is_office'] = (int)$communityBbsList['is_office'];
            $communityBbsList['official_type'] = (int)($communityBbsList['official_type']??0);
            $communityBbsList['sex'] = (int)$communityBbsList['sex'];
            $communityBbsList['level'] = (int)$communityBbsList['level'];
            $communityBbsList['vipLevel'] = (int)$communityBbsList['vipLevel'];

            $help = $this->redis()->hGet('common_cate_help', 'c_'.$communityBbsList['category_id']);
            $handleResult = $this->proProcessData($uid, [0=>$communityBbsList], $help, true);
            $result = $handleResult[0];
            $result['category_id'] = $communityBbsList['category_id'];
            $result['user_id'] = $communityBbsList['user_id'] ?? 0;
            //统计在线
            $dayData = date('Ymd');

            $redis->zAdd('online_user_'.$dayData,time(),$uid);
            $redis->expire('online_user_'.$dayData,3600*24*7);
            // 增加点击数
            CommBbs::query()->where('community_bbs.id', $id)->increment('views');
            //Log::info('==userLocationName1==',[$user]);
            // 处理新文章通知
            $mask = $redis->get("c_{$result['category_id']}");
            if ($mask == 'focus') {
                $keyMe = "status_me_focus_{$result['user_id']}";
            } else {
                $keyMe = "status_me_{$mask}_$uid";
            }
            $redis->del($keyMe);
            return response()->json(['state' => 0, 'data' => $result ?? []]);
        } catch (Exception $exception) {
            return $this->returnExceptionContent($exception->getMessage());
        }
    }

    /**
     * 关注列表
     * @param $uid
     * @param string $locationName
     * @param int $perPage
     * @param int $page
     * @return Builder[]|Collection
     */
    private function focus($uid, $locationName = '', $perPage = 6, $page = 1)
    {
        $userList = CommFocus::where('user_id', $uid)->pluck('to_user_id');
        $model = CommBbs::query()
            ->select($this->bbsFields)
            ->whereIn('author_id', $userList)
            ->where('status',1)
            ->orderBy('sort', 'desc')
            ->orderBy('updated_at', 'desc');

        $paginator = $model->simplePaginate($perPage, ['*'], '', $page);
        //加入视频列表
        $res['hasMorePages'] = $paginator->hasMorePages();
        $list = $paginator->items() ?? [];
        $res['bbs_list'] = $this->proProcessData($uid, $list);
        return $res;
    }

    /**
     * 最热
     * @param $uid
     * @param string $locationName
     * @param int $perPage
     * @param int $page
     * @return Builder[]|Collection
     */
    private function hot($uid, $locationName = '', $perPage = 6, $page = 1)
    {
        $model = CommBbs::query()
            ->select($this->bbsFields)
            ->where('community_bbs.status',1)
            ->orderBy('sort', 'desc')
            ->orderBy('updated_at', 'desc');
        /*if ($locationName) {
            $locationName = mb_ereg_replace('市|自治区|县', '', $locationName);
            $model->where('users.location_name', 'like', "%{$locationName}%");
        }*/
        $paginator = $model->simplePaginate($perPage, ['*'], '', $page);
        //加入视频列表
        $res['hasMorePages'] = $paginator->hasMorePages();
        $list = $paginator->items() ?? [];
        $result = $this->proProcessData($uid, $list);
        $res['bbs_list'] = $result;
        return $res;
    }

    /**
     * 其它类别
     * @param $uid
     * @param string $locationName
     * @param int $cid1
     * @param int $cid2
     * @param int $perPage
     * @param int $page
     * @return Collection|array
     */
    private function other($uid, $locationName = '', $cid1 = 0, $cid2 = 0, $perPage = 6, $page = 1, $help='default'): Collection|array|JsonResponse
    {
        $redis = $this->redis();
        $fields = $this->bbsFields;
        if ($cid2 > 0) {
            $rawKey = 'comm_other_cache_'.$cid2;
            $raw = $redis->hGet($rawKey, $page);
//            $raw = false;
            if ($raw) {
                $data = json_decode($raw,true);
            } else {
                $lock = Cache::lock($rawKey.'_lock',10);
                if($lock->get()){
                    $model = CommBbs::query()
                        ->select($fields)
                        ->where('category_id', $cid2)->where('community_bbs.status',1)
                        ->orderBy('sort', 'desc')
                        ->orderBy('created_at', 'desc');
                    $paginator = $model->simplePaginate($perPage, ['*'], '', $page);
                    $data['hasMorePages'] = $paginator->hasMorePages();
                    $data['bbs_list'] = $paginator->items();
                    $redis->hSet($rawKey, $page,json_encode($data));
                    $redis->expire($rawKey,3600);
                    $lock->release();
                }else{
                    return $this->returnExceptionContentForLock('无法获取锁:'.$rawKey.'_lock');
                }

            }
            $data['bbs_list'] = $this->proProcessData($uid, $data['bbs_list'], $help);
            return $data;
        }
        if ($cid1) {
            $oneKey = 'comm_other_cache_one_'.$cid1;
            $oneData = $redis->hGet($oneKey, $page);
//            $oneData = false;
            if($oneData){
                $data = json_decode($oneData,true);
            }else{
                $lockCid1 = Cache::lock($oneKey.'_lock',10);
                if($lockCid1->get()){
                    $ids = $this->getChild($cid1, false);
                    $model = CommBbs::query()
                        ->select($fields)
                        ->whereIn('category_id', $ids)
                        ->where('status',1)
//                        ->orderBy('created_at', 'desc');
                        ->orderBy('sort', 'desc')
                        ->orderBy('created_at', 'desc');
                    $paginator = $model->simplePaginate($perPage, ['*'], '', $page);
                    $data['hasMorePages'] = $paginator->hasMorePages();
                    $data['bbs_list'] = $paginator->items();
                    $redis->hSet($oneKey, $page, json_encode($data));
                    $redis->expire($oneKey,3600);
                    $lockCid1->release();
                }else{
                    return $this->returnExceptionContentForLock('无法获取锁:'.$oneKey.'_lock');
                }
            }
            $data['bbs_list'] = $this->proProcessData($uid, $data['bbs_list'], $help);
            return $data;
        }

        return [];
    }


    /**
     * 得到子分类
     * @param $id
     * @param bool $raw
     * @return mixed
     */
    private function getChild($id, bool $raw = true): mixed
    {
        $data = [];
        $tree = json_decode($this->redis()->get('common_cate'), true) ?? [];
        foreach ($tree as $item) {
            if ($item['id'] == $id) {
                $data = $item['childs'] ?? [];
            }
        }
        if ($raw) {
            return $data;
        }
        return !empty($data) ? array_column($data, 'id') : [$id];

    }
}