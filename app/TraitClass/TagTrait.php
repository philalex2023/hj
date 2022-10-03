<?php

namespace App\TraitClass;

use App\Models\Tag;

trait TagTrait
{

    public function getTagData(): array
    {
        return array_column(Tag::query()->get(['id','name'])->all(),null,'id');
    }

    public function transferJsonFieldName($data,$jsonArr): string
    {
        $name = [];
        foreach ($jsonArr as $v){
            isset($data[$v]) && $name[] = $data[$v]['name'];
        }
        return implode(',',$name);
    }

    public function getTagName($tag): string
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