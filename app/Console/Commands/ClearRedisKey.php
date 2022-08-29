<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearRedisKey extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clearRedis:keys {key?} {n?}';

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
        $paramKey = $this->argument('key');
        $n = $this->argument('n');

        if(!$paramKey){
            $this->info('######无匹配的key######');
            return 1;
        }
        $db = !$n?0:(int)$n;
        $redis = $this->redis();
        $redis->select($db);
        $keys = $redis->rawCommand('keys','*'.$paramKey.'*');
//        $keys = $redis->keys('*'.$paramKey.'*');
        $bar = $this->output->createProgressBar(count($keys));
        $bar->start();
        foreach ($keys as $key){
            $redis->del($key);
            //$this->info('######key:'.$key.'######');
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行完成######');
        return 0;
    }
}
