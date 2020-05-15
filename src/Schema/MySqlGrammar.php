<?php

namespace Brokenice\LaravelMysqlPartition\Schema;

use  Illuminate\Database\Query\Grammars\MysqlGrammar as IlluminateMySqlGrammar;
use Illuminate\Database\Query\Builder;

class MySqlGrammar extends IlluminateMySqlGrammar
{
    /**
     * Compile the "from" portion of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $table
     * @return string
     */
    protected function compileFrom(Builder $query, $table)
    {
        $baseFrom = 'from '.$this->wrapTable($table);
        if ($query->hasPartitions()){
            return $baseFrom . $this->compilePartitions($query);
        }
        return $baseFrom;
    }

    /**
     * Compile the "partition" portion of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    protected function compilePartitions(Builder $query){
        return ' PARTITION ('.collect($query->getPartitions())->map(static function($partition){
            return "`{$partition}`";
        })->join(', ').')';
    }

}
