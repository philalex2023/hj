<?php

namespace App\TraitClass;

use App\Models\Tag;

trait TagTrait
{

    public function getTagData($usage = 1)
    {
        return Tag::query()->where('usage',$usage)->get(['id','name'])->toArray();
    }

    public function getTagName($tag)
    {
        $tagArr = !is_array($tag) ? (array)json_decode($tag, true) : $tag;
        $name = '';
        $characters = '|';
        foreach ($tagArr as $t)
        {
            $name .= $t.$characters;
        }
        return rtrim($name,$characters);
    }
}