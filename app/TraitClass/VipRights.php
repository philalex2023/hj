<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait VipRights
{
    use MemberCardTrait;

    public function getCardsByIds($cardIds): array
    {
        $hasMemberCards = Cache::get('member_card_key') ?? [];
        $cards = [];
        foreach ($hasMemberCards as $item){
            if(in_array($item->id,$cardIds)){
                $cards[] = $item;
            }
        }
        return $cards;
    }

    //收藏权限 todo
    public function collectRight($user): bool
    {
        $cardIds = explode(',', ((string)$user->member_card_type));
        $cards = $this->getCardsByIds($cardIds);
        $rightsIds = $this->getCardRightIds($cards);
        //Log::info('Test_member_card_type',[$user->member_card_type]);
        //Log::info('Test_Card',[$cards]);
        //Log::info('Test_rights',[$rightsIds]);
        return !array_diff([8],$rightsIds);
    }

    //评论权限 todo
    public function commentRight($user): bool
    {
        $cardIds = explode(',', ((string)$user->member_card_type));
        $rightsIds = $this->getCardRightIds($this->getCardsByIds($cardIds));
        return !array_diff([6],$rightsIds);
    }

}