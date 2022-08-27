<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessMemberCard;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\GoldTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\OrderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RechargeController extends \App\Http\Controllers\Controller
{
    use OrderTrait, MemberCardTrait, GoldTrait;

    public function methods()
    {
        $methods =  DB::table('recharge_type')->where('status',1)->get(['id','name','sort','icon','channel']);
        return response()->json([
            'state'=>0,
            'data'=>$methods
        ]);
    }

    /*public function submit(Request $request)
    {
        if(isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'amount' => 'required|integer',
                'id' => 'required|integer',
                'rechargeType' => 'required|integer',
                'orderType' => 'required|integer',
            ])->validate();
            //todo 加入扣量统计表

            return response()->json([
                'state'=>0,
                'msg'=>'提交成功',
                'data'=>[]
            ]);
        }
        return [];
    }*/
}