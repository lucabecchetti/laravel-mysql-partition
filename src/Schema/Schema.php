<?php

namespace Brokenice\LaravelMysqlPartition\Schema;

use Brokenice\LaravelMysqlPartition\Exceptions\UnexpectedValueException;
use Brokenice\LaravelMysqlPartition\Exceptions\UnsupportedPartitionException;
use Brokenice\LaravelMysqlPartition\Models\Partition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as IlluminateSchema;

/**
 * Class PartitionHelper method.
 */
class Schema extends IlluminateSchema
{

    public static $have_partitioning = false;
    public static $already_checked = false;

    // Array of months
    static protected $month = [
        12 => 'dec',
        1 => 'jan',
        2 => 'feb',
        3 => 'mar',
        4 => 'apr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'aug',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov'
    ];

    /**
     * returns array of partition names for a specific db/table
     *
     * @param string $db    database name
     * @param string $table table name
     *
     * @access  public
     * @return array of partition names
     */
    public static function getPartitionNames($db, $table)
    {
        self::assertSupport();
        return DB::select(DB::raw(
            "SELECT `PARTITION_NAME`, `SUBPARTITION_NAME`, `PARTITION_ORDINAL_POSITION`, `TABLE_ROWS`, `PARTITION_METHOD` FROM `information_schema`.`PARTITIONS`"
            . " WHERE `TABLE_SCHEMA` = '" . $db
            . "' AND `TABLE_NAME` = '" . $table . "'"
        ));
    }

    /**
     * checks if MySQL server supports partitioning
     *
     * @static
     * @staticvar boolean $have_partitioning
     * @staticvar boolean $already_checked
     * @access  public
     * @return boolean
     */
    public static function havePartitioning()
    {
        if (!self::$already_checked && version_compare(self::version(), 5.1, '>=')) {
            if (version_compare(self::version(), 5.6, '<')) {
                if (DB::connection()->getPdo()->query("SHOW VARIABLES LIKE 'have_partitioning';")->fetchAll()) {
                    self::$have_partitioning = true;
                }
            } else {
                // see http://dev.mysql.com/doc/refman/5.6/en/partitioning.html
                $plugins = DB::connection()->getPdo()->query("SHOW PLUGINS")->fetchAll();
                foreach ($plugins as $value) {
                    if ($value['Name'] === 'partition') {
                        self::$have_partitioning = true;
                        break;
                    }
                }
            }
            self::$already_checked = true;
        }
        return self::$have_partitioning;
    }

    /**
     * Implode array of partitions with comma
     * @param $partitions
     * @return string
     */
    private static function implodePartitions($partitions)
    {
        return collect($partitions)->map(static function($partition){
            return $partition->toSQL();
        })->implode(',');
    }

    /**
     * @param $table
     * @param $column
     * @param $startYear
     * @param null $endYear
     * @param bool $includeFuturePartition
     */
    public static function partitionByYearsAndMonths($table, $column, $startYear, $endYear = null, $includeFuturePartition = true)
    {
        self::assertSupport();
        $endYear = $endYear ?: date('Y');
        if ($startYear > $endYear){
            throw new UnexpectedValueException("$startYear must be lower than $endYear");
        }
        // Build partitions array for years range
        $partitions = [];
        foreach (range($startYear, $endYear) as $year) {
            $partitions[] = new Partition('year'.$year, Partition::RANGE_TYPE, $year+1);
        }
        // Build query
        $query = "ALTER TABLE {$table} PARTITION BY RANGE(YEAR({$column})) SUBPARTITION BY HASH(MONTH({$column})) ( ";
        $subPartitionsQuery = collect($partitions)->map(static function($partition) {
            return $partition->toSQL() . "(". collect(self::$month)->map(static function($month) use ($partition){
                return "SUBPARTITION {$month}".($partition->value-1);
            })->implode(', ') . ' )';
        });
        $query .= collect($subPartitionsQuery)->implode(',');
        // Include future partitions if needed
        if($includeFuturePartition) {
            $query .= ", PARTITION future VALUES LESS THAN (MAXVALUE) (";
            $query .= collect(self::$month)->map(static function ($month) {
                return "SUBPARTITION `{$month}`";
            })->implode(', ');
            $query .= ") )";
        } else {
            $query .= ")";
        }
        DB::unprepared(DB::raw($query));
    }

    /**
     * Partition table by range
     * # WARNING 1: A PRIMARY KEY must include all columns in the table's partitioning function
     * @param $table
     * @param $column
     * @param Partition[] $partitions
     * @param bool $includeFuturePartition
     *
     * @static public
     *
     */
    public static function partitionByRange($table, $column, $partitions, $includeFuturePartition = true)
    {
        self::assertSupport();
        $query = "ALTER TABLE {$table} PARTITION BY RANGE({$column}) (";
        $query .= self::implodePartitions($partitions);
        if($includeFuturePartition){
            $query .= ", PARTITION future VALUES LESS THAN (MAXVALUE)";
        }
        $query = trim(trim($query),',') . ')';
        DB::unprepared(DB::raw($query));

    }

    /**
     * @param $table
     * @param $column
     * @param $startYear
     * @param $endYear
     */
    public static function partitionByYears($table, $column, $startYear, $endYear = null)
    {
        $endYear = $endYear ?: date('Y');
        if ($startYear > $endYear){
            throw new UnexpectedValueException("$startYear must be lower than $endYear");
        }
        $partitions = [];
        foreach (range($startYear, $endYear) as $year) {
            $partitions[] = new Partition('year'.$year, Partition::RANGE_TYPE, $year+1);
        }
        self::partitionByRange($table, "YEAR($column)", $partitions, true);
    }

