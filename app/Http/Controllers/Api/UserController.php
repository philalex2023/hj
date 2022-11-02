<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\User;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\EsTrait;
use App\TraitClass\IpTrait;
use App\TraitClass\LoginTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\SmsTrait;
use App\TraitClass\VideoTrait;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Token;
use \App\TraitClass\PHPRedisTrait;

class UserController extends Controller
{
    use MemberCardTrait,SmsTrait,LoginTrait,VideoTrait,PHPRedisTrait,IpTrait,ApiParamsTrait,EsTrait;

    public function set(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $params = self::parse($request->params);
            $onlyFields = ['nickname','email','sex','phone_number','avatar'];
            $setData = [];
            foreach ($params as $key=>$value){
                if(in_array($key,$onlyFields)){
                    $setData[$key] = $value;
                }
            }
            $user = $request->user();
            if(!empty($setData)){
                $state = User::query()->where('id',$user->id)->update($setData);
                $userInfo = User::query()->find($user->id,$onlyFields);
                $res = $userInfo;
                $userInfo['avatar'] += 0;
                if($state>0){
                    $msg = '设置成功';
                }else{
                    $msg = '重复设置或操作过快';
                }
                $state = 0;
                return response()->json([
                    'state'=>$state,
                    'msg'=>$msg,
                    'data'=>$res
                ]);
            }
        }
        return response()->json([
            'state'=>-1,
            'msg'=>'failed',
        ]);
    }

    public function extendInfo(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if(!empty($user)){
                $types = explode(',',$user->member_card_type);
                $memberCardTypeId = !empty($types) ? $types[0] : 0;
                $member_card = [
                    'name' => '未开通',
                    'expired_time' => '',
                    'is_vip' => 0,
                    'vip_expired' => '',
                ];
                if($memberCardTypeId>0){
                    $expired_time = '';
                    $calc = ($user->vip_expired?:0) - (time()-($user->vip_start_last?:time()));
                    $vipDay = ceil((($calc>0)?$calc:0)/(24*60*60));
                    $isVip = 0;
                    if ($calc >= 0) {
                        $isVip = 1;
                    }

                    $cardInfo = $this->getUserCardInfo($memberCardTypeId,$types);
                    $memberCardName = $cardInfo['name'];
                    if($cardInfo['forever']){
                        $vipDay = -1;
                        $isVip = 1;
                    }

                    $member_card = [
                        'name' => $memberCardName,
                        'expired_time' => $expired_time,
                        'is_vip' => str_contains($user->member_card_type,'6') ? -1 : $isVip,
                        'vip_expired' => $vipDay==-1 ? '永久' : date('Y-m-d',time() + $calc),
                        'vip_day' => $vipDay,
                    ];
                }

                $res=[
                    'member_card' => $member_card,
                    'saol_gold' => $user->gold ?:0,
                    'video_times' => $user->long_vedio_times ??0,
                    'kf_url' => $this->generateChatUrl([
                        'account' => $user->account,
                        'nickname' => $user->nickname,
                        'id' => $user->id
                    ])['kf_url'],
                ] ;
                return response()->json(['state'=>0, 'data'=>$res]);
            }
            return response()->json([]);
        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }
    }

    public function billing(Request $request): JsonResponse
    {
        if(isset($request->params)) {
            $params = self::parse($request->params);
            $page = $params['page'] ?? 1;
            $perPage = 16;
            $fields = ['id','type','type_id','amount','updated_at'];
            $uid = $request->user()->id;
            $paginator = DB::table('orders')
                ->where('uid',$uid)
                ->where('status',1)
                ->where('state','>',-1)
                ->orderByDesc('id')->simplePaginate($perPage,$fields,'commentLists',$page);
            $orders = $paginator->items();
            $memberCard = DB::table('member_card')->pluck('name','id')->all();
            foreach ($orders as &$order){
                if($order->type==1){
                    $order->name =  $memberCard[$order->type_id];
                }elseif ($order->type==2){
                    $order->name = '金币充值';
                }
                // $order->amount = number_format($order->amount/100,2);
                unset($order->type);
                unset($order->type_id);
            }
            $res['list'] = $orders;
            $res['hasMorePages'] = $paginator->hasMorePages();
            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);
        }
        return response()->json([]);
    }

    /*public function sendMsg($msg='')
    {
        //通知
        $tgApiToken = '5463455642:AAFPPpmsx_b4UvrQvlHZzKyd2ItxMIQnhgM';
        $apiUrl = 'https://api.telegram.org/bot' .$tgApiToken.'/sendMessage';
        $input = [
            'chat_id'=>'-1001729090537',
            'text'=>$msg,
        ];
        $curl = (new Client([
            //'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'verify' => false,
        ]))->post($apiUrl,['form_params' => $input]);
        $this->info($curl->getBody()->getContents());
    }*/

    public function billingClear(Request $request): JsonResponse
    {
        $user = $request->user();
        DB::table('orders')->where('uid',$user->id)->update(['state' => -1]);
        return response()->json([
            'state'=>0,
            'msg'=> '账单已清除'
        ]);
    }

    public function myShare(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $params = self::parse($request->params);
            $page = $params['page'] ?? 1;
            $perPage = 10;
            if(isset($params['pageSize']) && ($params['pageSize']<10)){
                $perPage = $params['pageSize'];
            }
            $user = $request->user();
            $userField = ['id','avatar','nickname','updated_at'];
            $paginator = DB::table('users')
                ->where('pid',$user->id)
                ->simplePaginate($perPage,$userField,'myShare',$page);
            $res['hasMorePages'] = $paginator->hasMorePages();
            $pageData = $paginator->toArray();
            $res['list'] = $pageData['data'];
            foreach ($res['list'] as &$item){
                $dateTime = explode(' ',$item->updated_at);
                $item->date = $dateTime[0] ?? '';
                $item->avatar = $item->avatar>0 ? (int)$item->avatar : rand(1,13);
                unset($item->updated_at);
            }
            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);
        }
        return response()->json([]);
    }

    public function myCollect(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $perPage = 10;
            $res = [];
            $params = self::parse($request->params);
            $user = $request->user();
            $videoRedis = $this->redis('video');
            $videoCollectsKey = 'videoCollects_'.$user->id;
            $shortCollectsKey = 'shortCollects_'.$user->id;
            if(isset($params['delete']) && $params['delete']==1){
                $vid = $params['vid'] ?? [];
                if(!empty($vid)){
                    //清除相关redis中的key
                    $videoRedis->zRem($videoCollectsKey,...$vid);
                    $videoRedis->zRem($shortCollectsKey,...$vid);
                }
                return response()->json([
                    'state'=>0,
                    'msg' => '删除成功',
                    'data'=>json_decode('{}')
                ]);
            }
            $page = $params['page'] ?? 1;
            if(isset($params['pageSize']) && ($params['pageSize']<$perPage)){
                $perPage = $params['pageSize'];
            }

            //
            $vidArr = $videoRedis->zRevRange($videoCollectsKey,0,-1,true);
            $videoIds = $vidArr ? array_keys($vidArr) : [];
            $vidArrShort = $videoRedis->zRevRange($shortCollectsKey,0,-1,true);
            $shortVideoIds = $vidArrShort ? array_keys($vidArrShort) : [];
//            $vidArrAll = [...$vidArr,...$vidArrShort];
            $vidArrAll = $vidArr + $vidArrShort;

            if(empty($vidArrAll)){
                Log::info('myCollect==',[$vidArrAll]);
                return response()->json([
                    'state'=>0,
                    'data'=>(object)'{}'
                ]);
            }

            $ids = [...$videoIds,...$shortVideoIds];

//            $videoList = DB::table('video')->select($this->videoFields)->whereIn('id',$ids)->get()->toArray();
            $videoList = $this->getVideoByIdsForEs($ids,$this->videoFields);

            foreach ($videoList as &$iv){
                $iv = (array)$iv;
                $iv['usage'] = 1;
                $iv['score'] = $vidArrAll[$iv['id']] ?? 0;
                $iv['updated_at'] = date('Y-m-d H:i:s',$iv['score']);
            }

            $result = [...$videoList];
            $score = array_column($result,'score');
            array_multisort($score,SORT_DESC,$result);
            $offset = ($page-1)*$perPage;
            $pageLists = array_slice($result,$offset,$perPage);
            $hasMorePages = count($result) > $perPage*$page;
            //路径处理
            $res['list'] = $this->handleVideoItems($pageLists,true, true);
            //时长转秒
            $res['list'] = self::transferSeconds($res['list']);
            $res['hasMorePages'] = $hasMorePages;
            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);
        }
        return response()->json([
            'state'=>-1,
            'data'=>'参数错误'
        ]);
    }

    public function viewHistory(Request $request): JsonResponse
    {
        try {
            if(isset($request->params)){
                $perPage = 10;
                $res = [];
                $params = self::parse($request->params);
                $user = $request->user();
                $videoRedis = $this->redis('video');
                $view_history_key = 'view_history_'.$user->id;
                if(isset($params['delete']) && $params['delete']==1){
                    $vid = $params['vid'] ?? [];
                    if(!empty($vid)){
                        $videoRedis->zRem($view_history_key,...$vid);
                    }
                    return response()->json([
                        'state'=>0,
                        'msg' => '删除成功',
                        'data'=>[]
                    ]);
                }
                $page = $params['page'] ?? 1;
                if(isset($params['pageSize']) && ($params['pageSize']<10)){
                    $perPage = $params['pageSize'];
                }

                $vidArr = $videoRedis->zRevRange($view_history_key,0,-1,true);
                $videoIds = $vidArr ? array_keys($vidArr) : [];


                $video = $this->getVideoByIdsForEs($videoIds,$this->videoFields);

                foreach ($video as &$r){
                    $r['usage'] = 1;
                    $r['score'] = $vidArr[$r['id']];
                    $r['updated_at'] = date('Y-m-d H:i:s',$r['score']);
                }

                //短视频
                $view_history_key_short = 'viewShortHistory_'.$user->id;
                $vidArrShort = $videoRedis->zRevRange($view_history_key_short,0,-1,true);
                //Log::info('test==',$vidArrShort);
                $videoShortIds = $vidArrShort ? array_keys($vidArrShort) : [];
                $videoShort = $this->getVideoByIdsForEs($videoShortIds,$this->videoFields);
                foreach ($videoShort as &$sr){
                    $sr['usage'] = 2;
                    $sr['score'] = $vidArrShort[$sr['vs_id']];
                    $sr['updated_at'] = date('Y-m-d H:i:s',$sr['score']);
                }

                $result = [...$video,...$videoShort];
                $score = array_column($result,'score');
                array_multisort($score,SORT_DESC,$result);
                $offset = ($page-1)*$perPage;
                $pageLists = array_slice($result,$offset,$perPage);
                $hasMorePages = count($result) > $perPage*$page;
                //路径处理
                $res['list'] = $this->handleVideoItems($pageLists,true, $user->id);
                //时长转秒
                $res['list'] = self::transferSeconds($res['list']);
                $res['hasMorePages'] = $hasMorePages;

                return response()->json([
                    'state'=>0,
                    'data'=>$res
                ]);
            }
            return response()->json([]);
        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }

    }

    public function overViewHistory(Request $request): JsonResponse
    {
        try {
            //if(isset($request->params)){
            $perPage = 6;
            $res = [];
            //$params = self::parse($request->params??'');
            $user = $request->user();
            $videoRedis = $this->redis('video');
            $view_history_key = 'view_history_'.$user->id;

            $page = 1;


            $vidArr = $videoRedis->zRevRange($view_history_key,0,-1,true);
            $videoIds = $vidArr ? array_keys($vidArr) : [];

            $offset = ($page-1)*$perPage;
            $video = DB::table('video')->whereIn('id',$videoIds)->get($this->videoFields)->toArray();
            $video = $this->getVideoByIdsForEs($videoIds,$this->videoFields);

            foreach ($video as &$r){
                $r = (array)$r;
                $r['usage'] = 1;
                $r['score'] = $vidArr[$r['id']];
                $r['updated_at'] = date('Y-m-d H:i:s',$r['score']);
            }
            //短视频
            $view_history_key_short = 'viewShortHistory_'.$user->id;
            $vidArrShort = $videoRedis->zRevRange($view_history_key_short,0,-1,true);
            //Log::info('test==',$vidArrShort);
            $videoShortIds = $vidArrShort ? array_keys($vidArrShort) : [];
            $videoShort = DB::table('video')->whereIn('id',$videoShortIds)->get($this->videoFields)->toArray();
            $videoShort = !empty($videoShortIds) ? DB::table('video')->whereIn('id',$videoShortIds)->get($this->videoFields)->toArray() : [];
            foreach ($videoShort as &$sr){
                $sr = (array)$sr;
                $sr['usage'] = 2;
                $sr['score'] = $vidArrShort[$sr['vs_id']];
                $sr['updated_at'] = date('Y-m-d H:i:s',$sr['score']);
            }
            $result = [...$video,...$videoShort];
            $score = array_column($result,'score');
            array_multisort($score,SORT_DESC,$result);
            $offset = ($page-1)*$perPage;
            $pageLists = array_slice($result,$offset,$perPage);
            if(!isset($result[0])){
                $pageLists = DB::table('video')->inRandomOrder()->limit(6)->get($this->videoFields)->toArray();
                $pageLists = $this->getVideoByRandomForEs(6,$this->videoFields);
//                    $pageLists = DB::table('video')->inRandomOrder()->limit(6)->get($this->videoFields)->toArray();
            }
            //路径处理
            $res['list'] = $this->handleVideoItems($pageLists,true, $user->id);
            //时长转秒
            $res['list'] = self::transferSeconds($res['list']);
            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);
            //}
            //return response()->json([]);
        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }

    }

    public static function transferSeconds($lists)
    {
        foreach ($lists as &$list) {
            if(isset($list->duration) && $list->duration>0){
                $His = explode(':',$list->duration);
                if(!empty($His)){
                    switch (array_key_last($His)){
                        case 0:
                            $His[0]+=0;
                            $list->duration_seconds = $His[0];
                            break;
                        case 1:
                            $His[0]+=0;
                            $His[1]+=0;
                            $list->duration_seconds = $His[0]*60 + $His[1];
                            break;
                        case 2:
                            $His[0]+=0;
                            $His[1]+=0;
                            $His[2]+=0;
                            $list->duration_seconds = $His[0] * 60 * 60 + $His[1] * 60 + $His[2];
                            break;
                    }
                }
            }
        }
        return $lists;
    }

    public function bindInviteCode(Request $request)
    {
        if(isset($request->params)){
            $params = self::parse($request->params);
            $validated = Validator::make($params, [
                'code' => 'required|string',
            ])->validated();
            $code = $validated['code'] ?? '';
            if(!empty($code)){
                $user = $request->user();
                if($user->pid>0){
                    return response()->json(['state'=>-1, 'msg'=>'不能重复绑定']);
                }
                $pid = User::query()->where('promotion_code',$code)->value('id');
                if($pid==$user->id){
                    return response()->json(['state'=>-1, 'msg'=>'不能绑定自己']);
                }
                User::query()->where('id',$user->id)->update(['pid' => $pid]);
                return response()->json(['state'=>0, 'msg'=>'绑定成功']);
            }
        }
        return [];
    }

    public function getAreaNum(Request $request): JsonResponse
    {
        return response()->json(['state'=>0, 'data'=>$this->getSmsAreaNum()]);
    }

    public function sendSmsCode(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $params = self::parse($request->params);
            $validated = Validator::make($params, [
                'phone' => 'required|integer',
                'areaNum' => 'required|integer',
            ])->validated();

            //$ip = $this->getRealIp();
            $smsKey = 'sms_codes_'.$validated['phone'];
            $redis = $this->redis();
            $times = 1;
            if($redis->exists($smsKey)){
                $hashValue = $redis->hMGet($smsKey,['status','at_time','times']);
                $times = $hashValue['times']+1;
                if(strtotime("-3 minute")>$hashValue['at_time']){
                    return response()->json(['state'=>-1, 'msg'=>'请不要重复发送！']);
                }
                if($times>20){
                    return response()->json(['state'=>-1, 'msg'=>'您当天累计已发送20次！']);
                }
            }

            $code = mt_rand(100000, 999999);
            $type = $validated['areaNum']!='86' ? 2 : 1;
            switch ($type){
                case 1:
                    $this->sendChinaSmsCode($validated['phone'], $code);
                    break;
                default:
                    $this->sendInternationalSmsCode($validated['areaNum'],$validated['phone'], $code);
                    break;
            }
            $smsData = [
                'phone' => $validated['phone'],
                'area_number' => $validated['areaNum'],
                'code' => $code,
                //'status' => 0,
                'times' => $times,
                'at_time' => time(),
            ];
            $setRes = $redis->hMSet($smsKey,$smsData);
            $redis->expire($smsKey,86400);
            if($setRes){
                return response()->json(['state'=>0, 'msg'=>'发送成功']);
            }
        }
        return response()->json([]);
    }

    public function bindPhone(Request $request): JsonResponse
    {
        try {
            if(isset($request->params)){
                $params = self::parse($request->params);
                $validated = Validator::make($params, [
                    'phone' => 'required|integer',
                    'code' => 'required|integer',
                ])->validated();
                $phoneUserId = User::query()->where('phone_number',$validated['phone'])->value('id');
                if(!isset($validated['code'])){
                    return response()->json(['state'=>-1, 'msg'=>'缺少验证码']);
                }
                if($phoneUserId > 0){
                    return response()->json(['state'=>-1, 'msg'=>'该手机号已绑定过']);
                }
                $smsCode = $this->validateSmsCode($validated['phone'],$validated['code']);
                if(!$smsCode){
                    return response()->json(['state'=>-1, 'msg'=>'短信验证码不正确']);
                }
                $user = $request->user();
                DB::table('users')->where('id',$user->id)->update(['phone_number'=>$smsCode['phone'],'area_number'=>$smsCode['area_number']]);
                //统计注册量
                Cache::forget("cachedUser.{$user->id}");
                Log::info('==bindPhone==',['success']);
                return response()->json(['state'=>0, 'msg'=>'绑定成功']);
            }
            return response()->json([]);
        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }

    }

    public function findADByPhone(Request $request): JsonResponse
    {
        try {
            if(isset($request->params)){
                $params = self::parse($request->params);
                $validated = Validator::make($params, [
                    'phone' => 'required|integer',
                    'code' => 'required|integer',
                ])->validated();

                $lock = Cache::lock('findADByPhone_lock_'.$validated['phone'],10);
                if($lock->get()){
                    //====
                    $smsCode = $this->validateSmsCode($validated['phone'],$validated['code']);
                    Log::debug('findADByPhone===',[$validated]);
                    Log::debug('validateSmsCode===',[$smsCode]);
                    if(!$smsCode){
                        return response()->json(['state'=>-1, 'msg'=>'短信验证码不正确']);
                    }
                    //====
                    $requestUser = $request->user();
                    $userModel = User::query()
                        ->where('phone_number',$validated['phone'])
                        ->where('status',1)
                        ->where('area_number',$smsCode['area_number']);
                    $user = $userModel->first();
                    if(!$user){
                        Log::debug('no_bind_phone===',[$validated]);
                        return response()->json(['state'=>-1, 'msg'=>'该手机没有绑定过帐号,无法找回']);
                    }
                    //同一账号的情况
                    if($requestUser->account == $user->account){
                        Log::debug('find_phone_same_bind_phone===',[$validated]);
                        return response()->json(['state'=>-1, 'msg'=>'找回账号与此账号是同一账号']);
                    }
                    $requestUser->status = 1;
                    $requestUser->vip_start_last = $user->vip_start_last;
                    $requestUser->vip_expired = $user->vip_expired;
                    $requestUser->vip = $user->vip;
                    $requestUser->gold = $user->gold;
                    $requestUser->avatar = $user->avatar;
                    $requestUser->promotion_code = $user->promotion_code;
                    $requestUser->member_card_type = $user->member_card_type;
                    $requestUser->balance = $user->balance;
                    $requestUser->phone_number = $user->phone_number;
                    $requestUser->area_number = $user->area_number;
                    $requestUser->channel_id = $user->channel_id;
                    $requestUser->save();

                    $tokenResult = $requestUser->createToken($requestUser->account,['check-user']);
                    $token = $tokenResult->token;
                    $token->expires_at = Carbon::now()->addDays();
                    $token->save();
                    $requestUser = $requestUser->only($this->loginUserFields);
                    if(isset($requestUser['avatar'])){
                        $requestUser['avatar'] += 0;
                    }
                    $requestUser['token'] = $tokenResult->accessToken;
                    $requestUser['token_type'] = 'Bearer';
                    $requestUser['expires_at'] = Carbon::parse(
                        $tokenResult->token->expires_at
                    )->toDateTimeString();
                    $requestUser['expires_at_timestamp'] = strtotime($requestUser['expires_at']);
                    //生成用户专有的客服链接
                    $requestUser = $this->generateChatUrl($requestUser);
                    User::query()->where('id',$user->id)->update([
                        'vip'=>0,
                        'member_card_type'=>'',
                        //'status'=>0,
                        'phone_number'=>0,
                        'area_number'=>0,
                        'long_vedio_times'=>0,
                        'short_vedio_times'=>0,
                        'vip_start_last'=>0,
                        'vip_expired'=>0,
                        'gold'=>0
                    ]);

                    $tokenId = Token::query()->where('name',$user->account)->value('id');
                    $tokenKey = 'api_passport_token_'.$tokenId;
                    $this->redis()->del($tokenKey);
                    Cache::forget("cachedUser.{$user->id}");
                    Cache::forget("cachedUser.".$requestUser['id']);
                    $lock->release();
                    Log::debug('==findADByPhoneRes===',['find back success']);
                    return response()->json(['state'=>0, 'data'=>$requestUser, 'msg'=>'账号找回成功']);
                }else{
                    Log::debug('==findADByPhoneGetLock===',['未释放锁',...$validated]);
                    return response()->json(['state'=>-1, 'msg'=>'操作频繁']);
                }

            }
            return response()->json([]);
        } catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }

    }

}
