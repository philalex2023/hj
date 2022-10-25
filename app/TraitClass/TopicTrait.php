<?php

namespace App\TraitClass;

use App\Models\Topic;
use Illuminate\Support\Facades\DB;

trait TopicTrait
{
    use PHPRedisTrait;

    public function getSelectTopicData(): array
    {
        return array_column(Topic::query()->get(['id','name'])->all(),null,'id');
    }

    public function updateTopicListByCid($cid)
    {
        $getItems = DB::table('topic')
            ->where('cid',$cid)
            ->where('status',1)
            ->orderBy('sort')
            ->get(['id','cid','name','show_type','contain_vids']);
        $redis = $this->redis();
        $redis->set('topic_cid_'.$cid,json_encode($getItems,JSON_UNESCAPED_UNICODE));
        //
        foreach ($getItems as $item){
            $redis->hSet('topic_id_cid',$item->id,$item->cid);
        }
    }

    public function getTopicVideoIdsById($id)
    {
        $redis = $this->redis();
        $cid = $redis->hGet('topic_id_cid',$id);

        $redisJson = $redis->get('topic_cid_'.$cid);

        $containVidStr = '';
        if(!$redisJson){
            $containVidStr = DB::table('topic')->where('id',$id)->value('contain_vids');
        }else{
            $arr = json_decode($redisJson,true);
            foreach ($arr as $item){
                $item['id']==$id && $containVidStr = $item['contain_vids'];
            }
        }
        return $containVidStr;
    }

}