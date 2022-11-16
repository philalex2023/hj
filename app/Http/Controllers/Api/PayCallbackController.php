<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\TraitClass\PaySignVerifyTrait;
use App\TraitClass\PayTrait;
use App\TraitClass\IpTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


/**
 * 通达支付
 * Class AXController
 * @package App\Http\Controllers\Api
 */
class PayCallbackController extends Controller
{
    use PaySignVerifyTrait,PayTrait,IpTrait;

    public function callbackKF(Request $request): string
    {
        $postResp = $request->post();
        $payName = 'KF';
        Log::info($payName.'_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv[$payName]['secret'];
            $verify = 'verify'.$payName;
            $signPass = $this->$verify($postResp, $secret, $postResp['sign']);
            if (!$signPass) {
                // 签名验证不通过
                Log::info($payName.'_verify_no_pass===', ['签名验证不通过']);
            }
            if ($postResp['status'] == 0) {
                // 记录支付信息
                DB::beginTransaction();
                $this->orderUpdate($postResp['out_trade_no'], $postResp);
                DB::commit();
            }
            Log::info($payName.'_pay_callback===', ['SUCCESS']);
            $return = 'success';
        } catch (\Exception $e) {
            isset($postResp['out_trade_no']) && Order::query()->where('number',$postResp['out_trade_no'])->update(['status'=>3]);
            Log::info($payName.'_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
            $return = 'failure';
        }
        return response($return);
    }

    public function callbackYK(Request $request): string
    {
        $postResp = $request->post();
        Log::info('yk_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv['YK']['secret'];
            $postResp['appId']= $payEnv['YK']['merchant_id'];
            $verify = 'verifyYK';
            $signPass = $this->$verify($postResp, $secret, $postResp['sign']);
            if (!$signPass) {
                // 签名验证不通过
                Log::info('yk_verify_no_pass===', ['签名验证不通过']);
            }
            if ($postResp['status'] == 1) {
                // 记录支付信息
                DB::beginTransaction();
                $this->orderUpdate($postResp['orderNo'], $postResp);
                DB::commit();
            }
            Log::info('yk_pay_callback===', ['SUCCESS']);
            $return = 'success';
        } catch (\Exception $e) {
            Order::query()->where('number',$postResp['orderNo'])->update(['status'=>3]);
            Log::info('yk_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
            $return = 'failure';
        }
        return response($return);
    }

    public function callbackYL(Request $request)
    {
        $postResp = $request->post();
        $ip = $this->getRealIp();
        Log::info('YL_pay_callback===', [$ip,$postResp]);
        $return = 'FAILED';
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv['YL']['secret'];
            $postResp['amount'] = $postResp['amount'] ?? $postResp['orderAmount'];
            $postResp['merchId'] = $postResp['merchId'] ?? '';
            $verify = 'verifyYL';
            $signPass = $this->$verify($postResp, $secret, $postResp['sign']);
            if (!$signPass) {
                // 签名验证不通过
                Log::info('yk_verify_no_pass===', ['签名验证不通过']);
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
        } catch (\Exception $e) {
            Order::query()->where('number',$postResp['orderId'])->update(['status'=>3]);
            Log::info('YL_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
        }
        Log::info('YL_pay_callback_res===', [$return]);
        return response($return);
    }

    public function callbackAX(Request $request)
    {
        $postResp = $request->post();
        Log::info('ax_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv['AX']['secret'];

            $verify = 'verifyAX';
            $signPass = $this->$verify($postResp, $secret, $postResp['fxsign']);
            if (!$signPass) {
                // 签名验证不通过
                Log::info('yk_verify_no_pass===', ['签名验证不通过']);
            }
            if ($postResp['fxstatus'] == 1) {
                // 记录支付信息
                DB::beginTransaction();
                $this->orderUpdate($postResp['fxddh'], $postResp);
                DB::commit();
            }
            $return = 'success';
        } catch (\Exception $e) {
            Order::query()->where('number',$postResp['fxddh'])->update(['status'=>3]);
            Log::info('ax_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
            $return = 'failed';
        }
        Log::info('AX_pay_callback_res===', [$return]);
        return response($return);
    }


}