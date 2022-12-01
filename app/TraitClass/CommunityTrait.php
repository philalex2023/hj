<?php

namespace App\TraitClass;

trait CommunityTrait
{
    public function getCommunityCat(): array
    {
        return [
            [
                'id'=>1,
                'sort'=>1,
                'selected'=>0,
                'name'=>'关注',
            ],
            [
                'id'=>2,
                'sort'=>2,
                'selected'=>1,
                'name'=>'广场',
            ],
            [
                'id'=>3,
                'sort'=>3,
                'selected'=>0,
                'name'=>'话题中心',
            ],
        ];
    }
}