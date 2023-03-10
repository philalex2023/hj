<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\AdminGoldLog;
use App\Models\User;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\StatisticTrait;
use App\TraitClass\VideoTrait;
use App\TraitClass\VipRights;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VideoController extends Controller
{
    use VideoTrait,PHPRedisTrait,VipRights,MemberCardTrait,StatisticTrait,ApiParamsTrait;

    public function processViewVideo($user,$video)
    {
        $vid = $video['id'];
        $uid = $user->id;
        //$videoRedis = $this->redis('video');
        $view_history_key = 'view_history_'.$uid;
        $video['limit'] == 0 && DB::table('video')->where('id',$vid)->increment('views'); //增加该视频播放次数
        //up主统计
        if($video['limit'] == 0 && $video['type'] == 4){
            $time = strtotime(date('Y-m-d'));
            $upIncomeBuild = DB::table('up_play_day')->where('uid',$video['uid'])->where('at_time',$time);
            if(!$upIncomeBuild->exists()){
                $insertData = [
                    'uid' => $video['uid'],
                    'at_time' => $time,
                    'play_times' => 1,
                ];
                $upIncomeBuild->insert($insertData);
            }else{
                $upIncomeBuild->increment('play_times');
            }

        }

        Redis::pipeline(function ($pipe) use ($uid,$vid,$view_history_key){
            //统计在线
            $dayData = date('Ymd');
            $pipe->zAdd('online_user_'.$dayData,time(),$uid);
            $pipe->expire('online_user_'.$dayData,3600*24*7);

            //插入历史记录
            $pipe->select(3);
            $pipe->zAdd($view_history_key,time(),$vid);
            $pipe->expire($view_history_key,7*24*3600);
        });

        if($user->long_vedio_times>0){
            /*if((date('Y-m-d')==date('Y-m-d',strtotime($user->created_at)))){
                $this->saveStatisticByDay('active_view_users',$user->channel_id,$user->device_system);
            }*/
            //
            $video['limit']==1 && DB::table('users')->where('id',$user->id)->where('long_vedio_times','>',0)->decrement('long_vedio_times'); //当日观看次数减一
        }
    }

    //播放
    public function actionView(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (isset($request->params)) {
                $user = $request->user();
                //$viewLongVideoTimes = $user->long_vedio_times; //观看次数
                // 业务逻辑
                $params = self::parse($request->params);
                $validated = Validator::make($params, [
                    'id' => 'required|integer|min:1',
                    'use_gold' => [
                        'nullable',
                        'string',
                        Rule::in(['1', '0']),
                    ],
                ])->validated();
                $useGold = intval($validated['use_gold'] ?? 1);
                $one = (array)$this->getVideoById($validated['id']);
                if (!empty($one)) {
                    $one = $this->handleVideoItems([$one], true,$user->id,['cid'=>$one['cid'],'device_system'=>$user->device_system])[0];

                    //观看限制
                    //是否有观看次数
                    $one['restricted'] = (int)$one['restricted'];
                    $one = $this->vipOrGold($one, $user);
                    if (($useGold==1) && $one['limit'] == 2) {
                        // 如果金币则尝试购买
                        $buy = $this->useGold($one, $user);
                        $buy && ($one['limit'] = 0);
                    }

                    $this->processViewVideo($user, $one);
                }
                Cache::forget('cachedUser.'.$user->id);
                return response()->json(['state' => 0, 'data' => $one]);
            }
            return response()->json(['state' => -1, 'msg' => "参数错误",'data'=>[]]);
        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("actionView", [$msg]);
            return response()->json(['state' => -1, 'msg' => $msg,'data'=>[]]);
        }

    }

    //点赞
    public function actionLike(Request $request): \Illuminate\Http\JsonResponse
    {
        if (isset($request->params)) {
            $user = $request->user();
            $params = self::parse($request->params);
            $rules = [
                'id' => 'required|integer',
                'like' => 'required|integer',
            ];
            Validator::make($params, $rules)->validate();
            $id = $params['id'];
            $is_love = $params['like'];
            try {
                $videoRedis = $this->redis('video');
                $videoLoveKey = 'videoLove_'.$user->id;
                $videoBuild = Video::query()->where('id', $id);
                if ($is_love) {
                    $videoRedis->sAdd($videoLoveKey,$id);
                    $videoRedis->expire($videoLoveKey,7*24*3600);
                    $videoBuild->increment('likes');
                } else {
                    $videoRedis->sRem($videoLoveKey,$id);
                    $videoBuild->value('likes')>0 && $videoBuild->decrement('likes');
                }
                return response()->json([
                    'state' => 0,
                    'data' => [],
                ]);
            } catch (Exception $exception) {
                $msg = $exception->getMessage();
                Log::error("actionLike", [$msg]);
            }
        }
        return response()->json([
            'state' => -1,
            'msg' => "参数错误",
        ]);
    }

    public function actionShare(Request $request)
    {
        $user = $request->user();
        $code = $user->promotion_code ?? null;
        if (!empty($code)) {
            $domainArr = array_column(Domain::query()
                ->where('status', 1)
                ->where('type', '<', 2)
                ->get(['id','name'])->all(),null,'id');
            if(empty($domainArr)){
                return response()->json([
                    'state' => -1,
                    'msg' => "请配置渠道推广域名",
                ]);
            }
            $randKey = array_rand($domainArr);
            $domain = $domainArr[$randKey]['name'];
            $promotion_url = $domain . '?code=' . $code;
            //奖励规则
            $appConfig = config_cache('app');
            return response()->json([
                'state' => 0,
                'data' => [
                    'invite_code' => $code,
                    'reward_rules' => $appConfig['reward_rules'] ?? '',
                    'promotion_url' => $promotion_url
                ],
            ]);
        }
        return [];
    }

    //
    public function actionCollect(Request $request): \Illuminate\Http\JsonResponse
    {
        if (isset($request->params)) {
            $user = $request->user();
            $params = self::parse($request->params);
            $rules = [
                'id' => 'required|integer',
                'collect' => 'required|integer',
            ];
            Validator::make($params, $rules)->validate();
            $id = $params['id'];
            $is_collect = $params['collect'];

            if(!$this->collectRight($user)){
                return response()->json([
                    'state' => -2,
                    'msg' => "权限不足",
                ]);
            }
            try {
                $videoRedis = $this->redis('video');
                $videoCollectsKey = 'videoCollects_'.$user->id;
                $videoBuild = Video::query()->where('id', $id);
                if ($is_collect) {
                    $videoRedis->zAdd($videoCollectsKey,time(),$id);
                    $videoRedis->expire($videoCollectsKey,10*24*3600);
                    $videoBuild->increment('collects');
                } else {
                    $videoRedis->zRem($videoCollectsKey,$id);
                    $videoBuild->value('collects') && $videoBuild->decrement('collects');
                }

            } catch (Exception $exception) {
                return $this->returnExceptionContent($exception->getMessage());
            }
        } else {
            return response()->json([
                'state' => -1,
                'msg' => "参数错误",
            ]);
        }
        return response()->json([
            'state' => 0,
            'data' => [],
        ]);
    }

    /**
     * 金豆判断
     * @param $one
     * @param $user
     * @return mixed
     */
    public function vipOrGold($one, $user): mixed
    {
        $rights = $this->getUserAllRights($user);
        /*if($user->id=='6977976'){
            Log::info('ViewVideoUser1==',[$user->id,$rights]);
        }*/
        $one['limit'] = 0;
        switch ($one['restricted']) {
            case 0: //免费视频非vip会员只能免费看一次
                $loginRedis = $this->redis('login');
                $key = 'viewFreeVideo:'.$one['id'];
                $isView = $loginRedis->getBit($key,$user->id);
                if($isView==1){
                    !isset($rights[1]) && $one['limit'] = 1 && $one['restricted'] = 1;
                }else{
                    $loginRedis->setBit($key,$user->id,1);
                    $loginRedis->expire($key,7*24*3600);
                }
                break;
            case 1:
                $isExpired = ($user->vip_start_last+$user->vip_expired) < time();
                if(!isset($rights[1]) && $isExpired){
                    $one['limit'] = 1;
                }
                break;
            case 2:
                if(isset($rights[4])){
                    return $one;
                }
                $videoRedis = $this->redis('video');
                $buyVideoKey = 'buyGoldVideo_' . $user->id;
                if (!$videoRedis->sIsMember($buyVideoKey,$one['id'])) {
                    $one['limit'] = 2;
                }
                break;
        }
        return $one;
    }

    /**
     * 花费金豆
     * @param $one
     * @param $user
     * @return bool
     */
    public function useGold($one, $user): bool
    {
        // 扣除金币
        $newGold = $user->gold - $one['gold'];
        $model = User::query();
        $userEffect = $model->where('id', '=', $user->id)
            ->where('gold', '>=', $one['gold'])
            ->update(
                ['gold' => $newGold]
            );
        if (!$userEffect) {
            return false;
        }
        $videoRedis = $this->redis('video');
//        $buyVideoKey = 'buyVideoWithGold_' . $user->id;
        $buyVideoKey = 'buyGoldVideo_' . $user->id;
        $videoRedis->sAdd($buyVideoKey,$one['id']);
        $videoRedis->expire($buyVideoKey,90*24*3600);
        //up主统计
        if($one['type'] == 4){
            /*$upBuyVideoKey = 'up_income_'.$one['uid'];
            $upRedis = $this->redis('tv');*/
            $configData = config_cache('app');
            $percentage = round(($configData['up_master_profit_percentage'] ?? 0)/100,2);
            $goldIncome = $percentage * $one['gold'] * 100;
            $time = strtotime(date('Y-m-d'));
            Log::info('use_gold',['up_uid:'.$one['uid'],$goldIncome]);
            $upIncomeBuild = DB::table('income_day')->where('uid',$one['uid'])->where('at_time',$time);
            if(!$upIncomeBuild->exists()){
                $insertData = [
                    'uid' => $one['uid'],
                    'at_time' => $time,
                    'gold' => $goldIncome,
                ];
                $upIncomeBuild->insert($insertData);
            }else{
                $upIncomeBuild->increment('gold',$goldIncome);
            }

        }
        DB::table('video')->where('id',$one['id'])->increment('buyers');
        return true;
    }
}
