<?php

namespace App\TraitClass;


use App\ExtendClass\CacheUser;
use App\Jobs\ProcessMemberCard;
use App\Jobs\ProcessStatisticsChannelByDay;
use App\Models\Gold;
use App\Models\MemberCard;
use App\Models\Order;
use App\Models\PayLog;
use App\Models\Recharge;
use App\Models\RechargeChannel;
use App\Models\RechargeChannels;
use App\Models\User;
use App\Models\Video;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Psr\SimpleCache\InvalidArgumentException;

trait PayTrait
{
    use ChannelTrait,CacheTableTrait;

    public function getOrderStatus(): array
    {
        return [
            ''=>[
                'id'=>'',
                'name'=>'全部'
            ],
            0=>[
                'id'=>0,
                'name'=>'未付'
            ],
            1=>[
                'id'=>1,
                'name'=>'成功'
            ],
            2=>[
                'id'=>2,
                'name'=>'未拉起'
            ],
            3=>[
                'id'=>3,
                'name'=>'未成功'
            ],
        ];
    }

    public static function getPayTypeCode()
    {
        $appConfig = config_cache('app');
        $payChannel = $appConfig['pay_channel_codes']??'';
        foreach (explode(',',$payChannel) as $v) {
            $data[$v] = [
                'id' => $v,
                'name' => $v,
            ];
        }
        return $data;
    }

    public static function getPayMethod()
    {
        $payChannel = env('PAY_CHANNEL','');
        foreach (explode(',',$payChannel) as $v) {
            $data[$v] = [
                'id' => $v,
                'name' => $v,
            ];
        }
        return $data;
    }

    public static function getAtionPayCode()
    {
        $payChannel = env('PAY_CHANNEL','');
        foreach (explode(',',$payChannel) as $v) {
            $data[$v] = [
                'id' => $v,
                'name' => $v,
            ];
        }
        return $data;
    }

    public function getAllPayChannel(){
        $data[] = ['id'=>'0','name'=>'全部'];
        $raw = RechargeChannel::where('status',1)->get();
        foreach ($raw as $v) {
            $data[] = ['id'=>$v->id,'name'=>$v->remark];
        }
        return $data;
    }
    /**
     * 返回支付类型标识
     * @param string $flag
     * @return string
     */
    public static function getPayType($flag=''): string
    {
        $payTypes = [
            'DBS' => '1',
        ];
        return $payTypes[$flag]??'0';
    }

    /**
     * 生成订单号
     * @return string
     */
    public static function getPayNumber($uid): string
    {
//        return 'JB'.time().rand(10000,99999);
        return 'HJ'.date('His').$uid;
    }

    /**
     * vip信息表
     * @param $cardId
     * @return Builder|Builder[]|Collection|Model|null
     */
    private function getVipInfo($cardId): Model|Collection|Builder|array|null
    {
        return MemberCard::query()->find($cardId)?->toArray();
    }

    /**
     * gold信息表
     * @param $Id
     * @return Builder|Builder[]|Collection|Model|null
     */
    private function getGoldInfo($Id): Model|Collection|Builder|array|null
    {
        return Gold::query()->find($Id)?->toArray();
    }

    /**
     * 视频信息
     * @param $goodsId
     * @return Model|Collection|Builder|array|null
     */
    private function getGoodsInfo($goodsId): Model|Collection|Builder|array|null
    {
        return Video::query()->find($goodsId)?->toArray();
    }

    /**
     * 处理金币购买
     * @param $id
     * @param $uid
     * @return Model|Collection|Builder|array|null
     */
    private function buyGold($id,$uid): Model|Collection|Builder|array|null
    {
        $info = Gold::query()->find($id)?->toArray();
        $proportion = round($info['proportion'],2);
        $bonus = $info['bonus'];
        $info['money'] = $info['money'] * $proportion;
        $info['money'] += $bonus;
        User::query()->find($uid)->update(
            [
                'gold' =>DB::raw("gold + {$info['money']}"),
                'movie_ticket' =>DB::raw("movie_ticket + {$info['tickets']}"),
            ]
        );

        Cache::forget("cachedUser.".$uid);
        Log::info('pay_gold_update===', ['用户'.$uid.'新增金额:'.$info['money'].',金币第'.$id.'档-比例:'.$proportion.',赠送金币:'.$bonus.',增加赠送观影券:'.$info['tickets']]);
        return [];
    }

    private function pullPayEvent($prePayData): void
    {
        $orderInfo = $prePayData['order_info'];
        //首页统计拉起
        $redis = Redis::connection()->client();
        $dayData = date('Ymd');
        $nowTime = time();
        $redis->zAdd('day_pull_order_'.$dayData,$nowTime,$orderInfo->id);
        $redis->expire('day_pull_order_'.$dayData,3600*24*7);

    }

