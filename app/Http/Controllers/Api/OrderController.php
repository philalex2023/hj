<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RechargeChannels;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\ChannelTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PayTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use ApiParamsTrait,MemberCardTrait,PayTrait,ChannelTrait;

    /**
     * 订单创建接口
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();

        /*if(!Cache::lock('createOrder_'.$user->id,5)->get()){
            Log::debug('==order_create=',['ID为:'.$user->id.'的用户在重复拉起订单']);//参数日志
            return response()->json(['state' => -1, 'msg' => '当前用户较多,请稍候重试']);
        }*/
        //一小时10次拉起未付的用户,一小时后才能发起订单
        $redis = $this->redis('login');
        $unpaidKey = 'unpaid_user_'.$user->id;
        $hourAgo = strtotime('-1 hour');
        $nowTime = time();
        $userUnpaidOrders = $redis->zCount($unpaidKey,$hourAgo,$nowTime);
        if($userUnpaidOrders && $userUnpaidOrders>=10){
            Log::debug('==order_reply_create=',['ID为:'.$user->id.'的恶意用户在重复拉起订单']);//参数日志
            return response()->json(['state' => -1, 'msg' => '一定时间受限']);
        }

        $params = self::parse($request->params ?? '');
        Validator::make($params, [
            'type' => [
                'required',
                'string',
                Rule::in(['1', '2','3']),
            ],
            'goods_id' => 'required|string',
            'method_id' => [
                'nullable',
                'string',
                Rule::in(['1', '2']),
            ],
            'forward' => 'nullable',
            'vid' => 'nullable',
            'time' => 'required|string',
            'pay_method' => [
                'nullable',
               // 'integer',
               // Rule::in([1, 2,3]),
            ],
        ])->validate();
//        Log::info('order_create_get_lock_params===',[$params]);//参数日志
        $goodsMethod = match ($params['type']) {
            '1' => 'getVipInfo',
            '2' => 'getGoldInfo',
            '3' => 'getGoodsInfo',
        };
        $goodsInfo = $this->$goodsMethod($params['goods_id']);
        $now = date('Y-m-d H:i:s', time());

        if($goodsMethod == 'getGoldInfo'){
            if($goodsInfo['user_type']==1 && ($this->getVipValue($user))==0){
                return response()->json($this->format(-1, [], '无vip权限'));
            }
        }
        //是否有效打折
        $useRealValue = false;
        $realValue = $goodsInfo['real_value'] ?? 0;
        if($realValue > 0){
            $validPeriodTime = strtotime($user->created_at) + $goodsInfo['hours']*3600;
            if($validPeriodTime > time()){
                $useRealValue = true;
            }
            Log::info('===memberCard_diff_time==',[$user->created_at,$goodsInfo['hours']*3600,$validPeriodTime,time(),$useRealValue]);
        }
        $amount = $goodsInfo[match ($params['type']) {
            '1' => $useRealValue ? 'real_value' : 'value',
            '2' => 'money',
            '3' => 'gold',
        }];

        try {
            $number = self::getPayNumber($user->id); //订单号
            $method_id = (int)$params['method_id'];
            $channelInfo = $user->channel_id>0 ? $this->getChannelInfoById($user->channel_id) : null;
            $payEnvInfo = $this->getRechargeChannelByWeight($method_id,$amount);
            if(!$payEnvInfo){
                $return = $this->format(-1, [], '无可用充值渠道');
                return response()->json($return);
            }

            $channelNo = match ($method_id){
                1 => $payEnvInfo['zfb_code'],
                2 => $payEnvInfo['wx_code'],
            };
            $createData = [
                'remark' => json_encode(['id'=>$goodsInfo['id']??0,'name'=>$goodsInfo['name']??'']),
                'number' => $number,
                'type' => $params['type'],
                'type_id' => $params['goods_id'],
                'amount' => $amount,
                'uid' => $user->id,
                'channel_id' => $user->channel_id??0,
                'channel_pid' => $user->channel_pid??0,
                'status' => 0,
                'forward' => $params['forward'] ?? '',
                'vid' => $params['vid'] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
                'device_system' => $user->device_system, //
                'channel_name' => !$channelInfo ? '官方' : $channelInfo->name, //

                'pay_channel_code'=>$channelNo,
                'pay_method'=>$payEnvInfo['id'],

                'channel_principal' => $channelInfo->principal??'', //
                'reg_at' => $user->created_at??'', //
                'user_type' => $user->created_at < date('Y-m-d'.' 00:00:00') ? 1 : 0
            ];
//            Log::info('order_create_Data===',[$createData]);//参数日志

            // 创建订单
            $order = Order::query()->create($createData);

            //
            $redis->zAdd($unpaidKey,time(),$number);
            $redis->expire($unpaidKey,3600);
            $return = $this->format(0, ['pay_id' => $order->id,'order_id'=>$order->id], '取出成功');
        } catch (Exception $e) {
            DB::rollBack();
            $return = $this->format((int)$e->getCode(), [], $e->getMessage());
        }

        return response()->json($return);
    }

    public function getRechargeChannelByWeight($payChannelType,$amount)
    {
        $recharge_channels = $this->getRechargeChannelsByCache($payChannelType,$amount);
        $weight = 0;
        $channelIds = [];
        foreach ($recharge_channels as $pay_channel => $weights){
            $weight += $weights;
            for ($i=0;$i < $weights; ++$i){
                $channelIds[] = $pay_channel;
            }
        }
        $use = rand(0, $weight -1);
        if(isset($channelIds[$use])){
            $channelId = $channelIds[$use];
            $rechargeChannelSelector = $this->getRechargeChannelSelector();
            if(!empty($rechargeChannelSelector)){
                return $rechargeChannelSelector[$channelId];
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getRechargeChannelsByCache($payChannelType,$amount)
    {
        $key = 'recharge_channels_Z_'.$payChannelType.'_'.$amount;
        $redis = $this->redis();
        $cacheData = $redis->zRange($key,0,-1,true);
        if(!$cacheData){
            $items = RechargeChannels::query()->where('status',1)->where('pay_type',$payChannelType)->get(['pay_channel','weights','match_amount']);
            $zData = [];
            $amountIdArr = array_column($this->getRechargeAmountColums(),'id','name');
            foreach ($items as $item){
                if(!empty($item->match_amount)){
                    $amountArr = (array)json_decode($item->match_amount,true);
                    $amountArr = array_flip($amountArr);
                    $amountIndex = $amountIdArr[$amount];
                    if(isset($amountArr[$amountIndex])){
                        $zData[$item->pay_channel] = $item->weights;
                        $redis->zAdd($key,$item->weights,$item->pay_channel);
                        $redis->expire($key,3600);
                    }
                }
            }
            return $zData;
        }
        return $cacheData;
    }

    private function getRechargeChannelSelector(): array
    {
        $cacheData = self::rechargeChannelCache();
        return array_column($cacheData->toArray(),null,'id');
    }

    /**
     * 订单查询接口
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function query(Request $request): JsonResponse
    {
        $params = self::parse($request->params ?? '');
        Validator::make($params, [
            'order_id' => 'required|string',
        ])->validate();
        try {
            $order = (array) Order::query()->findOrFail($params['order_id']);
            $return = $this->format(0,$order,"取出成功");
            return response()->json($return);
        } catch (Exception $e){
           return $this->returnExceptionContent($e->getMessage());
        }
    }

}