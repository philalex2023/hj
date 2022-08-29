<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBackupRes;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;

class BackupRes extends Command
{
    use DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:res {tableName?}';

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
        $paramTableName = $this->argument('tableName')??'video';
        $Items = DB::table($paramTableName)
            ->where('id','<=',16000)
            ->where('id','>',10)
            //->where('sync',1)
            //->take(1)
            ->get(['id','url','hls_url','cover_img']);
        $bar = $this->output->createProgressBar(count($Items));
        $bar->start();
        foreach ($Items as $item)
        {
            $this->info('#id:'.$item->id.'##url:'.$item->url);
            $job = new ProcessBackupRes($item);
            $this->dispatch($job->onQueue('default'));
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行成功######');
        return 0;
    }
}
