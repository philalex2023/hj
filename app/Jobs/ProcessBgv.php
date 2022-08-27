<?php

namespace App\Jobs;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBgv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PHPRedisTrait;

    public $key;
    public $member;
    public $vid;
    public $uid;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($key,$member,$vid,$uid)
    {
        //
        $this->key = $key;
        $this->member = $member;
        $this->vid = $vid;
        $this->uid = $uid;
    }

    /**
     * Execute the job.
     *
     * @return int
     */
    public function handle()
    {
        //
        $redis = $this->redis('video');
        if($redis->getBit('buyVideoWithGold_'.$this->vid,$this->uid)==1){
            $redis->sAdd($this->key,$this->member);
            $redis->expire($this->key,30*24*3600);
        }
        return 0;
    }
}
