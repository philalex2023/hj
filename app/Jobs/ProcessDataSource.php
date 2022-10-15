<?php

namespace App\Jobs;

use App\Models\Topic;
use App\TraitClass\TagTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessDataSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TagTrait;

    /**
     * 任务尝试次数
     *
     * @var
     * int
     */
    //public $tries = 3;

    public int $timeout = 180000; //默认60秒超时

    public mixed $row;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($row)
    {
        //
        $this->row = $row;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws FileNotFoundException
     * @throws \Exception
     */
    public function handle(): void
    {
        $model = $this->row;
        $topics = Topic::query()->where('data_source_id',$model->id)->get(['id','tag']);
        foreach ($topics as $topic) {
            $tag  = !$topic->tag ? [] : json_decode($topic->tag,true);
            $tag = !$tag ? [] : $tag;
            $tagVideoIds = [];
            if(!empty($tagVideoIds)){
                $tagVideoIds = $this->getVideoIdsByTag($tag);
                //Log::info('_tagVideoIds',[$tagVideoIds]);
            }
            $sourceIds = !$model->contain_vids ? [] : explode(',',$model->contain_vids);
            $sourceIds = array_unique($sourceIds);
            Log::info('_sourceIds',[$sourceIds]);
            $firstIds = [];
            if($this->row->show_num > 0){
                $containIds = explode(',',$model->contain_vids);
                Log::info('_contain_vids',[$containIds]);
                $firstIds = $this->getDataSourceSortArr($model->sort_vids);
                krsort($firstIds);
            }
            $ids = array_unique([...$firstIds,...$tagVideoIds,...$sourceIds]);
            Log::info('testDataSourceHandleTopic',[$firstIds,$tagVideoIds,$sourceIds]);
            Topic::query()->where('id',$topic->id)->update(['contain_vids'=>implode(',',$ids)]);
        }
    }

    public function getDataSourceSortArr($sort_vid)
    {
        $originSort = !$sort_vid ? [] : json_decode($sort_vid, true);
        !$originSort && $originSort=[];
        return $originSort;
    }


}
