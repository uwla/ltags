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
     * @return Illuminate\Database\Eloquent\Model;
     */
    public function findByName($name): Model;

    /**
     * Find the given tags by name.
     *
     * @param  array<string> $names
     * @return Illuminate\Database\Eloquent\Model;
     */
    public function findManyByName($names): Collection;
}
