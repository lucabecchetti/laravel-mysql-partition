<?php


use Brokenice\LaravelMysqlPartition\Models\Partition;
use Brokenice\LaravelMysqlPartition\Schema\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Container\Container;
use App\Service;

class SchemaTest extends BaseTestCase
{

    protected function setUp(): void
    {
        parent::setUp(); //
        Schema::$have_partitioning = true;
        Schema::$already_checked = true;
    }

    public function testDropPartition()
    {
        DB::shouldReceive("statement")->with('ALTER TABLE foo DROP PARTITION test')->andReturn(true);
        Schema::deletePartition('foo', ['test']);
        $this->assertTrue(true);
    }

    public function testDropPartitions()
    {
        DB::shouldReceive("statement")->with('ALTER TABLE foo DROP PARTITION test, test1')->andReturn(true);
        Schema::deletePartition('foo', ['test', 'test1']);
        $this->assertTrue(true);
    }

    public function testAnalyzePartition()
    {
        DB::shouldReceive('select');
        DB::shouldReceive("raw")->with('ALTER TABLE foo ANALYZE PARTITION test');
        Schema::analyzePartitions('foo', ['test']);
        $this->assertTrue(true);
    }

    public function testAnalyzePartitions()
    {
        DB::shouldReceive('select');
        DB::shouldReceive("raw")->with('ALTER TABLE foo ANALYZE PARTITION test, test1')->andReturn(true);
        Schema::analyzePartitions('foo', ['test', 'test1']);
        $this->assertTrue(true);
    }

    public function testRepairPartition()
    {
        DB::shouldReceive('select');
        DB::shouldReceive("raw")->with('ALTER TABLE foo REPAIR PARTITION test');
        Schema::repairPartitions('foo', ['test']);
        $this->assertTrue(true);
    }

    public function testRepairPartitions()
    {
        DB::shouldReceive('select');
        DB::shouldReceive("raw")->with('ALTER TABLE foo REPAIR PARTITION test, test1')->andReturn(true);
        Schema::repairPartitions('foo', ['test', 'test1']);
        $this->assertTrue(true);
    }

    public function testRebuildPartition()
    {
        DB::shouldReceive('statement')->with('ALTER TABLE foo REBUILD PARTITION test');
        Schema::rebuildPartitions('foo', ['test']);
        $this->assertTrue(true);
    }

    public function testRebuildPartitions()
    {
        DB::shouldReceive('statement')->with('ALTER TABLE foo REBUILD PARTITION test, test1')->andReturn(true);
        Schema::rebuildPartitions('foo', ['test', 'test1']);
        $this->assertTrue(true);
    }

    public function testCheckPartition()
    {
        DB::shouldReceive('select');
        DB::shouldReceive("raw")->with('ALTER TABLE foo CHECK PARTITION test');
        Schema::checkPartitions('foo', ['test']);
        $this->assertTrue(true);
    }

    public function testCheckPartitions()
    {
        DB::shouldReceive('select');
        DB::shouldReceive("raw")->with('ALTER TABLE foo CHECK PARTITION test, test1')->andReturn(true);
        Schema::checkPartitions('foo', ['test', 'test1']);
        $this->assertTrue(true);
    }

    public function testOptimizePartition()
    {
        DB::shouldReceive('select');
        DB::shouldReceive("raw")->with('ALTER TABLE foo OPTIMIZE PARTITION test');
        Schema::optimizePartitions('foo', ['test']);
        $this->assertTrue(true);
    }

    public function testOptimizePartitions()
    {
        DB::shouldReceive('select');
        DB::shouldReceive("raw")->with('ALTER TABLE foo OPTIMIZE PARTITION test, test1')->andReturn(true);
        Schema::optimizePartitions('foo', ['test', 'test1']);
        $this->assertTrue(true);
    }

    public function testTruncatePartition()
    {
        DB::shouldReceive('statement')->with('ALTER TABLE foo TRUNCATE PARTITION test');
        Schema::truncatePartitionData('foo', ['test']);
        $this->assertTrue(true);
    }

    public function testTruncatePartitions()
    {
        DB::shouldReceive('statement')->with('ALTER TABLE foo TRUNCATE PARTITION test, test1')->andReturn(true);
        Schema::truncatePartitionData('foo', ['test', 'test1']);
        $this->assertTrue(true);
    }

    public function testPartitionByKey(){
        DB::shouldReceive('unprepared');
        DB::shouldReceive("raw")->with('ALTER TABLE foo PARTITION BY KEY() PARTITIONS 10;')->andReturn(true);
        Schema::partitionByKey('foo', 10);
        $this->assertTrue(true);
    }

    public function testPartitionByHash(){
        DB::shouldReceive('unprepared');
        DB::shouldReceive("raw")->with('ALTER TABLE foo PARTITION BY HASH(date) PARTITIONS 10;')->andReturn(true);
        Schema::partitionByHash('foo', 'date', 10);
        $this->assertTrue(true);
    }

    public function testPartitionByRange(){
        DB::shouldReceive('unprepared');
        DB::shouldReceive("raw")->with('ALTER TABLE foo PARTITION BY RANGE(date) (PARTITION anno2000 VALUES LESS THAN (2000),PARTITION anno2001 VALUES LESS THAN (2001),PARTITION anno2002 VALUES LESS THAN (2002),PARTITION anno2003 VALUES LESS THAN (2003), PARTITION future VALUES LESS THAN (MAXVALUE))')->andReturn(true);
        Schema::partitionByRange('foo', 'date', [
            new Partition('anno2000', Partition::RANGE_TYPE, 2000),
            new Partition('anno2001', Partition::RANGE_TYPE, 2001),
            new Partition('anno2002', Partition::RANGE_TYPE, 2002),
            new Partition('anno2003', Partition::RANGE_TYPE, 2003),
        ]);
        $this->assertTrue(true);
    }

