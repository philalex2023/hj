<?php

namespace App\Console\Commands;

use App\Models\CommBbs;
use App\TraitClass\BbsTrait;
use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetCommBbs extends Command
{
    use BbsTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset_community_bbs';

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
        $paramTableName = 'community_bbs';
        $Items = DB::table($paramTableName)->get();
        $bar = $this->output->createProgressBar(count($Items));
        //$redis = $this->redis();
        $bar->start();
        foreach ($Items as $model)
        {
            $this->resetBBSItem($model);
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行成功######');
        return 0;
    }
}
