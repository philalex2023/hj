<?php

namespace App\TraitClass;

use App\Models\Tag;
use AWS\CRT\Log;
use Illuminate\Support\Facades\DB;

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

    public function getVideoIdsByTag(array $tag): array
    {
        $tagVideoIds = [];
        if(!empty($tag)){
            DB::table('video')->where('status',1)->chunkById(100,function ($items) use ($tag,&$tagVideoIds){
                foreach ($items as $item){
                    Log::log('video_tag',[$item->tag]);
                    $jsonArr = json_decode($item->tag,true);
                    $intersect = array_intersect($jsonArr,$tag); //交集
                    if(!empty($intersect)){
                        $tagVideoIds[] = $item->id;
                    }
                }
            });
        }
        return $tagVideoIds;
    }
}