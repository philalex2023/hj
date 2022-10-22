<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBackupResFromMM;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;

class BackupResFromOrigin extends Command
{
    use DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup_res_from_origin';

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
    public function handle()
    {
        $Items = DB::table('video')
            ->where('type',1)
            ->orderByDesc('created_at')
            //->where('id','>=',11665)
            //->where('sync',1)
            //->take(1)
            ->get(['id','url','hls_url','cover_img']);
        $bar = $this->output->createProgressBar(count($Items));
        $bar->start();
        foreach ($Items as $item)
        {
            $this->info('#id:'.$item->id.'##url:'.$item->url);
            $job = new ProcessBackupResFromMM($item);
            $this->dispatch($job->onQueue('default'));
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行成功######');
        return 0;
    }
}
