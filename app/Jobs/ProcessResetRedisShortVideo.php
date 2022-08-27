<?php

namespace App\Jobs;

use App\Models\AdminVideoShort;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoShortTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessResetRedisShortVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PHPRedisTrait, VideoShortTrait;

    public object $row;

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
     */
    public function handle()
    {
        //
        $model = $this->row;
        $this->resetRedisVideoShort($model, true);

    }
}
