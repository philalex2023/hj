<?php

namespace App\Jobs;

use App\TraitClass\CommTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessResetRedisVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels,PHPRedisTrait, VideoTrait,CommTrait;

    public object $row;

    public array $cats=[];

    public array $tags=[];

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($cat,$tag,$row)
    {
        $this->row = $row;
        $this->cats = json_decode($cat,true);
        $this->tags = json_decode($tag,true);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //清除缓存
        $this->resetRedisCatVideo($this->cats,$this->row->id);
//        $this->resetRedisTagVideo($this->tags,$this->row->id);
        $this->resetHomeRedisData();
    }
}
