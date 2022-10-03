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
            $tagVideoIds = $this->getVideoIdsByTag($tag);
            $sourceIds = !$model->contain_vids ? [] : explode(',',$model->contain_vids);
            $ids = array_unique([...$sourceIds,...$tagVideoIds]);
            Topic::query()->where('id',$topic->id)->update(['contain_vids'=>implode(',',$ids)]);
        }
    }


}
