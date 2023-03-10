<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait MemberCardTrait
{

    public array $cardRights = [
        1 => [
            'id' => 1,
            'icon' => 1,
            'name' => 'VIP视频无限看'
        ],
        2 => [
            'id' => 2,
            'icon' => 2,
            'name' => '专属标识'
        ],
        3 => [
            'id' => 3,
            'icon' => 3,
            'name' => '评论特权'
        ],
        4 => [
            'id' => 4,
            'icon' => 4,
            'name' => '金币视频无限看'
        ],
        5 => [
            'id' => 5,
            'icon' => 5,
            'name' => '收藏特权'
        ],
        6 => [
            'id' => 6,
            'icon' => 6,
            'name' => '专属客服'
        ],
        7 => [
            'id' => 7,
            'icon' => 7,
            'name' => '短视频无限看'
        ],
        8 => [
            'id' => 8,
            'icon' => 8,
            'name' => '社交特权'
        ],
    ];

    public function numToRights($num): array
    {
        $rights = [];
        foreach ($this->cardRights as $right)
        {
            $pos = $right['id']-1;
            if((($num >> $pos) & 1) == 1){
                $rights[] = $right['id'];
            }
        }
        return $rights;
    }

    public function getRightsName($num): string
    {
        $ids = $this->numToRights($num);
        $name = '';
        $char = '||';
        foreach ($ids as $id)
        {
            $name .= $this->cardRights[$id]['name'] . $char;
        }
        return rtrim($name,$char);
    }

    public function binTypeToNum($rights): float|object|int
    {
        $num = 0;
        foreach ($rights as $right)
        {
            $num += pow(2,$right-1);
        }
        return $num;
    }

    public function getMemberCardList($except=null): array
    {
        $queryBuild = DB::table('member_card');
        $items = match ($except) {
            'gold' => ['' => ''] + $queryBuild->pluck('name', 'id')->all(),
            'default' => $queryBuild->pluck('name', 'id')->all(),
            default => ['' => '全部', 0 => '金币'] + $queryBuild->pluck('name', 'id')->all(),
        };
        $lists = [];
        foreach ($items as $key => $value){
            $lists[$key] = [
                'id' => $key,
                'name' => $value,
            ];
        }
        return $lists;
    }

    public function isForeverCard($memberCardTypeId): bool
    {
        $hasMemberCards = Cache::get('member_card_key') ?? [];
        $forever = false;
        foreach ($hasMemberCards as $memberCard){
            if($memberCard->id == $memberCardTypeId){
                if($memberCard->expired_hours == 0){ //永久卡
                    $forever = true;
                }
                break;
            }
        }
        return $forever;
    }

    public function getUserCardInfo($memberCardTypeId,$cardIds): array
    {
        $hasMemberCards = Cache::get('member_card_key') ?? [];
        $forever = false;
        $flipArray = array_flip($cardIds);
        $n = 0;
        $rightName = [];
        foreach ($hasMemberCards as $memberCard){
            if($memberCard->id == $memberCardTypeId){
                if($memberCard->expired_hours == 0){ //永久卡
                    $forever = true;
                }
            }
            if(isset($flipArray[$memberCard->id])){
                $rightName[$memberCard->rights] = $memberCard->name;
                $memberCard->rights>$n && $n=$memberCard->rights;
            }
        }
        return [
            'forever' => $forever,
            'name' => !empty($rightName) ? $rightName[$n] : '未开通',
        ];
    }

    public function getCardRightIds($cards): array
    {
        $rightIds = [];
        foreach ($cards as $memberCard){
            $rightIds = [...$rightIds,...$this->numToRights($memberCard->rights)];
        }
        return $rightIds;
    }

    public function getUserAllRights($user): array
    {
        $rightIds = [];
        $isExpired = ($user->vip_start_last+$user->vip_expired)<time();
        if(!$user->member_card_type || $user->member_card_type=="" || $isExpired){
            return [];
        }
        $types = explode(',',$user->member_card_type);
        if(!empty($types)){
            $hasMemberCards = Cache::get('member_card_key') ?? [];
            foreach ($hasMemberCards as $memberCard){
                if(in_array($memberCard->id,$types)){
                    $rightIds = [...$rightIds,...$this->numToRights($memberCard->rights)];
                }
            }
            $rightIds = array_flip($rightIds);
        }
        return $rightIds;
    }

    public function getVipValue($user): int
    {
        $vipValue = 1;
        if(!$user->member_card_type || $user->member_card_type==""){
            return 0;
        }
        $types = explode(',',$user->member_card_type);
        if(!empty($types)){
            $memberCardTypeId = $types[0];
            $hasMemberCards = Cache::get('member_card_key') ?? [];
            $forever = false;
            $rightIds = [];
            foreach ($hasMemberCards as $memberCard){
                $rightIds = [...$rightIds,...$this->numToRights($memberCard->rights)];
                if($memberCard->id == $memberCardTypeId){
                    if($memberCard->expired_hours == 0){ //永久卡
                        $forever = true;
                    }
                }
            }
            if(!array_diff([1,7],$rightIds)){
                return 2;
            }
            if(!$forever){
                if(time() - $user->vip_expired > $user->vip_start_last){
                    $vipValue = 0;
                }
            }
        }else{
            $vipValue = 0;
        }
        return $vipValue;
    }
}