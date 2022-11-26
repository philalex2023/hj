<?php

namespace App\TraitClass;

use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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
        Redis::pipeline(function ($pipe) use ($getItems){
            foreach ($getItems as $item){
                $key = 'topic_id_'.$item->id;
                $pipe->set($key,$item->contain_vids);
                $pipe->expire($key,7200);
            }
        });
    }

    public function getTopicVideoIdsById($id)
    {
        $redis = $this->redis();
        $key = 'topic_id_'.$id;

        $containVidStr = $redis->get($key);
        if(!$containVidStr && $containVidStr!==''){
            Log::info('TopicFromDb',['is',$id]);
            $containVidStr = DB::table('topic')->where('id',$id)->value('contain_vids');
            $containVidStr = $containVidStr ?? '';
            $redis->set($key,$containVidStr);
            $redis->expire($key,3600);
        }
        return $containVidStr;
    }

}