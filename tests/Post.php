<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Uwla\Ltags\Contracts\Taggable as TaggableContract;
use Uwla\Ltags\Traits\Taggable;

class Post extends Model implements TaggableContract
{
    use HasFactory, Taggable;

    /**
     * namespace of the model's tags
     *
     * @var string
     */
    public $tagNamespace = null;

    /**
     * Get the namespace of the tags associated with this model
     *
     * @return string
     */
    public function getTagNamespace()
    {
        return $this->tagNamespace;
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return PostFactory::new();
    }
}
