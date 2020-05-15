<?php

namespace Brokenice\LaravelMysqlPartition\Models;

use Brokenice\LaravelMysqlPartition\Exceptions\UnexpectedValueException;

class Partition{

    const RANGE_TYPE = 'RANGE';
    const LIST_TYPE  = 'LIST';

    /**
     * @var string
     */
    public $name;

    /**
     * @var string HASH|LIST|KEY|RANGE
     */
    public $type;

    /**
     * @var mixed
     */
    public $value;

    public function __construct($name, $type, $value)
    {
        $this->name = $name;
        $this->type = $type;
        if ($this->type === 'RANGE' && !is_numeric($value)){
            throw new UnexpectedValueException('Value for range must be an integer');
        }
        if ($this->type === 'LIST' && !is_array($value)){
            throw new UnexpectedValueException('Value for list must be an array');
        }
        $this->value = $value;
    }

    /**
     * Convert this partition to sql
     * @return string
     */
    public function toSQL(){
        if ($this->type === 'RANGE') {
            return "PARTITION {$this->name} VALUES LESS THAN ({$this->value})";
        }

        if($this->type === 'LIST') {
            return "PARTITION {$this->name} VALUES IN (". implode(',', $this->value) . ")";
        }
        return '';
    }

}
