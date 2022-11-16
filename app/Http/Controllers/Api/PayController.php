<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RechargeChannels;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PaySignVerifyTrait;
use App\TraitClass\PayTrait;
use App\TraitClass\IpTrait;
use App\TraitClass\RobotTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * 通达支付
 * Class AXController
 * @package App\Http\Controllers\Api
 */
class PayController extends Controller
{
    use PayTrait,ApiParamsTrait,IpTrait,PaySignVerifyTrait,RobotTrait;

    public function getRechargeChannelsByCache($payChannelType)
    {
        $key = 'recharge_channels_Z_'.$payChannelType;
        $redis = $this->redis();
        $cacheData = $redis->zRange($key,0,-1,true);
        if(!$cacheData){
            $items = RechargeChannels::query()->where('status',1)->where('pay_type',$payChannelType)->get(['pay_channel','weights']);
            $zData = [];
            foreach ($items as $item){
                $zData[$item->pay_channel] = $item->weights;
                $redis->zAdd($key,$item->weights,$item->pay_channel);
            }
            return $zData;
        }
        return $cacheData;
    }

    public function getRechargeChannelByWeight($payChannelType)
    {
        $recharge_channels = $this->getRechargeChannelsByCache($payChannelType);
        $weight = 0;
        $channelIds = array ();
        foreach ($recharge_channels as $pay_channel => $weights){
            $weight += $weights;
            for ($i=0;$i < $weights; ++$i){
                $channelIds[] = $pay_channel;
            }
        }
        $use = rand(0, $weight -1);
        $channelId = $channelIds[$use];
        return $this->getRechargeChannelSelector()[$channelId];
    }

    private function getRechargeChannelSelector(): array
    {
        $cacheData = self::rechargeChannelCache();
        return array_column($cacheData->toArray(),null,'id');
    }

    public function getPayParams($validated)
    {
        $payChannelType = (int)$validated['type'];
        $payEnvInfo = $this->getRechargeChannelByWeight($payChannelType);

        $payName = $payEnvInfo['name'];
        //$orderInfo = Order::query()->find($validated['pay_id']);
        $orderInfo = DB::connection('master_mysql')->table('orders')->find($validated['pay_id']);

        //一小时10次拉起未付
//        $unpaidKey = 'unpaid_user_'.$orderInfo->uid;

        if (!$orderInfo) {
            Log::error($payName.'_pay_exception===', [$validated]);
            $return = $this->format(-1, [], '订单不存在');
            return response()->json($return);
        }

        $secret = $payEnvInfo['secret'];
        $mercId = $payEnvInfo['merchant_id'];
        $notifyUrl = 'https://' .$_SERVER['HTTP_HOST'] . $payEnvInfo['notify_url'];
        $channelNo = match ($payChannelType){
            1 => $payEnvInfo['zfb_code'],
            2 => $payEnvInfo['wx_code'],
        };
        Log::info($payName.'_pay_url===', [$payEnvInfo['pay_url']]);//三方参数日志
        Order::query()->where('id',$orderInfo->id)->update(['pay_channel_code'=>$channelNo,'pay_method'=>$payEnvInfo['id']]);

        return [
            'payName' => $payName,
            'secret' => $secret,
            'order_info' => $orderInfo,
            'notifyUrl' => $notifyUrl,
            'merchId' => $mercId,
            'channelNo' => $channelNo,
            'pay_url' => $payEnvInfo['pay_url'],
            'pay_type' => $payChannelType,
            'pay_method' => $payEnvInfo['id'],
            'ip' => $this->getRealIp(),
        ];
    }

    public function bill(Request $request)
    {
        $params = self::parse($request->params ?? '');
        $validated = Validator::make($params, [
            'pay_id' => 'required|string',
            'type' => [
                'required',
                'string',
                Rule::in(['1', '2']),
            ],
        ])->validated();
        $prePayData = $this->getPayParams($validated);
        $payName = $prePayData['payName'];
        return $this->$payName($prePayData);
    }

