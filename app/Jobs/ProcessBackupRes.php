<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessBackupRes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Object $row;

    public int $timeout = 180000; //默认60秒超时
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($row)
    {
        //
        $this->row = $row;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        //
        $hls_directory = pathinfo(str_replace('/storage','/public',$this->row->hls_url),PATHINFO_DIRNAME);
//        if(is_dir(Storage::disk('ftps')->path($hls_directory))){
            $cover_img_dir = pathinfo(str_replace('/storage','/public',$this->row->cover_img),PATHINFO_DIRNAME);
            $hls_files = Storage::disk('ftps1')->files($hls_directory);
            $cover_img = Storage::disk('ftps1')->files($cover_img_dir);
            foreach ($hls_files as $file){
                $content = Storage::disk('ftps1')->get($file);
                Storage::disk('ftps')->put($file,$content);
            }
            foreach ($cover_img as $img)
            {
                $content = Storage::disk('ftps1')->get($img);
                Storage::disk('ftps')->put($img,$content);
            }
//        }

    }
}
