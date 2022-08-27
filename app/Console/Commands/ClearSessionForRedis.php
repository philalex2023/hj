<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearSessionForRedis extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear_session_for_redis_key {str?}';

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
        $paramStr = $this->argument('str');

        $redis = $this->redis();
        $keys = $redis->keys('*cache*');
        $bar = $this->output->createProgressBar(count($keys));
        $bar->start();
        foreach ($keys as $key){
            $key = str_replace('laravel_database_','',$key);
            $value = $redis->get($key);
            if(str_contains($value,$paramStr)){
                $redis->del($key);
            }
            //
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行完成######');
        return 0;
    }
}
