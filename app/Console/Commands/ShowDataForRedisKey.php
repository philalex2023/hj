<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ShowDataForRedisKey extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'show_redis_data {match?}';

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
        $paramKey = $this->argument('match');
        if(!$paramKey){
            $this->info('######无匹配的key######');
            return 1;
        }
        $redis = $this->redis();
        $keys = $redis->keys('*'.$paramKey.'*');
        $channelData = [];
        foreach ($keys as $key){
            $type = $redis->type($key);

            if($type==5){
                $hashData = $redis->hGetAll($key);
                $channelData[$hashData['refer']] = $hashData['download_url'];
                /*$line = '';
                foreach ($hashData as $k=>$v)
                {
                    $line .= $k.'=>'.$v.'|';
                }
                $this->info('hash:'.$line);*/
            }

        }
        foreach ($channelData as $k => $v){
            $this->info('refer:'.$k.' => url:'.$v);
        }
        $this->info('######执行完成######');
        return 0;
    }
}
