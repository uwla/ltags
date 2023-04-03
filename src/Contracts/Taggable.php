<?php

namespace Uwla\Ltags\Contracts;

interface Taggable
{
    /**
     * Get the models tagged with any of the given tags.
     *
     * @param mixed     $tags
     * @param string    $namespace
     * @return Illuminate\Database\Eloquent\Collection
     */
    public static function taggedBy($tags, $depth=1, $namespace=null);

    /**
     * Get the models tagged with all of the given tags.
     *
     * @param mixed     $tags
     * @param string    $namespace
     * @return Illuminate\Database\Eloquent\Collection
     */
    public static function taggedByAll($tags, $depth=1, $namespace=null);

    /**
     * Attach the corresponding tags to the given models
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    public static function withTags($models);

    /**
     * Attach the tag to the given models
     *
     * @param  mixed $tag
     * @param  \Illuminate\Database\Eloquent\Collection|array $models
     * @return void
     */
    public static function addTagTo($tag, $models);

    /**
     * Attach the tags to the given models
     *
     * @param  mixed $tag
     * @param  \Illuminate\Database\Eloquent\Collection|array $models
     * @return void
     */
    public static function addTagsTo($tags, $models);

    /**
     * Delete the association between the given tag and the given models.
     *
     * @param  mixed $tags
     * @param  \Illuminate\Database\Eloquent\Collection|array $models
     * @return void
     */
    public static function delTagFrom($tags, $models);

    /**
     * Delete the association between the given tags and the given models.
     *
     * @param  mixed $tags
     * @param  \Illuminate\Database\Eloquent\Collection|array $models
     * @return void
     */
    public static function delTagsFrom($tags, $models);

    /**
     * Delete all the tags associated with the given models.
     *
     * @param  mixed $tags
     * @param  \Illuminate\Database\Eloquent\Collection|array $models
     * @return void
     */
    public static function delAllTagsFrom($models);

    /**
     * Get the tag namespace for this model.
     *
     * @return string
     */
    public function getTagNamespace();

    /**
     * Get the tags associated with this model
     *
     * @param int $depth=1 The depth of the search for nested tags.
     * @return Illuminate\Database\Eloquent\Collection<Tag>
     */
    public function getTags($depth=1);

    /**
     * Get the tags associated with this model that match a pattern.
     *
     * @param string    $pattern    The pattern to search for.
     * @param int       $depth=1    The depth of the search for nested tags.
     * @return Illuminate\Database\Eloquent\Collection<Tag>
     */
    public function getTagsMatching($pattern, $depth=1);

    /**
     * Add the tag to this model
     *
     * @param  mixed $tag
     * @return void
     */
    public function addTag($tag);

    /**
     * Add the tags to this model
     *
     * @param  mixed $tags
     * @return void
     */
    public function addTags($tags);

    /**
     * Add the given tags to this model, deleting previous ones
     *
     * @param  mixed $tags
     * @return void
     */
    public function setTags($tags);

    /**
     * Indicates whether this model has the given tag.
     *
     * @param  mixed    $tag        Tag or name string.
     * @param  int      $depth=1    The depth of the search for nested tags.
     * @return bool
     */
    public function hasTag($tag, $depth=1);

    /**
     * Indicates whether this model has the given tags.
     *
     * @param  mixed    $tag        Tag or name string.
     * @param  int      $depth=1    The depth of the search for nested tags.
     * @return bool
     */
    public function hasTags($tags, $depth=1);

    /**
     * Indicates whether this model has at least one of the given tags.
     *
     * @param  mixed    $tag        Tag or name string.
     * @param  int      $depth=1    The depth of the search for nested tags.
     * @return bool
     */
    public function hasAnyTags($tags, $depth=1);

    /**
     * Delete the association between the given tag and this model.
     *
     * @param  mixed $tag
     * @return void
     */
    public function delTag($tag);

    /**
     * Delete the association between the given tags and this model.
     *
     * @param  mixed $tags
     * @return void
     */
    public function delTags($tags);

    /**
     * Delete the tags of this model that match the pattern
     *
     * @param  string $pattern
     * @return void
     */
    public function delTagsMatching($pattern);

    /**
     * Delete all tags associated with this model
     *
     * @param  mixed $tags
     * @return void
     */
    public function delAllTags();
}
