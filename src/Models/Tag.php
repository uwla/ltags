<?php

namespace Uwla\Ltags\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Uwla\Ltags\Contracts\Tag as TagContract;
use Uwla\Ltags\Traits\Taggable;

class Tag extends Model implements TagContract
{
    use Taggable;

    /**
      * The attributes that should be excluded from mass assignment.
      *
      * @var array<string>
      */
    protected $guarded = [];

    /**
     * Find the given tag by name.
     *
     * @param  string $name
     * @param  string $namespace
     * @return Illuminate\Database\Eloquent\Model
     */
    public static function findByName($name, $namespace=null): Model
    {
        return Tag::where(['name' => $name, 'namespace' => $namespace])->first();
    }

    /**
     * Find the given tags by name.
     *
     * @param  array<string> $names
     * @param  string        $namespace
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findManyByName($names, $namespace=null): Collection
    {
        return Tag::whereIn('name', $names)->where('namespace', $namespace)->get();
    }

    /**
     * Create a single tag by the given name.
     *
     * @param  string $name
     * @param  string $namespace=null
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function createOne($name, $namespace=null): Model
    {
        return Tag::create(['name' => $name, 'namespace' => $namespace]);
    }

    /**
     * Create many tags by the given names.
     *
     * @param  array<string> $names
     * @param  string        $namespace=null
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createMany($names, $namespace=null): Collection
    {
        $attr = [];
        foreach ($names as $name)
            $attr[] = ['name' => $name, 'namespace' => $namespace];
        Tag::insert($attr);
        return Tag::whereIn('name', $names)->where('namespace', $namespace)->get();
    }

    /**
     * Delete the given tags by name.
     *
     * @param  string|array<string>  $name
     * @param  string                $namespace=null
     * @return void
     */
    public static function del($names, $namespace=null)
    {
        if (is_string($names))
            $names = [$names];
        if (! is_array($names))
            throw new Exception("First argument must be string or string array");
        Tag::where('namespace', $namespace)->whereIn('name', $names)->delete();
    }
}
