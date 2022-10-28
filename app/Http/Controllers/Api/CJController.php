<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\PayLog;
use App\Services\Pay;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CJTrait;
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
use App\ExtendClass\Random;
use App\TraitClass\IpTrait;

/**
 * 长江支付
 * Class CJController
 * @package App\Http\Controllers\Api
 */
class CJController extends PayBaseController implements Pay
{
    use PayTrait;
    use ApiParamsTrait;
    use IpTrait;
    use CJTrait;

    /**
     * 长江支付动作
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function pay(Request $request): JsonResponse
    {

        // 强制转换
        try {
            $prePayData = $this->prepay($request,$this->flag);
            $orderInfo = $prePayData['order_info'];
            $notifyUrl = $prePayData['notifyUrl'];
            $mercId = $prePayData['merchId'];
            $channelNo = $prePayData['channelNo'];
            $secret = json_decode($prePayData['secret'],true);
//            $ip = $prePayData['ip'];
            $payUrl = $prePayData['pay_url'];

            $md5Key = $secret['md5_key'];
            $privateKey = $secret['private_key'];
            $input = [
                'merId' => $mercId,               //商户号
                'orderId' => strval($orderInfo->number),           //订单号，值允许英文数字
                'orderAmt' => strval($orderInfo->amount??0),              //订单金额,单位元保留两位小数
                'channel' => $channelNo,            //支付通道编码
                'desc' => '正常充值',           //简单描述，只允许英文数字 最大64
                'attch' => '',             //附加信息,原样返回
                'smstyle' => '1',               //用于扫码模式（sm），仅带sm接口可用，默认0返回扫码图片，为1则返回扫码跳转地址。
                'userId' => '',                 //用于识别用户绑卡信息，仅快捷接口可用。
                'ip' => $this->getRealIp(),          //用户的ip地址必传，风控需要
                'notifyUrl' => $notifyUrl,   //异步返回地址
                'returnUrl' => 'https://dl.yinlian66.com',     //同步返回地址
                'nonceStr' => Random::alnum('32')   //随机字符串不超过32位
            ];
            //生成签名 请求参数按照Ascii编码排序
            //私钥签名
            $input['sign'] = $this->sign($input, $md5Key, $privateKey);

            Log::info('cj_third_params===', [$input]);//三方参数日志
            $curl = (new Client([
                // 'headers' => ['Content-Type' => 'multipart/form-data'],
                'verify' => false,
            ]))->post($payUrl, ['form_params' => $input]);
            $response = $curl->getBody();
            Log::info('cj_third_response===', [$response]);//三方响应日志
            $resJson = json_decode($response, true);
            if ($resJson['code'] == 1) {
                $this->pullPayEvent($orderInfo);
                $return = $this->format(0, ['url'=>$resJson['data']['payurl']], '取出成功');
            } else {
                $return = $this->format($resJson['code'],[], $response);
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
        Log::info('cj_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv()['CJ'];
            $secret = json_decode($payEnv['secret'],true);
            $publicKey = $secret['public_key'];
            $md5Key = $secret['md5_key'];
            $signPass = $this->verify($postResp, $md5Key, $publicKey);
            if (!$signPass) {
                // 签名验证不通过
                throw new Exception('签名验证不通过', -1);
            }
            // 记录支付信息
            DB::beginTransaction();
            $this->orderUpdate($postResp['orderId'], $postResp);
            DB::commit();
            $return = 'success';
        } catch (Exception $e) {
            Log::info('cj_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
            $return = 'failure';
        }
        Log::info('cj_pay_callback_res===', [$return]);
        return response($return);
    }

    public function method(Request $request): mixed
    {
        // TODO: Implement method() method.
        return '';
    }

}