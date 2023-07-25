<?php

namespace Uwla\Ltags\Contracts;

interface TagContract
{
    /**
     * Find the given tag or tags by name.
     *
     * @param  string|array<string> $name
     * @param  string               $namespace=null
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection
     */
    public static function findByName($name, $namespace=null);

    /**
     * Create a single tag name.
     *
     * @param  string|array<string> $name
     * @param  string               $namespace
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function createOne($name, $namespace=null);

    /**
     * Create many tags by name.
     *
     * @param  array<string> $names
     * @param  string        $namespace
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createMany($names, $namespace=null);

    /**
     * Create a tag or tags by the given name.
     *
     * @param  string|array<string> $name
     * @param  string               $namespace=null
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection
     */
    public static function createByName($name, $namespace=null);

    /**
     * Delete the given tags by name.
     *
     * @param  string|array<string>  $name
     * @param  string                $namespace=null
     * @return void
     */
    public static function delByName($name, $namespace=null);
}
