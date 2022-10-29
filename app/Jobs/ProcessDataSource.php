<?php

namespace App\Jobs;

use App\Models\DataSource;
use App\Models\Topic;
use App\TraitClass\TagTrait;
use App\TraitClass\TopicTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessDataSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TagTrait,TopicTrait;

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
        $topics = Topic::query()->where('data_source_id',$model->id)->get(['id','tag','cid']);
        foreach ($topics as $topic) {
            $tag  = !$topic->tag ? [] : json_decode($topic->tag,true);
            $tag = !$tag ? [] : $tag;
            $tagVideoIds = [];
            if(!empty($tagVideoIds)){
                $tagVideoIds = $this->getVideoIdsByTag($tag);
                //Log::info('_tagVideoIds',[$tagVideoIds]);
            }
            $sourceIds = !$model->contain_vids ? [] : explode(',',$model->contain_vids);
            $sIds = array_unique($sourceIds);
            //Log::info('_sourceIds',[$sIds]);
            $firstIds = [];
            if($this->row->show_num > 0){
                $firstIds = $this->getDataSourceSortArr($model->sort_vids);
                krsort($firstIds);
                //Log::info('firstIds',[$firstIds]);
                //更新数据源
                $updateIdStr = implode(',',array_unique([...$firstIds,...$sourceIds]));
                //Log::info('idStr-1',[$updateIdStr]);
                DataSource::query()->where('id',$model->id)->update(['contain_vids'=>$updateIdStr]);
            }
            $mergerArr = [...$firstIds,...$tagVideoIds,...$sIds];
            $ids = array_unique($mergerArr);
            //Log::info('testDataSourceHandleTopic',[$firstIds,$tagVideoIds,$sourceIds]);
            $idStr = implode(',',$ids);
            Log::info('update data num',[count($ids)]);
            Artisan::call('scout:import',["App\Models\Video"]);
            Topic::query()->where('id',$topic->id)->update(['contain_vids'=>$idStr]);
            $this->updateTopicListByCid($topic->cid);
        }

    }

    public function getDataSourceSortArr($sort_vid)
    {
        $originSort = !$sort_vid ? [] : json_decode($sort_vid, true);
        !$originSort && $originSort=[];
        return $originSort;
    }


}
