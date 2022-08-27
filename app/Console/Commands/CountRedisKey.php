<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CountRedisKey extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'count_redis_key {match?}';

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
        $this->info('######执行完成######');
        $this->info('COUNT:'.count($keys));
        return 0;
    }
}