    /**
     * 处理vip购买
     * @param $goodsId
     * @param $uid
     * @return Model|Collection|Builder|array|null
     */
    private function buyVip($goodsId,$uid): Model|Collection|Builder|array|null
    {
        $cardInfo = MemberCard::query()->find($goodsId);
        if($cardInfo->expired_hours > 0) {
            $expiredTime = $cardInfo->expired_hours * 3600 + time();
            $expiredAt = date('Y-m-d H:i:s',$expiredTime);
        }
        $user = User::query()->findOrFail($uid);
        $member_card_type = !empty($user->member_card_type) ? (array)$user->member_card_type : [];
        $member_card_type[] = $cardInfo->id;
        $vip = max($member_card_type);
        $updateMember = implode(',',$member_card_type);

        $vipExpired = MemberCard::query()->select(DB::raw('SUM(IF(expired_hours>0,expired_hours,10*365*24)) as expired_hours'))->whereIn('id',$member_card_type)->value('expired_hours') *3600;
        $r = User::query()->where('id',$uid)->update([
            'movie_ticket' =>DB::raw("movie_ticket + {$cardInfo->tickets}"),
            'member_card_type' => $updateMember,
            'vip'=>$vip,
            'vip_start_last' => time(), // 最后vip开通时间
            'vip_expired' => $vipExpired
        ]);

        Log::info('pay_vip_update===', [[$user->id,$user->member_card_type],[
            'member_card_type' => $updateMember,
            'vip'=>$vip,
            'vip_start_last' => time(), // 最后vip开通时间
            'vip_expired' => $vipExpired,
            'tickets' => $cardInfo->tickets,
        ],$r]);//vip更新日志
        //队列执行
        /*if($cardInfo->expired_hours >= 0) {
            $job = new ProcessMemberCard($user->id,$cardInfo->id,($cardInfo->expired_hours?:10*365*34)*60*60);
            app(Dispatcher::class)->dispatchNow($job);
        }*/
        Cache::forget("cachedUser.".$user->id);
        return [
            'expired_at' => $expiredAt??false
        ];
    }

    public function reqPostPayUrl($url,$params,$headers=[],$isProxy=false)
    {
        $clientParams = [
            'verify' => false,
        ];
//        $isProxy && $clientParams['proxy'] = ['https'  => 'tcp://www.runoob.com:80'];
//        $clientParams['proxy'] = ['https'  => 'tcp://www.runoob.com:80'];
        !empty($headers) && $clientParams['headers'] = $headers;
        $curl = (new Client($clientParams))->post($url, $params);
        return $curl->getBody();
    }

    /**
     * 订单更新
     * @param $tradeNo
     * @param array $jsonResp
     * @param $userInfo
     * @throws Exception
     */
    private function orderUpdate($tradeNo,$jsonResp = []): void
    {
        if(!Cache::lock('payCallback_'.$tradeNo,5)->get()){
            Log::debug('==payCallbackOrderUpdate=',['订单:'.$tradeNo.'重复在回调']);//参数日志
            exit('failed');
        }
        $nowData = date('Y-m-d H:i:s');
        $orderModel = DB::connection('master_mysql')->table('orders')->where('number',$tradeNo);
        $orderInfo = $orderModel->first();
        !$orderInfo && exit('failed');
        $orderInfo->status == 1 && exit('success');
        !isset($orderInfo->type) && exit('failed');
        $orderModel->where('id',$orderInfo->id)->update([
            'status' => 1,
            'updated_at' => $nowData,
        ]);
        $method = match ($orderInfo->type) {
            1 => 'buyVip',
            2 => 'buyGold',
        };
        $this->$method($orderInfo->type_id??0,$orderInfo->uid);
        $userType = $orderInfo->user_type;
        //########渠道CPS日统计########
        ProcessStatisticsChannelByDay::dispatchAfterResponse($orderInfo,$userType);
        //#############################

        //
        $redis = $this->redis();
        $unpaidKey = 'unpaid_user_'.$orderInfo->uid;
        $redis->zRem($unpaidKey,$orderInfo->number);

    }

    /**
     * 得到支付信息
     * @return array
     * @throws InvalidArgumentException
     */
    public static function getPayEnv(): array
    {
        $cacheData = self::rechargeChannelCache();
        return array_column($cacheData->toArray(),null,'name');
    }

    public function getPayChannels(): array
    {
        return RechargeChannel::query()->pluck('remark','id')->all();
    }
}