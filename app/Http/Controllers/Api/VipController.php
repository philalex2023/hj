<?php

namespace App\Http\Controllers\Api;

use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CacheTableTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Cache\Traits\RedisTrait;

class VipController extends \App\Http\Controllers\Controller
{
    use MemberCardTrait, PHPRedisTrait, CacheTableTrait, ApiParamsTrait;

    private string $payUrl = '/api/payBill';

    /**
     * @throws ValidationException
     */
    public function memberCards(Request $request): \Illuminate\Http\JsonResponse
    {
        if(isset($request->params)){
            $params = self::parse($request->params ?? '') ?? [];
            $validated = Validator::make($params, [
                'sc' => 'nullable',
            ])->validated();
        }

        $user = $request->user();
        $build = DB::table('member_card')->where('status',1);

        $sc = $validated['sc']??0;
        if($sc==1){
            $build = $build->orderByDesc('sort');
        }else{
            $build = $build->orderBy('sort');
        }
        $memberCard = $build->get(['id','name','sort','bg_img','remark','value','rights','hours','real_value','status','name_day','remain_hours'])->toArray();
        $ascItem = [];
        $registerTime = strtotime($user->created_at);
        $nowTime = time();
        $userExpiredTime =  $user->vip_start_last + $user->vip_expired;
        $diffTime = $userExpiredTime-$nowTime;
        foreach ($memberCard as $index => &$item)
        {
            $item = (array)$item;
            $rights = $this->numToRights($item['rights']);
            $rights_list = [];
            foreach ($rights as $right)
            {
                $rights_list[] = $this->cardRights[$right];
                if(($item['hours']>0) || ($item['real_value']>0)){
                    $real_value = $item['real_value'];
                    $item['valid_period'] = 0;
                    $item['real_value'] = 0;
                    if($diffTime>0 && ($diffTime/3600 < $item['remain_hours'])){
                        $item['valid_period'] = $item['hours']*3600;
                        $item['real_value'] = $real_value;
                    }
                    if($item['remain_hours']==0 && $user->vip==0 && $nowTime < ($registerTime+$item['hours']*3600)){
                        $item['valid_period'] = $registerTime+$item['hours']*3600-$nowTime;
                        $item['real_value'] = $real_value;
                    }
                }
            }
            /*if ($item['name_day'] && ($rights_list[0]['id'] == 1)) {
                $rights_list[0]['name'] = $item['name_day'];
            }*/
            $item['rights_list'] = $rights_list;
            unset($item['rights']);
            //
            if($diffTime>0 && ($diffTime/3600 < $item['remain_hours'])){
                $ascItem[] = $item;
                unset($memberCard[$index]);
            }

            if(isset($memberCard[$index]) && $user->vip==0){
                if($registerTime+$item['hours']*3600 > $nowTime){
                    $ascItem[] = $item;
                    unset($memberCard[$index]);
                }
            }

        }
        array_unshift($memberCard,...$ascItem);

        $payUrl = $this->getPayUrl();
        foreach ($memberCard as $mcKey=>$mvItem) {
            $memberCard[$mcKey]['zfb_url'] = $payUrl['zfb'];
            $memberCard[$mcKey]['wx_url'] = $payUrl['wx'];
        }

        $res['list'] = $memberCard;
        return response()->json([
            'state'=>0,
            'data'=>$res
        ]);
    }

    public function gold(): \Illuminate\Http\JsonResponse
    {
        $gold = DB::table('gold')
            ->where('status',1)
            ->orderBy('sort')
            ->get(['id','money','remark','bonus'])->toArray();
       $payUrl = $this->getPayUrl();
       foreach ($gold as $mvItem) {
           $mvItem->zfb_url = $payUrl['zfb'];
           $mvItem->wx_url = $payUrl['wx'];
       }
        return response()->json([
            'state'=>0,
            'data'=>$gold
        ]);
    }

    private function getPayUrl(): array
    {
       $zfb = DB::table('recharge_channels')->where('status',1)->where('pay_type',1)->exists();
       $wx = DB::table('recharge_channels')->where('status',1)->where('pay_type',2)->exists();
       $baseUrl =  env('PAY_DOMAIN','https://' .$_SERVER['HTTP_HOST']);
       return [
           'zfb' => $zfb ? $baseUrl.$this->payUrl : '',
           'wx' => $wx ? $baseUrl.$this->payUrl : '',
       ];
    }

}