    public function testPartitionByRangeExcludeFuture(){
        DB::shouldReceive('unprepared');
        DB::shouldReceive("raw")->with('ALTER TABLE foo PARTITION BY RANGE(date) (PARTITION anno2000 VALUES LESS THAN (2000),PARTITION anno2001 VALUES LESS THAN (2001),PARTITION anno2002 VALUES LESS THAN (2002),PARTITION anno2003 VALUES LESS THAN (2003))')->andReturn(true);
        Schema::partitionByRange('foo', 'date', [
            new Partition('anno2000', Partition::RANGE_TYPE, 2000),
            new Partition('anno2001', Partition::RANGE_TYPE, 2001),
            new Partition('anno2002', Partition::RANGE_TYPE, 2002),
            new Partition('anno2003', Partition::RANGE_TYPE, 2003),
        ], false);
        $this->assertTrue(true);
    }

    public function testPartitionByList(){
        DB::shouldReceive('unprepared');
        DB::shouldReceive("raw")->with('ALTER TABLE foo PARTITION BY LIST(id) (PARTITION server_east VALUES IN (1,43,65,12,56,73),PARTITION server_west VALUES IN (534,6422,196,956,22))')->andReturn(true);
        Schema::partitionByList('foo', 'id',
            [
                new Partition('server_east', Partition::LIST_TYPE, [1,43,65,12,56,73]),
                new Partition('server_west', Partition::LIST_TYPE, [534,6422,196,956,22])
            ]
        );
        $this->assertTrue(true);
    }

    public function testPartitionByOneYears(){
        DB::shouldReceive('unprepared');
        DB::shouldReceive("raw")->with('ALTER TABLE foo PARTITION BY RANGE(YEAR(date)) (PARTITION year2020 VALUES LESS THAN (2021), PARTITION future VALUES LESS THAN (MAXVALUE))')->andReturn(true);
        Schema::partitionByYears('foo', 'date', 2020, 2020);
        $this->assertTrue(true);
    }

    public function testPartitionByYears(){
        DB::shouldReceive('unprepared');
        DB::shouldReceive("raw")->with('ALTER TABLE foo PARTITION BY RANGE(YEAR(date)) (PARTITION year2019 VALUES LESS THAN (2020),PARTITION year2020 VALUES LESS THAN (2021), PARTITION future VALUES LESS THAN (MAXVALUE))')->andReturn(true);
        Schema::partitionByYears('foo', 'date', 2019, 2020);
        $this->assertTrue(true);
    }

    public function testPartitionByYearsAndMonths(){
        DB::shouldReceive('unprepared');
        DB::shouldReceive("raw")->with('ALTER TABLE foo PARTITION BY RANGE(YEAR(date)) SUBPARTITION BY HASH(MONTH(date)) ( PARTITION year2019 VALUES LESS THAN (2020)(SUBPARTITION dec2019, SUBPARTITION jan2019, SUBPARTITION feb2019, SUBPARTITION mar2019, SUBPARTITION apr2019, SUBPARTITION may2019, SUBPARTITION jun2019, SUBPARTITION jul2019, SUBPARTITION aug2019, SUBPARTITION sep2019, SUBPARTITION oct2019, SUBPARTITION nov2019 ),PARTITION year2020 VALUES LESS THAN (2021)(SUBPARTITION dec2020, SUBPARTITION jan2020, SUBPARTITION feb2020, SUBPARTITION mar2020, SUBPARTITION apr2020, SUBPARTITION may2020, SUBPARTITION jun2020, SUBPARTITION jul2020, SUBPARTITION aug2020, SUBPARTITION sep2020, SUBPARTITION oct2020, SUBPARTITION nov2020 ), PARTITION future VALUES LESS THAN (MAXVALUE) (SUBPARTITION `dec`, SUBPARTITION `jan`, SUBPARTITION `feb`, SUBPARTITION `mar`, SUBPARTITION `apr`, SUBPARTITION `may`, SUBPARTITION `jun`, SUBPARTITION `jul`, SUBPARTITION `aug`, SUBPARTITION `sep`, SUBPARTITION `oct`, SUBPARTITION `nov`) )')->andReturn(true);
        Schema::partitionByYearsAndMonths('foo', 'date', 2019, 2020);
        $this->assertTrue(true);
    }

    public function testPartitionByYearsAndMonthsExcludeFuture(){
        DB::shouldReceive('unprepared');
        DB::shouldReceive("raw")->with('ALTER TABLE foo PARTITION BY RANGE(YEAR(date)) SUBPARTITION BY HASH(MONTH(date)) ( PARTITION year2019 VALUES LESS THAN (2020)(SUBPARTITION dec2019, SUBPARTITION jan2019, SUBPARTITION feb2019, SUBPARTITION mar2019, SUBPARTITION apr2019, SUBPARTITION may2019, SUBPARTITION jun2019, SUBPARTITION jul2019, SUBPARTITION aug2019, SUBPARTITION sep2019, SUBPARTITION oct2019, SUBPARTITION nov2019 ),PARTITION year2020 VALUES LESS THAN (2021)(SUBPARTITION dec2020, SUBPARTITION jan2020, SUBPARTITION feb2020, SUBPARTITION mar2020, SUBPARTITION apr2020, SUBPARTITION may2020, SUBPARTITION jun2020, SUBPARTITION jul2020, SUBPARTITION aug2020, SUBPARTITION sep2020, SUBPARTITION oct2020, SUBPARTITION nov2020 ))')->andReturn(true);
        Schema::partitionByYearsAndMonths('foo', 'date', 2019, 2020, false);
        $this->assertTrue(true);
    }
}