    public function YK($prePayData){
        $orderInfo = $prePayData['order_info'];
        $notifyUrl = $prePayData['notifyUrl'];
        $mercId = $prePayData['merchId'];
        $channelNo = $prePayData['channelNo'];
        $secret = $prePayData['secret'];
//        $ip = $prePayData['ip'];
        $payUrl = $prePayData['pay_url'];
        // 强制转换
        try {
            $input = [
                'appId' => $mercId,               //商户号
                'orderNo' => strval($orderInfo->number),           //订单号，值允许英文数字
                'channelNo' => $channelNo,            //支付通道编码
                'amount' => strval($orderInfo->amount ?? 0) . '.00',              //订单金额,单位元保留两位小数
                'notifyCallback' => $notifyUrl,   //异步返回地址
                'payType' => "1",   //固定1
            ];
            //生成签名 请求参数按照Ascii编码排序
            //私钥签名
            $signFun = 'sign'.$prePayData['payName'];
            $input['sign'] = $this->$signFun($input, $secret);
            Log::info($prePayData['payName'].'_third_params===', [$input]);//三方参数日志
            /*$curl = (new Client([
                'headers' => ['Content-Type' => 'application/json'],
                'verify' => false,
            ]))->post($payUrl, [
                'body' => json_encode($input)
            ]);*/
//            $response = $curl->getBody();
            $response = $this->reqPostPayUrl($payUrl, ['body' => json_encode($input)], ['Content-Type' => 'application/json']);
            Log::info('YK_third_response===', [$response]);//三方响应日志
//            Log::info('Yk_order_info===', [$orderInfo]);
            $resJson = json_decode($response, true);
            if ($resJson['code'] == 1) {
                $this->pullPayEvent($prePayData);
                $return = $this->format(0, ['url'=>$resJson['payUrl']], '取出成功');
            } else {
                Order::query()->where('id',$orderInfo->id)->update(['status'=>2]);
                $return = $this->format($resJson['code'], [], $response);
                $this->RobotSendMsg('YK通道异常未拉起');
            }
        } catch (\Exception $e) {
            $return = $this->format($e->getCode(), [], $e->getMessage());
        }
        return response()->json($return);
    }

    public function KF($prePayData)
    {
        $payName = $prePayData['payName'];
        $orderInfo = $prePayData['order_info'];
        $notifyUrl = $prePayData['notifyUrl'];
        $mercId = $prePayData['merchId'];
        $channelNo = $prePayData['channelNo'];
        $secret = $prePayData['secret'];
//        $ip = $prePayData['ip'];
        $payUrl = $prePayData['pay_url'];
        $input = [
            'service' => $channelNo,            //通道类型
            'version' => '1.0',            //版本号
            'charset' => 'UTF-8',            //字符集
            'sign_type' => 'MD5',            //签名类型
            'merchant_id' => $mercId,               //商户号
            'out_trade_no' => strval($orderInfo->number),           //订单号，值允许英文数字
            'goods_desc' => '网络购物',              //商品描述
            'total_amount' => round($orderInfo->amount ?? 0,2),              //支持小数点后两位，比如9.99
            'notify_url' => $notifyUrl,              //后台异步通知 (回调) 地址
            'nonce_str' => Str::random(8),            //随机字符串,不长于32位
        ];
        //生成签名
        $signFun = 'sign'.$prePayData['payName'];
        $input['sign'] = $this->$signFun($input, $secret);

        $response = $this->reqPostPayUrl($payUrl, ['form_params' => $input]);
        Log::info($payName.'_pull_req', [$input]);//拉起请求三方日志
        Log::info($payName.'_third_response', [$response]);//三方响应日志
//        exit();
        $resJson = json_decode($response, true);
        if($resJson['status']==0){
            $this->pullPayEvent($prePayData);
            $return = $this->format($resJson['result_code'], ['url' => $resJson['pay_info']??''], $resJson['message']??'');
        }else{
            $return = $this->format($resJson['result_code'], $resJson, $resJson['err_msg']??'');
        }
        return response()->json($return);
    }

