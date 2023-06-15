<?php

namespace Uwla\Ltags\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
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
     * Find the given tag or tags by name.
     *
     * @param  string|array<string> $name
     * @param  string               $namespace
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection
     */
    public static function findByName($name, $namespace=null)
    {
        if (is_string($name))
            return self::where(['name' => $name, 'namespace' => $namespace])->first();
        else if (is_array($name))
            return self::whereIn('name', $name)->where('namespace', $namespace)->get();
        throw new Exception('Name should be string or string array');
    }

    /**
     * Create a single tag name.
     *
     * @param  string|array<string> $name
     * @param  string               $namespace
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function createOne($name, $namespace=null)
    {
        return self::createByName($name, $namespace);
    }

    /**
     * Create many tags by name.
     *
     * @param  array<string> $names
     * @param  string        $namespace
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createMany($names, $namespace=null)
    {
        return self::createByName($names, $namespace);
    }

    /**
     * Create a single tag by the given name.
     *
     * @param  string|array<string> $name
     * @param  string               $namespace
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection
     */
    public static function createByName($name, $namespace=null)
    {
        if (is_string($name)) {
            return self::create(['name' => $name, 'namespace' => $namespace]);
        } else if (is_array($name)) {
            $names = $name;
            $attr = [];
            foreach ($names as $name)
                $attr[] = ['name' => $name, 'namespace' => $namespace];
            self::insert($attr);
            return self::findByName($names, $namespace);
        }
        throw new Exception('Name should be string or string array');
    }

    /**
     * Delete the given tags by name.
     *
     * @param  string|array<string>  $name
     * @param  string                $namespace=null
     * @return void
     */
    public static function delByName($names, $namespace=null)
    {
        if (is_string($names))
            $names = [$names];
        if (! is_array($names))
            throw new Exception('First argument must be string or string array');
        self::where('namespace', $namespace)->whereIn('name', $names)->delete();
    }
}
