<?php

namespace App\Console\Commands;

use App\Models\User;
use App\TraitClass\CurlTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class RedisTest extends Command
{
    use PHPRedisTrait,DispatchesJobs,CurlTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test_redis_pipe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        /*Redis::pipeline(function ($pipe) {
            $pipe->select(7);
            for ($i = 0; $i < 2; $i++) {
                $pipe->set("testKey1:$i", $i);
            }
            $pipe->select(8);
            for ($i = 0; $i < 2; $i++) {
                $pipe->set("testKey2:$i", $i);
            }
        });*/
        /*$didArr = User::query()->pluck('did')->all();
        $redis = $this->redis('login');
        $redis->pipeline();
        foreach ($didArr as $did){
            $redis->ping();
            $redis->multi();
            $redis->sAdd('account_did',$did);
            $redis->exec();
        }
        $this->info('total:'.count($didArr));*/
        $redis = $this->redis('test');
        $iterator = 0;
        while (false !== ($keys = $redis->scan($iterator,'channel_day_statistics:*',1000))){
            foreach($keys as $key) {
                echo $key . PHP_EOL;
            }
        }
        return 0;
    }
}
