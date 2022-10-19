<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter;

class RepairVideo extends Command
{
    use PHPRedisTrait;
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
            ->where('id',22127)
            ->get(['id','url','hls_url']);
        $bar = $this->output->createProgressBar(count($Items));
        $bar->start();

        $bar->advance();
        foreach ($Items as $item)
        {
            //$path = str_replace('/storage','/public',$item->hls_url);
            $file_name = pathinfo($item->url,PATHINFO_FILENAME);
            $tmp_path = 'public/slice/hls/'.$file_name.'/';
            $m3u8_path = $tmp_path.$file_name.'.m3u8';

//            $openPath = '';
            $video = \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk("ftp")
            ->openUrl(Storage::path($m3u8_path));
            $format = new \FFMpeg\Format\Video\X264();
            $encryptKey = HLSExporter::generateEncryptionKey();
            Storage::disk('local')->put($tmp_path.'/secret.key',$encryptKey);//在storage/app的位置
            $video->exportForHLS()
                ->withEncryptionKey($encryptKey)
                ->setSegmentLength(1)//默认值是10
                ->toDisk("local")
                ->addFormat($format)
                //->addFormat($lowBitrate)
                //->addFormat($midBitrate)
                //->addFormat($highBitrate)
                ->save($m3u8_path);
        }
        $bar->finish();
        $this->info('######执行成功######');
        return 0;
    }
}
