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
//            ->where('type',4)
            ->where('id','<',30693)
            ->get(['id','url','hls_url']);
        $bar = $this->output->createProgressBar(count($Items));
        $bar->start();

        $bar->advance();
        foreach ($Items as $item)
        {
            $file_name = pathinfo($item->url,PATHINFO_FILENAME);
            $tmp_path = 'public/slice/hls/'.$file_name.'/';
            $keyFile = $tmp_path.'/secret.key';
            $exists = Storage::exists($keyFile);
            !$exists && $this->info('not found '.$item->id);
            /*$job = new ProcessRepairVideo($item);
            $this->dispatch($job->onQueue('high'));*/
        }
        $bar->finish();
        $this->info('######执行成功######');
        return 0;
    }

}
