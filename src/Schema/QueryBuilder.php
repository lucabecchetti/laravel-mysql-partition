<?php
namespace Brokenice\LaravelMysqlPartition\Schema;


class QueryBuilder extends \Illuminate\Database\Query\Builder {

    /**
     * The partitions which the query is targeting.
     *
     * @var string[]
     */
    private $partitions = [];

    /**
     * DB name to override inside a query
     *
     * @var string
     */
    private $databaseName = null;

    /**
     * Add a "partition" clause to the query.
     * @param array $partitions
     * @return $this
     */
    public function partitions($partitions) {
        $this->partitions = $partitions;
        return $this;
    }

    /**
     * Add a "partition" clause to the query.
     * @param array $partition
     * @return $this
     */
    public function partition($partition) {
        $this->partitions = [$partition];
        return $this;
    }

    /**
     * Set database name
     * @param $name
     * @return $this
     */
    public function db($name)
    {
        $this->databaseName = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDb()
    {
        return $this->databaseName;
    }

    /**
     * @return string[]
     */
    public function getPartitions()
    {
        return $this->partitions;
    }

    /**
     * Check if partitions did set
     * @return bool
     */
    public function hasPartitions(){
        return count($this->partitions) > 0;
    }

}
