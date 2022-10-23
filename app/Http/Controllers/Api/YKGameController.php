<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayLog;
use App\Services\Pay;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\YKTrait;
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
 * YK支付
 * Class YKController
 * @package App\Http\Controllers\Api
 */
class YKGameController extends PayBaseController implements Pay
{
    use PayTrait;
    use ApiParamsTrait;
    use IpTrait;
    use YKTrait;

    public string $payFlag = 'YKGame';

    /**
     * YK支付动作
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
                Rule::in(['zfbwap', 'wxwap','1','2']),
            ],
        ])->validate();
        Log::info($this->payFlag.'_pay_params===', [$params]);//参数日志
        // 强制转换
        $payEnv = self::getPayEnv();
        $payEnvInfo = $payEnv[$this->payFlag];
        $secret = $payEnvInfo['secret'];

        $payInfo = PayLog::query()->find($params['pay_id']);
        if (!$payInfo) {
            throw new Exception("记录不存在");
        }

        $orderInfo = Order::query()->find($payInfo['order_id']);
        if (!$orderInfo) {
            throw new Exception("订单不存在");
        }

        $oldMix = false;
        $channelNo = $params['type'];
        if (in_array($params['type'],['1','2'])) {
            $oldMix = true;
            $channelNo = $this->getOwnMethod($orderInfo->type,$orderInfo->type_id,$params['type']);
        }


        $mercId = $payEnvInfo['merchant_id'];
        $notifyUrl = 'https://' .$_SERVER['HTTP_HOST'] . $payEnvInfo['notify_url'];
        $input = [
            'appId' => $mercId,               //商户号
            'orderNo' => strval($payInfo->number),           //订单号，值允许英文数字
            'channelNo' => $channelNo,            //支付通道编码
            'amount' => strval($orderInfo->amount ?? 0) . '.00',              //订单金额,单位元保留两位小数
            'notifyCallback' => $notifyUrl,   //异步返回地址
            'payType' => "1",   //固定1
        ];
        //生成签名 请求参数按照Ascii编码排序
        //私钥签名
        $input['sign'] = $this->sign($input, $secret);
        Log::info($this->payFlag .'_third_params===', [$input]);//三方参数日志
        $curl = (new Client([
            'headers' => ['Content-Type' => 'application/json'],
            'verify' => false,
        ]))->post($payEnvInfo['pay_url'], [
            'body' => json_encode($input)
        ]);
        $response = $curl->getBody();
         Log::info('YKGame_third_response===', [$response]);//三方响应日志
        $resJson = json_decode($response, true);
        if ($resJson['code'] == 1) {
            if ($oldMix) {
                $this->pullPayEvent($orderInfo);
                $return = $this->format(0, ['url'=>$resJson['payUrl']], '取出成功');
            } else {
                $return = $this->format(0, $resJson, '取出成功');
            }
        } else {
            $return = $this->format($resJson['code'], [], $response);
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
        Log::info($this->payFlag . '_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv[$this->payFlag]['secret'];
            $postResp['appId']= $payEnv[$this->payFlag]['merchant_id'];
            $signPass = $this->verify($postResp, $secret, $postResp['sign']);
            if (!$signPass) {
                // 签名验证不通过
                throw new Exception('签名验证不通过', -1);
            }
            if ($postResp['status'] == 1) {
                // 记录支付信息
                DB::beginTransaction();
                $this->orderUpdate($postResp['orderNo'], $postResp);
                DB::commit();
            }
            $return = 'success';
        } catch (Exception $e) {
            Order::query()->where('number',$postResp['orderNo'])->update(['status'=>3]);
            Log::info($this->payFlag .'_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
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
    public function sign($data, $md5Key): string
    {
        //签名数据转换为大写
        return strtolower(md5($md5Key . $data['orderNo'] . $data['appId'] . $data['amount'] . $data['notifyCallback']));
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
        $sig_data = strtolower(md5($md5Key . $data['orderNo'] . $data['appId'] . $data['amount']));
        if ($sig_data == $pubKey) {
            return true;
        }
        return false;
    }
}