<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MoveTopic extends Command
{
    use PHPRedisTrait,VideoTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'move_topic';

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
        $topCat = DB::table('categories')
            ->where('parent_id',2)
            ->where('is_checked',1)
            ->orderBy('sort')
            ->pluck('id');
        foreach ($topCat as $cat){
            $topics = DB::table('categories')
                ->where('parent_id',$cat)
                ->where('is_checked',1)
                ->orderBy('sort')
                ->get();
            foreach ($topics as $topic){
                DB::table('topic')->insert([
                    'name' => $topic->name,
                    'status' => 1,
                    'cid' => $cat,
                    'tag' => json_encode([]),
                    'data_source' => json_encode([]),
                    'show_type' => $topic->group_type,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        //$bar = $this->output->createProgressBar(count($Items));
        //$bar->start();
        //$bar->finish();
        $this->info('######执行完成######');
        return 0;
    }
}
