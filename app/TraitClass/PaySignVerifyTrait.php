<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\Log;

trait PaySignVerifyTrait
{
    function signYK($data, $md5Key): string
    {
        //签名数据转换为大写
        return strtolower(md5($md5Key . $data['orderNo'] . $data['appId'] . $data['amount'] . $data['notifyCallback']));
    }

    function verifyYK($data, $md5Key, $pubKey): bool
    {
        $sig_data = strtolower(md5($md5Key . $data['orderNo'] . $data['appId'] . $data['amount']));
        if ($sig_data == $pubKey) {
            return true;
        }
        return false;
    }

    function signYL($data, $md5Key): string
    {
        $md5str = $data['orderId'].$data['amount'].$data['merchId'];
        return md5($md5str . $md5Key);
    }

    function verifyYL($data, $md5Key, $pubKey): bool
    {
        $sign = md5($data['orderId'].$data['respCode'].$md5Key);
        if ($sign == $pubKey) {
            return true;
        }
        return false;
    }

    function signAX($data, $md5Key): string
    {
        $native = array(
            "fxid" => $data['fxid'],
            "fxddh" => $data['fxddh'],
            "fxfee" => $data['fxfee'],
            "fxnotifyurl" => $data['fxnotifyurl'],
        );
        $md5str = implode('', $native);
        return md5($md5str . $md5Key);
    }

    function verifyAX($data, $md5Key, $pubKey): bool
    {
        $returnArray = array( // 返回字段
            "fxstatus" => $data["fxstatus"], // 状态
            "fxid" =>  $data["fxid"], // 商务号
            "fxddh" =>  $data["fxddh"], // 商户订单号
            "fxfee" =>  $data["fxfee"], // 支付金额
        );
        $sign = md5(implode('', $returnArray) . $md5Key);
        if ($sign == $pubKey) {
            return true;
        }
        return false;
    }
}