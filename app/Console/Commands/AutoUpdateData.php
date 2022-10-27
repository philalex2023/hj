<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDataSource;
use App\Models\DataSource;
use App\TraitClass\DataSourceTrait;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Video;

class AutoUpdateData extends Command
{
    use DataSourceTrait,DispatchesJobs;
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
        Artisan::call('scout:import "App\\Models\\Video"');
        //
        $dataSource = DB::table('data_source')->get();

        $bar = $this->output->createProgressBar(count($dataSource));
        $bar->start();
        foreach ($dataSource as $model){
            //
            $this->getDataSourceIdsForVideo($model);
            $dataSourceModel = DataSource::query()->findOrFail($model->id);
            $dataSourceModel->fill((array)$model)->save();
            $job = new ProcessDataSource($dataSourceModel);
            $this->dispatch($job->onQueue('default'));
            //$this->info('######key:'.$key.'######');
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行完成######');
        return 0;
    }
}
