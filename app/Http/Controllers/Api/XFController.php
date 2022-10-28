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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Psr\SimpleCache\InvalidArgumentException;
use App\TraitClass\IpTrait;

/**
 * 大发支付
 * Class AXController
 * @package App\Http\Controllers\Api
 */
class XFController extends PayBaseController implements Pay
{
    use PayTrait;
    use ApiParamsTrait;
    use IpTrait;

    public string $payFlag = 'XF';
    /**
     * 支付动作
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function pay(Request $request): JsonResponse
    {

        $prePayData = $this->prepay($request,$this->payFlag);
        $orderInfo = $prePayData['order_info'];
        $notifyUrl = $prePayData['notifyUrl'];
        $mercId = $prePayData['merchId'];
        $channelNo = $prePayData['channelNo'];
        $secret = $prePayData['secret'];
//        $ip = $prePayData['ip'];
        $payUrl = $prePayData['pay_url'];
        $input = [
            'p1_merchantno' => $mercId,               //商户号
            'p2_amount' => round($orderInfo->amount ?? 0,2),              //订单金额,单位元保留两位小数
            'p3_orderno' => strval($orderInfo->number),           //订单号，值允许英文数字
            'p4_paytype' => $channelNo,            //支付通道编码
            'p5_reqtime' => date('YmdHis'),//支付发起时间
            'p6_goodsname' => $orderInfo->id,              //商品名称
            //'p7_bankcode' => '', //【可选】银行编码: 付款银行的编码，仅在网关支付产品中有意义, 其他支付产品请传递空白字符串或忽略该参数。
            'p8_returnurl' => 'https://dl.yinlian66.com',     //同步跳转 URL
            'p9_callbackurl' => $notifyUrl,              //后台异步通知 (回调) 地址
        ];
        //生成签名 请求参数按照Ascii编码排序
        //MD5 签名: HEX 大写, 32 字节。
        $input['sign'] = $this->sign($input, $secret);
        Log::info($this->payFlag.'_third_params===', [$input]);//三方参数日志
        $curl = (new Client([
            //  'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'verify' => false,
        ]))->post($payUrl, ['form_params' => $input]);

        $response = $curl->getBody();
        $resJson = json_decode($response, true);
        if ($resJson['rspcode'] == 'A0') {
            $this->pullPayEvent($orderInfo);
            $return = $this->format(0, ['url' => $resJson['data']??''], '取出成功');
        } else {
            $return = $this->format($resJson['rspcode'], $resJson, $resJson['rspmsg']);
        }
        // 强制转换
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
        $postResp = $request->except(['s']);
        Log::info($this->payFlag.'_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv[$this->payFlag]['secret'];
            $signPass = $this->verify($postResp, $secret, $postResp['sign']);
            if (!$signPass) {
                // 签名验证不通过
                throw new Exception('签名验证不通过', -1);
            }else{
                // 记录支付信息
                DB::beginTransaction();
                $this->orderUpdate($postResp['p3_orderno'], $postResp);
                DB::commit();
            }

            $return = 'SUCCESS';
        } catch (Exception $e) {
            Log::info($this->payFlag.'_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
            $return = 'FAILED';
        }
        return response($return);
    }

    public function method(Request $request): mixed
    {
        // TODO: Implement method() method.
        return '';
    }

    function sign($data, $md5Key): string
    {
        $native = $data;
        ksort($native);
        $md5str = "";
        foreach ($native as $key => $val) {
            if($key!='sign'){
                $md5str = $md5str . $key . "=" . $val . "&";
            }
        }
        //Log::debug('==callbackIp==',[$this->getRealIp()]);
        Log::debug('==signAble==',[$md5str . "key=" . $md5Key]);
        Log::debug('==signAbleRes==',[strtoupper(md5($md5str . "key=" . $md5Key))]);
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
        $sign = $this->sign($data,$md5Key);
        if ($sign == $pubKey) {
            return true;
        }
        return false;
    }
}