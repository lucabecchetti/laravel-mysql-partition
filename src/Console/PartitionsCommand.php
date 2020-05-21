<?php

namespace Brokenice\LaravelMysqlPartition\Console;

use Brokenice\LaravelMysqlPartition\Models\Partition;
use Brokenice\LaravelMysqlPartition\Schema\Schema;
use Illuminate\Console\Command;

class PartitionsCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel-mysql-partition
                            {action : Action to perform} 
                            {--database=} {--table=} {--method=} {--number=} {--excludeFuture} {--column=} {--partitions=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage of laravel mysql partition';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->checkForOptions(['table']);
        switch ($this->argument('action')) {
            case 'list':
                $this->checkForOptions(['table']);
                $partitions = Schema::getPartitionNames($this->option('database') ?: env('DB_DATABASE'), $this->option('table'));
                $this->table(
                    ['PARTITION_NAME', 'SUBPARTITION_NAME', 'PARTITION_ORDINAL_POSITION', 'TABLE_ROWS', 'PARTITION_METHOD'],
                    collect($partitions)->map(static function ($item) { return (array) $item;})
                );
                break;
            case 'delete':
                Schema::deletePartition($this->option('table'), $this->option('partitions'));
                $this->info('Partition '.implode(',', $this->option('partitions')).' did delete successfully!');
                break;
            case 'truncate':
                Schema::truncatePartitionData($this->option('table'), $this->option('partitions'));
                $this->info('Partition '.implode(',', $this->option('partitions')).' did truncate successfully!');
                break;
            case 'optimize':
                $result = Schema::optimizePartitions($this->option('table'), $this->option('partitions'));
                $this->parseResultIntoTable($result);
                break;
            case 'repair':
                $result = Schema::repairPartitions($this->option('table'), $this->option('partitions'));
                $this->parseResultIntoTable($result);
                break;
            case 'check':
                $result = Schema::checkPartitions($this->option('table'), $this->option('partitions'));
                $this->parseResultIntoTable($result);
                break;
            case 'analyze':
                $result = Schema::analyzePartitions($this->option('table'), $this->option('partitions'));
                $this->parseResultIntoTable($result);
                break;
            case 'rebuild':
                Schema::rebuildPartitions($this->option('table'), $this->option('partitions'));
                $this->info('Partitions '. implode(',', $this->option('partitions')). ' did rebuilt successfully!');
                break;
            case 'create':
                $this->checkForOptions(['table', 'method']);
                switch ($this->option('method')){
                    case "HASH":
                        $this->checkForOptions(['number'], 'numeric');
                        $this->checkForOptions(['column']);
                        Schema::partitionByHash($this->option('table'), $this->option('column'), $this->option('number'));
                        $this->info('Table did partitioned successfully!');
                        break;
                    case "RANGE":
                        $this->checkForOptions(['column']);
                        $partitions = $this->askRangePartitions();
                        Schema::partitionByRange($this->option('table'), $this->option('column'), $partitions, !$this->option('excludeFuture'));
                        $this->info('Table did partitioned successfully!');
                        break;
                    case "YEAR":
                        $this->checkForOptions(['column']);
                        $yearRanges = $this->askforYearRange();
                        Schema::partitionByYears($this->option('table'), $this->option('column'), $yearRanges[0], $yearRanges[1] ?: date('Y'));
                        $this->info('Table did partitioned successfully!');
                        break;
                    case "KEY":
                        $this->checkForOptions(['number'], 'numeric');
                        Schema::partitionByKey($this->option('table'), $this->option('number'));
                        $this->info('Table did partitioned successfully!');
                        break;
                    case "LIST":
                        $this->checkForOptions(['column']);
                        $partitions = $this->askListPartitions();
                        Schema::partitionByList($this->option('table'), $this->option('column'), $partitions);
                        $this->info('Table did partitioned successfully!');
                        break;
                }
                break;
            default:
                $this->error('unable to find action: ' . $this->argument('action'));
                break;
        }
    }

    /**
     * @param $options
     * @param string $type
     */
    private function checkForOptions($options, $type = '')
    {
        foreach ($options as $option) {
            if (empty($this->option($option)) || $this->option($option) === null) {
                $this->error("\n Please, insert $option option! \n");
                die();
            }
            switch ($type){
                case "numeric":
                    if(!is_numeric($this->option($option))){
                        $this->error("\n Error, $option option must be a number! \n");
                        die();
                    }
                    break;
                case "array":
                    if(count(explode(',',$this->option($option))) <= 0){
                        $this->error("\n Error, $option option must be a comma separated string! \n");
                        die();
                    }
                    break;
                default:
                    if(!is_string($this->option($option))){
                        $this->error("\n Error, $option option must be a string! \n");
                        die();
                    }
                    break;
            }
        }
    }

    /**
     * Ask user to build list partitions
     * @return array
     */
    private function askListPartitions()
    {
        $partitions = [];
        do {
            $listNumber = $this->ask('How many partition do you want to create?');
        } while (!is_numeric($listNumber));
        for ($i=0, $iMax = $listNumber; $i< $iMax; $i++){
            do{
                $items = explode(',', $this->ask('Enter a comma separated value for list ' . $i));
            }while( !is_array($items) || count($items) <= 0);
            $partitions[] = new Partition('list'.$i, Partition::LIST_TYPE, $items);
        }
        return $partitions;
    }

    /**
     * Ask user to build list partitions
     * @return array
     */
    private function askRangePartitions()
    {
        $partitions = [];
        do{
            $items = explode(',', $this->ask('Enter a comma separated value for partitions of:'.$this->option('column')));
        }while( !is_array($items) || count($items) <= 0);
        foreach ($items as $value) {
            $partitions[] = new Partition('range' . $value, Partition::RANGE_TYPE, $value);
        }
        return $partitions;
    }

    /**
     * Ask user for year range
     * @return array
     */
    private function askforYearRange(){
        do {
            $startYear = $this->ask('Enter start year for partition:');
        } while (!is_numeric($startYear));
        do {
            $endYear = $this->ask('Enter end year for partition (leave blank for current year):');
        } while ( ($endYear !== null && !is_numeric($endYear)) || (is_numeric($endYear) && $endYear < $startYear) );
        return [$startYear, $endYear];
    }

    /**
     * Convert result into a table
     * @param $result
     */
    private function parseResultIntoTable($result)
    {
        foreach (collect($result)->map(static function ($item) { return (array) $item;}) as $res){
            $this->table(
                array_keys($res),
                [$res]
            );
        }
    }

}
