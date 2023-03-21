<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Uwla\Ltags\Contracts\Taggable as TaggableContract;
use Uwla\Ltags\Traits\Taggable;

class Video extends Model implements TaggableContract
{
    use HasFactory, Taggable;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return VideoFactory::new();
    }
}
