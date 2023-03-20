<?php

namespace Uwla\Ltags\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

interface Tag
{
    /**
     * Find the given tag by name.
     *
     * @param  string $name
     * @param  string $namespace=null
     * @return Illuminate\Database\Eloquent\Model;
     */
    public static function findByName($name, $namespace=null): Model;

    /**
     * Find the given tags by name.
     *
     * @param  array<string> $names
     * @param  string        $namespace=null
     * @return Illuminate\Database\Eloquent\Model;
     */
    public static function findManyByName($names, $namespace=null): Collection;
}
