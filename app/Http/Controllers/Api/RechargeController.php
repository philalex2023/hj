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

}