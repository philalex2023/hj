<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\PayLog;
use App\Services\Pay;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\SDTrait;
use App\TraitClass\PayTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Psr\SimpleCache\InvalidArgumentException;
use App\TraitClass\IpTrait;

/**
 * 艾希支付
 * Class AXController
 * @package App\Http\Controllers\Api
 */
class YYController extends PayBaseController implements Pay
{
    use PayTrait;
    use ApiParamsTrait;
    use IpTrait;

    private string $flag = 'YY';

    /**
     * 艾希支付动作
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function pay(Request $request): JsonResponse
    {

        $prePayData = $this->prepay($request,$this->flag);
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
                'pay_memberid' => $mercId,               //商户号
                'pay_orderid' => strval($orderInfo->number),           //订单号，值允许英文数字
                'pay_amount' => intval($orderInfo->amount ?? 0),              //订单金额,单位元保留两位小数
                'pay_applydate' => date('Y-m-d H:i:s'),
                'pay_bankcode' => $channelNo,            //支付通道编码
                'pay_notifyurl' => $notifyUrl,              //异步返回地址
                'pay_callbackurl' => 'https://dl.yinlian66.com',     //同步返回地址
                'is_bank' => 2,
            ];
            //生成签名 请求参数按照Ascii编码排序
            //私钥签名
            $input['pay_md5sign'] = $this->sign($input, $secret);
            $input['client_ip'] = $this->getRealIp();
            Log::info($this->flag.'_third_params===', [$input]);//三方参数日志
            $curl = (new Client([
              //  'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'verify' => false,
            ]))->post($payUrl, ['form_params' => $input]);

            $response = $curl->getBody();
             Log::info($this->flag.'_third_response===', [$response]);//三方响应日志
            $resJson = json_decode($response, true);
            if ($resJson['code'] == 0) {
                $this->pullPayEvent($orderInfo);
                $return = $this->format(0, ['url' => $resJson['data']['qrCode']], '取出成功');
            } else {
                $return = $this->format($resJson['code'], [], $response);
            }
        } catch (Exception | InvalidArgumentException $e) {
            $return = $this->format($e->getCode(), [], $e->getMessage());
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
        Log::info($this->flag.'_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv[$this->flag]['secret'];
            $signPass = $this->verify($postResp, $secret, $postResp['sign']);
            if (!$signPass) {
                // 签名验证不通过
                throw new Exception('签名验证不通过', -1);
            }
            if ($postResp['returncode'] == '00') {
                // 记录支付信息
                DB::beginTransaction();
                $this->orderUpdate($postResp['pay_orderid'], $postResp);
                DB::commit();
            }
            $return = 'OK';
            Log::info($this->flag.'_pay_callback===', ['OK']);
        } catch (Exception $e) {
            Log::info($this->flag.'_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
            $return = 'NO';
        }
        return response($return);
    }

    public function method(Request $request): mixed
    {
        // TODO: Implement method() method.
        return '';
    }

    /**
     * 签名算法
     * @param $data
     * @param $md5Key
     * @return string
     */
    function sign($data, $md5Key): string
    {
        $native = $data;
        ksort($native);
        $md5str = "";
        foreach ($native as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        return strtoupper(md5($md5str . "key=" . $md5Key));
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
        unset($data['sign']);
        $returnArray = $data;
        $sign = $this->sign($returnArray,$md5Key);
        if ($sign == $pubKey) {
            return true;
        }
        return false;
    }
}