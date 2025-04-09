<?php

namespace Brokenice\LaravelMysqlPartition;

use Brokenice\LaravelMysqlPartition\Schema\MySqlGrammar;
use Brokenice\LaravelMysqlPartition\Schema\QueryBuilder;
use Illuminate\Database\MySqlConnection as IlluminateMySqlConnection;

class MysqlConnection extends IlluminateMySqlConnection
{

    /**
     * Get a new query builder instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function query() {
        return new QueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        $grammar = new MySqlGrammar($this);
        if (method_exists($grammar, 'setConnection')) {
            $grammar->setConnection($this);
        }
        $this->setQueryGrammar($grammar)->setTablePrefix($this->tablePrefix);
        return $grammar;
    }


}
