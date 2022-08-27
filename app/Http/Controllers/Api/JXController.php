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
class JXController extends PayBaseController implements Pay
{
    use PayTrait;
    use ApiParamsTrait;
    use IpTrait;

    /**
     * 艾希支付动作
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function pay(Request $request): JsonResponse
    {

        // TODO: Implement pay() method.
        $params = self::parse($request->params ?? '');
        Validator::make($params, [
            'pay_id' => 'required|string',
            'type' => [
                'required',
                'string',
                Rule::in(['1', '2']),
            ],
        ])->validate();
        Log::info('ax_pay_params===', [$params]);//参数日志
        // 强制转换
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv['AX']['secret'];

            $payInfo = PayLog::query()->find($params['pay_id']);
            if (!$payInfo) {
                throw new Exception("记录不存在");
            }

            $orderInfo = Order::query()->find($payInfo['order_id']);
            if (!$orderInfo) {
                throw new Exception("订单不存在");
            }

            $channelNo = $params['type'];
            if (in_array($params['type'], ['1', '2'])) {
                $channelNo = $this->getOwnMethod($orderInfo->type, $orderInfo->type_id, $params['type']);
            }

            $mercId = $payEnv['AX']['merchant_id'];
            $notifyUrl = 'https://' .$_SERVER['HTTP_HOST'] . $payEnv['AX']['notify_url'];
            $input = [
                'pay_memberid' => $mercId,               //商户号
                'pay_orderid' => strval($payInfo->number),           //订单号，值允许英文数字
                'pay_amount' => intval($orderInfo->amount ?? 0),              //订单金额,单位元保留两位小数
                'pay_applydate' => date('Y-m-d H:i:s'),
                'pay_bankcode' => $channelNo,            //支付通道编码
                'pay_notifyurl' => $notifyUrl,              //异步返回地址
                'pay_callbackurl' => 'https://dl.yinlian66.com',     //同步返回地址
                'pay_attach' => 'saol订单',           //订单号，值允许英文数字
                'pay_productname' => $orderInfo->id,              //订单金额,单位元保留两位小数
            ];
            //生成签名 请求参数按照Ascii编码排序
            //私钥签名
            $input['pay_md5sign'] = $this->sign($input, $secret);
            Log::info('ax_third_params===', [$input]);//三方参数日志
            $curl = (new Client([
              //  'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'verify' => false,
            ]))->post($payEnv['AX']['pay_url'], ['form_params' => $input]);

            $response = $curl->getBody();
            // Log::info('ax_third_response===', [$response]);//三方响应日志
            $resJson = json_decode($response, true);
            if ($resJson['status'] == 'success') {
                $return = $this->format(0, ['url' => $resJson['url']], '取出成功');
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
        Log::info('ax_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv['AX']['secret'];
            $signPass = $this->verify($postResp, $secret, $postResp['sign']);
            if (!$signPass) {
                // 签名验证不通过
                throw new Exception('签名验证不通过', -1);
            }
            if ($postResp['returncode'] == '00') {
                // 记录支付信息
                DB::beginTransaction();
                $this->orderUpdate($postResp['orderid'], $postResp);
                DB::commit();
            }
            $return = 'ok';
        } catch (Exception $e) {
            Log::info('ax_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
            $return = 'no';
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
        $native = array(
            "pay_memberid" => $data['pay_memberid'],
            "pay_orderid" => $data['pay_orderid'],
            "pay_amount" => $data['pay_amount'],
            "pay_applydate" => $data['pay_applydate'],
            "pay_bankcode" => $data['pay_bankcode'],
            "pay_notifyurl" => $data['pay_notifyurl'],
            "pay_callbackurl" => $data['pay_callbackurl'],
        );
        ksort($native);
        $md5str = "";
        foreach ($native as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5Key));
        return $sign;
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
        $returnArray = array( // 返回字段
            "memberid" => $data["memberid"], // 商户ID
            "orderid" =>  $data["orderid"], // 订单号
            "amount" =>  $data["amount"], // 交易金额
            "datetime" =>  $data["datetime"], // 交易时间
            "transaction_id" =>  $data["transaction_id"], // 支付流水号
            "returncode" => $data["returncode"],
        );
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5Key));
        if ($sign == $pubKey) {
            return true;
        }
        return false;
    }
}