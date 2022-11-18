<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberCard;
use App\Models\Order;
use App\Models\PayLog;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\ChannelTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PayTrait;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends PayBaseController
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

        if(!Cache::lock('createOrder_'.$user->id,5)->get()){
            Log::debug('==order_create=',['ID为:'.$user->id.'的用户在重复拉起订单']);//参数日志
            return response()->json(['state' => -1, 'msg' => '当前用户较多,请稍候重试']);
        }
        //一小时10次拉起未付的用户,一小时后才能发起订单 todo
        $redis = $this->redis();
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
        Log::info('order_create_get_lock_params===',[$params]);//参数日志
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
            $number = self::getPayNumber($user->id);

            $channelInfo = $user->channel_id>0 ? $this->getChannelInfoById($user->channel_id) : null;
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
//                'pay_channel_code' => $payNumber, //
//                'pay_method' => $payMethod, //
                'device_system' => $user->device_system, //
                'channel_name' => !$channelInfo ? '官方' : $channelInfo->name, //

                'channel_principal' => $channelInfo->principal??'', //
                'reg_at' => $user->created_at??'', //
                'user_type' => time()-strtotime($user->created_at)>=24*3600 ? 1 : 0
            ];
            Log::info('order_create_Data===',[$createData]);//参数日志

            // 创建订单
            $order = Order::query()->create($createData);

            //
            $redis->zAdd($unpaidKey,time(),$number);
            $redis->expire($unpaidKey,3600);
//            $return = $this->format(0, ['pay_id' => $pay->id,'order_id'=>$order->id], '取出成功');
            $return = $this->format(0, ['pay_id' => $order->id,'order_id'=>$order->id], '取出成功');
        } catch (Exception $e) {
            DB::rollBack();
            $return = $this->format((int)$e->getCode(), [], $e->getMessage());
        }

        return response()->json($return);
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