    /**
     * Partition table by list
     * # WARNING 1: A PRIMARY KEY must include all columns in the table's partitioning function
     * @param $table
     * @param $column
     * @param Partition[] $partitions
     * @static public
     *
     * @throws UnsupportedPartitionException
     */
    public static function partitionByList($table, $column, $partitions)
    {
        self::assertSupport();
        $query = "ALTER TABLE {$table} PARTITION BY LIST({$column}) (";
        $query .= self::implodePartitions($partitions);
        $query .= ')';
        DB::unprepared(DB::raw($query));
    }

    /**
     * Partition table by hash
     * # WARNING 1: A PRIMARY KEY must include all columns in the table's partitioning function
     * @param $table
     * @param $hashColumn
     * @param $partitionsNumber
     * @static public
     *
     */
    public static function partitionByHash($table, $hashColumn, $partitionsNumber)
    {
        self::assertSupport();
        $query = "ALTER TABLE {$table} PARTITION BY HASH({$hashColumn}) ";
        $query .= "PARTITIONS {$partitionsNumber};";
        DB::unprepared(DB::raw($query));
    }

    /**
     * Partition table by hash
     * # WARNING 1: Are used all primary and unique keys
     * @param $table
     * @param $partitionsNumber
     * @static public
     *
     */
    public static function partitionByKey($table, $partitionsNumber)
        {
            self::assertSupport();
            $query = "ALTER TABLE {$table} PARTITION BY KEY() ";
            $query .= "PARTITIONS {$partitionsNumber};";
            DB::unprepared(DB::raw($query));
        }

    /**
     * Check mysql version
     *
     * @static public
     * @return string
     */
    public static function version()
    {
        $pdo = DB::connection()->getPdo();
        return $pdo->query('select version()')->fetchColumn();
    }

    /**
     * Force field to be autoIncrement
     * @param $table
     * @param string $field
     */
    public static function forceAutoIncrement($table, $field = 'id')
    {
        DB::statement("ALTER TABLE {$table} MODIFY {$field} INTEGER NOT NULL AUTO_INCREMENT");
    }

    /**
     * Delete the rows of a partition without affecting the rest of the dataset in the table
     * @param $table
     * @param $partitions
     */
    public static function truncatePartitionData($table, $partitions)
    {
        DB::statement("ALTER TABLE {$table} TRUNCATE PARTITION " . implode(', ', $partitions));
    }

    /**
     * Delete the rows of a partition without affecting the rest of the dataset in the table
     * @param $table
     * @param $partitions
     */
    public static function deletePartition($table, $partitions)
    {
        DB::statement("ALTER TABLE {$table} DROP PARTITION " . implode(', ', $partitions));
    }

    /**
     * Rebuilds the partition; this has the same effect as dropping all records stored in the partition,
     * then reinserting them. This can be useful for purposes of defragmentation.
     * @param $table
     * @param string[] $partitions
     */
    public static function rebuildPartitions($table, $partitions)
    {
        DB::statement("ALTER TABLE {$table} REBUILD PARTITION " . implode(', ', $partitions));
    }

    /**
     * If you have deleted a large number of rows from a partition or if you have made many changes to a partitioned table
     * with variable-length rows (that is, having VARCHAR, BLOB, or TEXT columns), you can use this method
     * to reclaim any unused space and to defragment the partition data file.
     * @param $table
     * @param string[] $partitions
     * @return array
     */
    public static function optimizePartitions($table, $partitions)
    {
        return DB::select(DB::raw("ALTER TABLE {$table} OPTIMIZE PARTITION " . implode(', ', $partitions)));
    }

    /**
     * This reads and stores the key distributions for partitions.
     * @param $table
     * @param string[] $partitions
     * @return array
     */
    public static function analyzePartitions($table, $partitions)
    {
        return DB::select(DB::raw("ALTER TABLE {$table} ANALYZE PARTITION " . implode(', ', $partitions)));
    }

    /**
     * Normally, REPAIR PARTITION fails when the partition contains duplicate key errors. In MySQL 5.7.2 and later,
     * you can use ALTER IGNORE TABLE with this option, in which case all rows that cannot be moved due to the presence
     * of duplicate keys are removed from the partition (Bug #16900947).
     * @param $table
     * @param string[] $partitions
     * @return array
     */
    public static function repairPartitions($table, $partitions)
    {
        return DB::select(DB::raw("ALTER TABLE {$table} REPAIR PARTITION " . implode(', ', $partitions)));
    }

    /**
     * You can check partitions for errors in much the same way that you can use CHECK TABLE with non partitioned tables.
     * @param $table
     * @param string[] $partitions
     * @return array
     */
    public static function checkPartitions($table, $partitions)
    {
        return DB::select(DB::raw("ALTER TABLE {$table} CHECK PARTITION " . implode(', ', $partitions)));
    }

    /**
     * Assert support for partition
     * @throws UnsupportedPartitionException
     */
    private static function assertSupport()
    {
        if (!self::havePartitioning()) {
            throw new UnsupportedPartitionException('Partitioning is unsupported on your server version');
        }
    }
}
