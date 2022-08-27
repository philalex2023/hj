<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateVideoTags extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update_video_tags {table?}';

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
        $table = $this->argument('table');
        $tagPluck = DB::table('tag')->pluck('name','id');
        DB::table($table)
            //->take(5)
            //->orderByDesc('id')
            ->chunkById(800,function ($videos) use ($table,$tagPluck){
                foreach ($videos as $video) {
                    //
                    $tagArr = (array)json_decode($video->tag,true);
                    if(!empty($tagArr)){
                        $value = [];
                        foreach ($tagArr as $tid){
                            isset($tagPluck[$tid]) && $value[$tid] = $tagPluck[$tid];
                        }
                        DB::table($table)->where('id',$video->id)->update(['tag_kv'=>json_encode($value)]);
                    }
                }
                $this->info('finished 800!');
            });
        $this->info('######执行完成######');
        return 0;
    }
}
