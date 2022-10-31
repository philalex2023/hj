<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PayCallbackTrait;
use App\TraitClass\PaySignVerifyTrait;
use App\TraitClass\PayTrait;
use App\TraitClass\IpTrait;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * 通达支付
 * Class AXController
 * @package App\Http\Controllers\Api
 */
class PayController extends Controller
{
    use PayTrait,ApiParamsTrait,IpTrait,PaySignVerifyTrait,PayCallbackTrait;

    public function getRechargeChannelByWeight($payChannelType)
    {
        $recharge_channels = DB::table('recharge_channels')->where('status',1)->get(['id','weights','pay_channel','pay_type']);

        $weight = 0;
        $channelIds = array ();
        foreach ($recharge_channels as $one){
            $weight += $one->weights;
            for ($i=0;$i < $one->weights; ++$i){
                $channelIds[] = $one->pay_channel;
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
        $orderInfo = Order::query()->find($validated['pay_id']);
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
        $this->$payName($prePayData);
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
            $curl = (new Client([
                'headers' => ['Content-Type' => 'application/json'],
                'verify' => false,
            ]))->post($payUrl, [
                'body' => json_encode($input)
            ]);
            $response = $curl->getBody();
            // Log::info('yk_third_response===', [$response]);//三方响应日志
            $resJson = json_decode($response, true);
            if ($resJson['code'] == 1) {
                $this->pullPayEvent($orderInfo);
                $return = $this->format(0, ['url'=>$resJson['payUrl']], '取出成功');
            } else {
                $return = $this->format($resJson['code'], [], $response);
            }
        } catch (\Exception $e) {
            $return = $this->format($e->getCode(), [], $e->getMessage());
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

        $curl = (new Client([
            'verify' => false,
        ]))->post($payUrl, ['form_params' => $input]);

        $response = $curl->getBody();
        Log::info($payName.'_third_response===', [$response]);//三方响应日志

        //返回H5页面方式
        /*$key = 'pay_'.$request->user()->id;
        $redis = $this->redis();
        $redis->set($key,(string)$response);
        $redis->expire($key,600);
        return response()->json($this->format(0, ['url' => 'https://' .$_SERVER['HTTP_HOST'] .'/pay/'.$key], 'ok'));*/
        //返回json方式
        $resJson = json_decode($response, true);
        if ($resJson['code']=='0100') {
            $this->pullPayEvent($orderInfo);
            $url = $resJson['data']['html'];
            $return = $this->format(0, ['url' => $url], 'ok');
        } else {
            Order::query()->where('id',$orderInfo->id)->update(['status'=>2]);
            $return = $this->format(-1, $resJson, $resJson['message']??'');
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
            $curl = (new Client([
                //  'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'verify' => false,
            ]))->post($payUrl, ['form_params' => $input]);

            $response = $curl->getBody();
            $resJson = json_decode($response, true);
            Log::info('ax_third_response===', [$resJson]);//三方响应日志
            if ($resJson['status'] == 1) {
                $this->pullPayEvent($orderInfo);
                $return = $this->format(0, ['url' => $resJson['payurl']], '取出成功');
            } else {
                Order::query()->where('id',$orderInfo->id)->update(['status'=>2]);
                $return = $this->format(-1, [], $resJson['error']);
            }
        } catch (\Exception $e) {
            $return = $this->format($e->getCode(), [], $e->getMessage());
        }
        return response()->json($return);
    }

}