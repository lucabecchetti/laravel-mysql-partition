<?php

use Brokenice\LaravelMysqlPartition\MysqlConnection;
use Brokenice\LaravelMysqlPartition\Schema\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\App;

class MysqlConnectionTest extends TestCase
{
    private $mysqlConnection;

    protected function setUp(): void
    {
        $mysqlConfig = ['driver' => 'mysql', 'prefix' => 'prefix', 'database' => 'database', 'name' => 'foo'];
        $this->mysqlConnection = new MysqlConnection(new PDOStub(), 'database', 'prefix', $mysqlConfig);
    }

    public function testGetQueryBuilder()
    {
        $builder = $this->mysqlConnection->query();

        $this->assertInstanceOf(QueryBuilder::class, $builder);
    }
}

class PDOStub extends PDO
{
    public function __construct()
    {
    }
}
