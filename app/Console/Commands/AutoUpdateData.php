<?php

namespace App\Console\Commands;

use App\Models\DataSource;
use App\Models\Topic;
use App\TraitClass\DataSourceTrait;
use App\TraitClass\TopicTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoUpdateData extends Command
{
    use DataSourceTrait,TopicTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto_update_data';

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
        //
        $dataSource = DB::table('data_source')->get();

        $bar = $this->output->createProgressBar(count($dataSource));
        $bar->start();
        foreach ($dataSource as $model){
            //
            $this->getDataSourceIdsForVideo($model);
            $dataSourceModel = DataSource::query()->findOrFail($model->id);
            $this->updateTopicData($dataSourceModel);
            $bar->advance();
        }
        $bar->finish();
        $this->call('scout:import',["App\Models\Video"]);
        $this->info('######执行完成######');
        return 0;
    }

    public function updateTopicData($model)
    {
        $topics = Topic::query()->where('data_source_id',$model->id)->get(['id','tag','cid']);
        foreach ($topics as $topic) {
            $tag  = !$topic->tag ? [] : json_decode($topic->tag,true);
            $tag = !$tag ? [] : $tag;
            $tagVideoIds = [];
            if(!empty($tagVideoIds)){
                $tagVideoIds = $this->getVideoIdsByTag($tag);
                //Log::info('_tagVideoIds',[$tagVideoIds]);
            }
            $sourceIds = !$model->contain_vids ? [] : explode(',',$model->contain_vids);
            $sIds = array_unique($sourceIds);
            //Log::info('_sourceIds',[$sIds]);
            $firstIds = [];
            if($model->show_num > 0){
                $firstIds = $this->getDataSourceSortArr($model->sort_vids);
                krsort($firstIds);
                //Log::info('firstIds',[$firstIds]);
                //更新数据源
                $updateIdStr = implode(',',array_unique([...$firstIds,...$sourceIds]));
                //Log::info('idStr-1',[$updateIdStr]);
                DataSource::query()->where('id',$model->id)->update(['contain_vids'=>$updateIdStr]);
            }
            $mergerArr = [...$firstIds,...$tagVideoIds,...$sIds];
            $ids = array_unique($mergerArr);
            //Log::info('testDataSourceHandleTopic',[$firstIds,$tagVideoIds,$sourceIds]);
            $idStr = implode(',',$ids);
//            Log::info('update data num',[count($ids)]);
            Topic::query()->where('id',$topic->id)->update(['contain_vids'=>$idStr]);
//            Log::info('update topic id',[$topic->id]);
            $this->updateTopicListByCid($topic->cid);
        }
    }

    public function getDataSourceSortArr($sort_vid)
    {
        $originSort = !$sort_vid ? [] : json_decode($sort_vid, true);
        !$originSort && $originSort=[];
        return $originSort;
    }
}
