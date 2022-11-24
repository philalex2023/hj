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
        /*$redis = $this->redis();

        $iterator = null;
// 遍历前缀
        $pattern = 'channel_day_statistics:*';
        $count = 15;
// 务必设置，如果没扫描到，继续扫描，而不是返回空，否则while直接退出，遍历就会不准确
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $total = [];
        $i = 0;

// $count可以不设置，非必需参数
        while($arr = $redis->scan($iterator, $pattern, $count)) {
            $arrVal = $redis->mget($arr);
            $ret = array_combine($arr, $arrVal);
            $total = array_merge($total, $ret);
            $i++;
        }

        var_dump($total);
        echo count($total).PHP_EOL;*/
        return 0;
    }
}
