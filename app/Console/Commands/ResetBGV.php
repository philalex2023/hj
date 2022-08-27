<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBgv;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;

class ResetBGV extends Command
{
    use PHPRedisTrait, DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset_buy_gold_video';

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
        $Items = DB::table('recharge')
            ->where('type',2)
//            ->where('status',1)
//            ->where('is_office',0)
            ->whereDate('created_at','>',date('Y-m-d',strtotime('-8 day')))
//            ->whereDate('created_at','<=',date('Y-m-d',strtotime('-1 day')))
            ->pluck('uid');
        $bar = $this->output->createProgressBar(count($Items));
        $redis = $this->redis('video');
        $keys = $redis->keys('*buyVideoWithGold_*');
        $vids = [];
        foreach ($keys as $key){
            $vids[] = substr($key, strrpos($key, '_') + 1);
        }
        $this->info('uids:'.count($Items).'个');
        $this->info('vids:'.count($vids).'个');
        $bar->start();
        foreach ($Items as $id)
        {
            $newKey = 'buyGoldVideo_' . $id;
            foreach ($vids as $vid){
                $job = new ProcessBgv($newKey,$vid,$vid,$id);
                $this->dispatch($job->onQueue('default'));
            }
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行成功######');
        return 0;
    }
}
