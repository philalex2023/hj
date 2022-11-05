<?php

namespace App\Jobs;

use App\Models\Carousel;
use App\TraitClass\AboutEncryptTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessCarousel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, AboutEncryptTrait, PHPRedisTrait;

    public Carousel $carousel;

    public int $timeout = 7200;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($row)
    {
        //
        $this->carousel = $row;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle(): void
    {
        //
        /*$model = $this->carousel;
        $cid = $model->cid??0;
        $key = 'api_carousel.'.$cid;
        $value = Carousel::query()
            ->where('cid', $cid)
            ->where('status', 1)
            ->orderByDesc('sort')
            ->get(['id','title','img','url','action_type','vid','status','sort','line','end_at']);
        Cache::forever($key,$value);
        $this->syncUpload($model->img);*/
    }
}
