<?php

namespace App\TraitClass;

use App\Models\User;
use Illuminate\Support\Facades\Log;

trait LoginTrait
{
    use ChannelTrait,PHPRedisTrait;

    public array $loginUserFields = ['id','account','channel_id','nickname','device_system','phone_number','promotion_code','avatar','sex','status','gold','balance','long_vedio_times','area_number'];

    public array $loginRules = [
        'type' => 'required|integer|between:1,2',
        'did' => 'required|string|max:64',
        'dev' => 'required',
        'env' => 'required',
        'name' => 'nullable|string',
        'clipboard' => 'nullable|string',
        'test' => 'nullable|boolean',
    ];

    public array $createNickNames = [
        '孤独患者',
        '会飞的贼',
        '笨比熊',
        '花无邪',
        '一莺时',
        '长安忆',
        '放开那萌比',
        '乏味万千',
        '无恶不赦',
        '池鱼',
        '枯心人',
        '姑娘久等了',
        '何为美人',
        '善解人衣',
        '桃花扇',
        '勾勒相思',
        '酒自斟',
        '在水一方',
        '少女的医生',
        '琉璃心',
        '曾何时',
        '暴君与猫',
        '星星曲奇',
        '欲望作祟',
        '陪你单身',
    ];

    public function generateChatUrl(Array $user): array
    {
        $queryParam = http_build_query([
            'account' => $user['account'],
            'nickname' => $user['nickname'],
            'id' => $user['id']
        ]);
        $user['kf_url'] = 'https://vm.homeleasyn.com/1mjzi3sh74yrj19xqt1ofbv1ff?'.$queryParam;
        return $user;
    }

    public function getDeviceSystem($deviceInfo): int
    {
        $test_ds = 0;
        if(strpos($deviceInfo.'', 'androidId')){
            $test_ds = 2;
        }else if(strpos($deviceInfo.'', 'ios')){
            $test_ds = 1;
        }
        return $test_ds;
    }

    public function getDidFromDb($did,$loginRedis)
    {
        if(!$loginRedis->setnx('lock_login_did',1)){
            return response()->json(['state' => -1, 'msg' => '服务器繁忙请稍候重试']);
        }
        /*$ids = Live::query()->where('status',1)->pluck('id')->all();
        $this->redis()->sAddArray('fakeLiveIdsCollection',$ids);*/
        $didArr = User::query()->pluck('did')->all();
        Log::info('==BindChannelUserClipboard==',[count($didArr)]);
        $loginRedis->sAddArray('account_did',$didArr);
        $loginRedis->del('lock_login_did');
        return User::query()->where('did',$did)->exists();
    }

    public function bindChannel($loginInfo)
    {
        //绑定渠道推广
        $device_system = $loginInfo['device_system'];
        $channel_id = 0;
        $clipboard = $loginInfo['clipboard'] ?? '';
        //$redis = $this->redis('channel');
        if(!empty($clipboard)){
            $channel_id = $this->getChannelIdByPromotionCode($clipboard);
            //Log::info('==BindChannelUserClipboard==',[$clipboard,$channel_id]);
        }
        /*else{
            $hashKey = 'download:'.$loginInfo['ip'];
            if($redis->exists($hashKey)){
                $channel_id = $redis->hGet($hashKey,'channel_id');
                $pid = 0;
            }

        }*/
        //Log::info('==BindChannelUser==',$updateData);
        return [
            'pid'=>$pid ?? 0,
            'channel_id'=>$channel_id ?? 0,
            'device_system'=>$device_system ?? 0,
            'channel_pid'=>$this->getChannelInfoById($channel_id)->pid ?? 0
        ];
    }

}