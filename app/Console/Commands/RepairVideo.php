<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRepairVideo;
use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter;

class RepairVideo extends Command
{
    use PHPRedisTrait,DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repair_video';

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
        $Items = DB::table('video')
            ->where('type',4)
//            ->where('id','=',29315)
            ->where('id','<',30693)
            ->where('duration_seconds','=',0)
            ->get(['id','url','status','hls_url']);
        $bar = $this->output->createProgressBar(count($Items));
        $bar->start();

        $bar->advance();

        $ids = [];

        foreach ($Items as $item)
        {
            $ids[] = $item->id;
            $this->info('can delete '.$item->id.' status '.$item->status);
            /*$file_name = pathinfo($item->url,PATHINFO_FILENAME);
            $tmp_path = '/home/hj/public/slice/hls/'.$file_name.'/';
            $keyFile = $tmp_path.'secret.key';
            $exists = file_exists($keyFile);
            !$exists && $this->info('not found '.$item->id.' '.$keyFile);*/
            /*$job = new ProcessRepairVideo($item);
            $this->dispatch($job->onQueue('high'));*/
        }
        $bar->finish();

        //DB::table('video')->whereIn('id',$ids)->delete();

        $this->info('######执行成功######');
        return 0;
    }

}
