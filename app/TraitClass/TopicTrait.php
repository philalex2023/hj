<?php

namespace App\TraitClass;

use App\Models\Topic;

trait TopicTrait
{

    public function getSelectTopicData(): array
    {
        return array_column(Topic::query()->get(['id','name'])->all(),null,'id');
    }

}