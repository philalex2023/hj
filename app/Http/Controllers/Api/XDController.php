<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayLog;
use App\Services\Pay;
use App\TraitClass\ApiParamsTrait;
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
 * 信达支付
 * Class YKController
 * @package App\Http\Controllers\Api
 */
class XDController extends PayBaseController implements Pay
{
    use PayTrait;
    use ApiParamsTrait;
    use IpTrait;

    /**
     * 信达支付动作
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function pay(Request $request): JsonResponse
    {

        // TODO: Implement pay() method.
        $prePayData = $this->prepay($request,'XD');
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
                'fxid' => $mercId,               //商户号
                'fxddh' => strval($orderInfo->number),           //订单号，值允许英文数字
                'fxdesc' => 'saol订单',           //订单号，值允许英文数字
                'fxfee' => intval($orderInfo->amount ?? 0),              //订单金额,单位元保留两位小数
                'fxattch' => '',              //订单金额,单位元保留两位小数
                'fxnotifyurl' => $notifyUrl,              //异步返回地址
                'fxbackurl' => 'https://dl.yinlian66.com',     //同步返回地址
                'fxpay' => $channelNo,            //支付通道编码
                'fxip' => $this->getRealIp(),            //支付通道编码
            ];
            //生成签名 请求参数按照Ascii编码排序
            //私钥签名
            $input['fxsign'] = $this->sign($input, $secret);
            Log::info('xd_third_params===', [$input]);//三方参数日志
            $curl = (new Client([
                'headers' => ['Content-Type' => 'application/json'],
                'verify' => false,
            ]))->post($payUrl, [
                'body' => json_encode($input)
            ]);
            $response = $curl->getBody();
            // Log::info('xd_third_response===', [$response]);//三方响应日志
            $resJson = json_decode($response, true);
            if ($resJson['status'] == 1) {
                $this->pullPayEvent($orderInfo);
                $return = $this->format(0, ['url' => $resJson['payurl']], '取出成功');
            } else {
                $return = $this->format($resJson['status'], [], $response);
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
        Log::info('xd_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv['XD']['secret'];
            $signPass = $this->verify($postResp, $secret, $postResp['fxsign']);
            if (!$signPass) {
                // 签名验证不通过
                throw new Exception('签名验证不通过', -1);
            }
            if ($postResp['fxstatus'] == 1) {
                // 记录支付信息
                DB::beginTransaction();
                $this->orderUpdate($postResp['fxddh'], $postResp);
                DB::commit();
            }
            $return = 'success';
        } catch (Exception $e) {
            Log::info('xd_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
            $return = 'failure';
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
        //签名数据转换为大写
        return md5($data['fxid'] . $data['fxddh'] . $data['fxfee'] . $data['fxnotifyurl'] . $md5Key);
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
        $sig_data = md5($data['fxstatus'] . $data['fxid'] . $data['fxddh'] . $data['fxfee'] . $md5Key);
        if ($sig_data == $pubKey) {
            return true;
        }
        return false;
    }
}