    public function YL($prePayData){
        $payName = $prePayData['payName'];
        $orderInfo = $prePayData['order_info'];
        $notifyUrl = $prePayData['notifyUrl'];
        $mercId = $prePayData['merchId'];
        $channelNo = $prePayData['channelNo'];
        $secret = $prePayData['secret'];
        $ip = $prePayData['ip'];
        $payUrl = $prePayData['pay_url'];
        $input = [
            'orderId' => strval($orderInfo->number),           //订单号，值允许英文数字
            'amount' => intval($orderInfo->amount ?? 0)*100,              //订单金额,单位分
            'notifyUrl' => $notifyUrl,              //后台异步通知 (回调) 地址
            'frontUrl' => 'https://sina.com',              //支付完成，页面跳转地址
            'merchId' => $mercId,               //商户号
            'transType' => $channelNo,            //通道类型
            'fromIp' => $ip,               //下单用户ip
        ];
        //生成签名 请求参数按照Ascii编码排序
        //MD5 签名: HEX 大写, 32 字节。
        Log::info($payName.'_third_params===', [$input]);//三方参数日志

        $signFun = 'sign'.$prePayData['payName'];
        $input['sign'] = $this->$signFun($input, $secret);

        /*$curl = (new Client([
            'verify' => false,
        ]))->post($payUrl, ['form_params' => $input]);
        $response = $curl->getBody();*/
        $response = $this->reqPostPayUrl($payUrl, ['form_params' => $input]);
        Log::info($payName.'_third_response', [$response]);//三方响应日志

        //返回H5页面方式
        /*$key = 'pay_'.$request->user()->id;
        $redis = $this->redis();
        $redis->set($key,(string)$response);
        $redis->expire($key,600);
        return response()->json($this->format(0, ['url' => 'https://' .$_SERVER['HTTP_HOST'] .'/pay/'.$key], 'ok'));*/
        //返回json方式
        $resJson = json_decode($response, true);
        if ($resJson['code']=='0100') {
            $this->pullPayEvent($prePayData);
            $url = $resJson['data']['html'];
            $return = $this->format(0, ['url' => $url], 'ok');
        } else {
            Order::query()->where('id',$orderInfo->id)->update(['status'=>2]);
            $return = $this->format(-1, $resJson, $resJson['message']??'');
            $this->RobotSendMsg($payName.'通道异常未拉起');
        }
        return response()->json($return);
    }

    public function AX($prePayData)
    {
        $orderInfo = $prePayData['order_info'];
        $notifyUrl = $prePayData['notifyUrl'];
        $mercId = $prePayData['merchId'];
        $channelNo = $prePayData['channelNo'];
        $secret = $prePayData['secret'];
        $ip = $prePayData['ip'];
        $payUrl = $prePayData['pay_url'];
        // 强制转换
        try {
            $input = [
                'fxid' => $mercId,               //商户号
                'fxddh' => strval($orderInfo->number),           //订单号，值允许英文数字
                'fxdesc' => $orderInfo->id,           //商品名称
                'fxfee' => intval($orderInfo->amount ?? 0),              //订单金额,单位元保留两位小数
                'fxnotifyurl' => $notifyUrl,              //异步返回地址
                'fxbackurl' => 'https://dl.yinlian66.com',     //同步返回地址
                'fxpay' => $channelNo,     //支付通道编码
                'fxnotifystyle' => 2,     //异步数据类型
                'fxip' => $ip,
            ];
            //生成签名 请求参数按照Ascii编码排序
            //私钥签名
            $signFun = 'sign'.$prePayData['payName'];
            $input['fxsign'] = $this->$signFun($input, $secret);
            Log::info('ax_third_params===', [$input]);//三方参数日志
            /*$curl = (new Client([
                //  'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'verify' => false,
            ]))->post($payUrl, ['form_params' => $input]);

            $response = $curl->getBody();*/
            $response = $this->reqPostPayUrl($payUrl, ['form_params' => $input]);
            Log::info('ax_third_response', [$response]);//三方响应日志
            $resJson = json_decode($response, true);
            Log::info('ax_third_response', [$resJson]);//三方响应日志
            if ($resJson['status'] == 1) {
                $this->pullPayEvent($prePayData);
                $return = $this->format(0, ['url' => $resJson['payurl']], '取出成功');
            } else {
                Order::query()->where('id',$orderInfo->id)->update(['status'=>2]);
                $return = $this->format(-1, [], $resJson['error']);
                $this->RobotSendMsg('AX通道异常未拉起');
            }
        } catch (\Exception $e) {
            $return = $this->format($e->getCode(), [], $e->getMessage());
        }
        return response()->json($return);
    }

}