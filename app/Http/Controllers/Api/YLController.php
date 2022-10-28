<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\PayLog;
use App\Services\Pay;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PayTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Psr\SimpleCache\InvalidArgumentException;
use App\TraitClass\IpTrait;

/**
 * 通达支付
 * Class AXController
 * @package App\Http\Controllers\Api
 */
class YLController extends PayBaseController implements Pay
{
    use PayTrait;
    use ApiParamsTrait;
    use IpTrait;

    public string $payFlag = 'YL';

    /**
     * 支付动作
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function pay(Request $request): JsonResponse
    {

        // TODO: Implement pay() method.
        $prePayData = $this->prepay($request,$this->payFlag);
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
        Log::info($this->payFlag.'_third_params===', [$input]);//三方参数日志

        $input['sign'] = $this->sign($input, $secret);

        $curl = (new Client([
            'verify' => false,
        ]))->post($payUrl, ['form_params' => $input]);

        $response = $curl->getBody();
        Log::info($this->payFlag.'_third_response===', [$response]);//三方响应日志

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

    /**
     * 订单回调
     * @param Request $request
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function callback(Request $request): mixed
    {
        // TODO: Implement callback() method.
        $postResp = $request->post();
        $ip = $this->getRealIp();
        Log::info($this->payFlag.'_pay_callback===', [$ip,$postResp]);
        $return = 'FAILED';
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv[$this->payFlag]['secret'];
            $postResp['amount'] = $postResp['amount'] ?? $postResp['orderAmount'];
            $postResp['merchId'] = $postResp['merchId'] ?? '';
            $signPass = $this->verify($postResp, $secret, $postResp['sign']);
            if (!$signPass) {
                // 签名验证不通过
                throw new Exception('签名验证不通过', -1);
            }else{
                if($postResp['status']!='02'){
                    return response($return);
                }
                // 记录支付信息
                DB::beginTransaction();
                $this->orderUpdate($postResp['orderId'], $postResp);
                DB::commit();
            }

            $return = 'SUCCESS';
        } catch (Exception $e) {
            Order::query()->where('number',$postResp['orderId'])->update(['status'=>3]);
            Log::info($this->payFlag.'_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
        }
        Log::info($this->payFlag.'_pay_callback_res===', [$return]);
        return response($return);
    }

    public function method(Request $request): mixed
    {
        // TODO: Implement method() method.
        return '';
    }

    function sign($data, $md5Key): string
    {
        /*$native = $data;
        ksort($native);
        $md5str = "";
        foreach ($native as $key => $val) {
            if($key!='sign'){
                $md5str = $md5str . $key . "=" . $val . "&";
            }
        }*/
        //Log::debug('==callbackIp==',[$this->getRealIp()]);
        $md5str = $data['orderId'].$data['amount'].$data['merchId'];
//        Log::debug('==signAble==',[$md5str . $md5Key]);
//        Log::debug('==signAbleRes==',[md5($md5str . $md5Key)]);
        return md5($md5str . $md5Key);
    }
    /**
     * 验签
     * @param $data
     * @param $md5Key
     * @param $pubKey
     * @return bool
     */
    function verify($data, $md5Key, $pubKey): bool
    {
        $sign = md5($data['orderId'].$data['respCode'].$md5Key);
        if ($sign == $pubKey) {
            return true;
        }
        return false;
    }
}