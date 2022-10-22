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
            ->where('status',1)
            ->where('id','<=',31230)
            ->orderByDesc('id')
//            ->whereIn('id',[5058, 5074, 5026, 5011, 5023, 5013, 5020, 5073, 4999, 5018, 5016, 4994, 5025, 5012, 5021, 5024, 5022, 4998, 5007, 4987, 4989, 4990, 4991, 4992, 4993, 4995, 4996, 4997, 5000, 5005, 5015])
            //->where('id','>=',11665)
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
