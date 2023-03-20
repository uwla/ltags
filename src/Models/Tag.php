<?php

namespace Uwla\Ltags\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Uwla\Ltags\Contracts\Tag as TagContract;

class Tag extends Model implements TagContract
{
    /**
     * Find the given tag by name.
     *
     * @param  string $name
     * @param  string $namespace
     * @return Illuminate\Database\Eloquent\Model;
     */
    public static function findByName($name, $namespace=null): Model
    {
        return Tag::where(['name' => $name, 'namespace' => $namespace])->where()->first();
    }

    /**
     * Find the given tags by name.
     *
     * @param  array<string> $names
     * @param  string        $namespace
     * @return Illuminate\Database\Eloquent\Model;
     */
    public static function findManyByName($names, $namespace=null): Collection
    {
        return Tag::whereIn('name', $names)->where('namespace', $namespace)->get();
    }
}
