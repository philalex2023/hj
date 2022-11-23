<?php

namespace App\Console\Commands;

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
        Redis::pipeline(function ($pipe) {
            $pipe->select(7);
            for ($i = 0; $i < 10; $i++) {
                $pipe->set("testKey:$i", $i);
            }
        });
        return 0;
    }
}
