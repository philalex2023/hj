<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLogin;
use App\Models\User;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\IpTrait;
use App\TraitClass\LoginTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Token;

class AuthController extends Controller
{
    use LoginTrait, IpTrait, ApiParamsTrait;
    /**
     * Create user
     *
     * @param  [string] name
     * @param  [string] email
     * @param  [string] password
     * @param  [string] password_confirmation
     * @return [string] message
     */
    public function signup(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|confirmed'
        ]);

        /*$user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->status = 1;
        $user->save();*/

        return response()->json([
            'state' => 0,
            'message' => '注册成功'
        ], 201);
    }

    public function reg($validated,$ip,$appInfo,$deviceInfo,$deviceSystem,$accountRedis,$login_info){
        //创建新用户
        $user = new User();
        $user->did = $validated['did'];
        $user->last_did = $validated['did'];
        $user->create_ip = $ip;
        $user->last_ip = $ip;
        $user->gold = 0;
        $user->balance = 0;
        $user->sex = 0;
        $user->member_card_type = 0;
        $user->vip_start_last = '';
        //分配默认相关设置
        $configData = config_cache('app');
        $user->long_vedio_times = $configData['free_view_long_video_times'] ?? 0;
        $user->avatar = rand(1,13);

        $user->device_system = $deviceSystem;

        $user->device_info = $deviceInfo;
        $user->app_info = $appInfo ?? [];
        //
        $nickNames = $this->createNickNames;
        $randNickName = $this->createNickNames[array_rand($nickNames)];
        $getAccountV = $accountRedis->get('account_v');
        $accountV = !$getAccountV ? 1 : $getAccountV;
        $user->account = 'AD-'.$accountV;
        $user->nickname = $randNickName;

        $bindInfo = $this->bindChannel($login_info);
        $user->pid = $bindInfo['pid'];
        $user->channel_id = $bindInfo['channel_id'];
        $user->channel_pid = $bindInfo['channel_pid'];
        $user->save();

        $accountRedis->sAdd('account_did',$validated['did']);
        $accountRedis->incr('account_v');
        return $user;
    }
    /**
     * Login user and create token
     * @throws ValidationException
     */
    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        if(!isset($request->params)){
            return response()->json(['state' => -1, 'msg' => '未提交参数']);
        }
        $params = self::parse($request->params);
        //Log::debug('login_request_params_info===',[$params['did']??'none did']);//参数日志
        $validated = Validator::make($params,$this->loginRules)->validated();
        //短时间内禁止同一设备注册多个账号
        $key = 'api_did_'.$validated['did'];

        $ip = $this->getRealIp();
        if($validated['did']==0){
            Log::debug('illegal_did===',[$validated['did'],$ip]);
            return response()->json(['state' => -1, 'msg' => '非法设备']);
        }
        if(!isset($_SERVER['HTTP_USER_AGENT'])){
            return response()->json(['state' => -1, 'msg' => '非法设备!']);
        }

        $deviceInfo = !is_string($validated['dev']) ? json_encode($validated['dev']) : $validated['dev'] ;
        $deviceSystem = $this->getDeviceSystem($deviceInfo);

        Log::info('login_deviceInfo',[$deviceInfo,$deviceSystem]);

        $appInfo = !is_string($validated['env']) ? json_encode($validated['env']) : $validated['env'] ;
        // 暂时放开轻量版
        if(!strpos($deviceInfo.'', 'ios')){
            $lock = Cache::lock($key,10);
            if(!$lock->get()){
                Log::debug('repeat_register_did===',[$validated['did'],'ip:'.$ip]);//参数日志
                return response()->json(['state' => -1, 'msg' => '请勿重复提交否则会作封禁处理']);
            }
        }

        $test = $validated['test'] ?? false;
        $accountRedis = $this->redis('account');

        $hasDid = !$accountRedis->exists('account_did') ? $this->getDidFromDb($validated['did']) : $accountRedis->sIsMember('account_did',$validated['did']);
        $loginType = !$hasDid ? 1 : 2;

        $login_info = ['device_system'=>$deviceSystem,'clipboard'=>$validated['clipboard']??'','ip'=>$ip];
        Log::info('login_info',$login_info);
        if($loginType===1){ //注册登录
            $regLock = Cache::lock('reg_lock');
            if(!$regLock->get()){
                Log::info('reg_lock',[$ip,$validated]);
                return response()->json(['state' => -1, 'msg' => '服务器繁忙请稍候重试']);
            }

            $user = $this->reg($validated,$ip,$appInfo,$deviceInfo,$deviceSystem,$accountRedis,$login_info);

            $regLock->release();
        }else{ //第二次及以后登录
            $user = User::query()->where('did',$validated['did'])->first($this->loginUserFields);
            if(!$user){ //重新注册
                $user = $this->reg($validated,$ip,$appInfo,$deviceInfo,$deviceSystem,$accountRedis,$login_info);
//                Log::info('Login',['login_type:'.$loginType,'did:'.$validated['did']]);
//                return response()->json(['state' => -1, 'msg' => '用户不存在!']);
            }else{
                if($user->status!=1){
                    Log::info('status',[$user->status]);
                    return response()->json(['state' => -1, 'msg' => '用户被禁用!']);
                }
            }
        }
        $login_info = $user->only($this->loginUserFields);

        $login_info['avatar'] += 0;
        //记录登录日志
        $login_log_data = [
            'ip'=>$ip,
            'uid'=>$login_info['id'],
            'promotion_code'=>$login_info['promotion_code'],
            'type'=>$loginType,
            'account'=>$login_info['account'],
            'nickname'=>$login_info['nickname'],
            'channel_id'=>$login_info['channel_id']??'0',
            'device_info'=> $deviceInfo,
            'clipboard'=> $validated['clipboard'] ?? '',
            'source_info'=> $_SERVER['HTTP_USER_AGENT'],
            'device_system'=> $login_info['device_system'] ?? 0,
        ];
        //Log::debug('login_log_data===',[$login_log_data]);

        ProcessLogin::dispatchAfterResponse($login_log_data);
        /*$job = new ProcessLogin($login_log_data);
        $this->dispatch($job);*/
        //ProcessLogin::dispatch($login_log_data)->delay(now()->addMinutes());
        if($loginType===2){
            Token::query()->where('user_id',$login_info['id'])->delete();
        }
        //重新分配token
        $tokenResult = $user->createToken($user->did,['check-user']);
        $token = $tokenResult->token;
        $token->expires_at = !$test ? Carbon::now()->addDays() : Carbon::now()->addMinutes(3);
        $token->save();

        //Log::debug('login_result_info===',[$login_info]);
        //返回的token信息
        $login_info['token'] = $tokenResult->accessToken;
        $login_info['token_type'] = 'Bearer';
        $login_info['expires_at'] = Carbon::parse($tokenResult->token->expires_at)->toDateTimeString();
        $login_info['expires_at_timestamp'] = strtotime($login_info['expires_at']);
        $login_info['phone_number'] = strval($login_info['phone_number']);
        //生成用户专有的客服链接
        $login_info = $this->generateChatUrl($login_info);

        return response()->json([
            'state'=>0,
            'data'=>$login_info
        ]);
    }

    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json([
            'state' => 0,
            'msg' => '登出成功'
        ]);
    }

    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

}
