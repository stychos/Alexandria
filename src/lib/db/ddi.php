<?php

namespace alexandria\lib\db;

interface ddi
{
    const result_assoc   = 0;
    const result_numeric = 1;
    const result_first   = 2;
    const result_shot    = 3;
    const result_object  = 5;

    public function __construct($args);

    public function driver();

    public function &query(string $query, array $args = [], int $mode = self::result_object, string $cast = '\\stdClass');

    public function first(string $query, array $args = [], int $mode = self::result_object, string $cast = '\\stdClass');

    public function shot(string $query, array $args = []);

    public function id();

    public function last_query(): string;

    public function quote($value, $type = null);

    public function table_exists(string $table): bool;
}
