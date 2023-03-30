<?php

namespace Uwla\Ltags\Traits;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Uwla\Ltags\Models\Tag;
use Uwla\Ltags\Models\Taggable as TaggableModel;
use Uwla\Ltags\Contracts\Tag as TagContract;

Trait Taggable
{
    /**
     * Max depth allowed when searching nested tags. Default is 6.
     * Developers may want to override it.
     *
     * @return int
     */
    protected function maxTagDepth()
    {
        return 6;
    }

    /**
     * Get the id column of this model
     *
     * @return int
     */
    protected static function getModelIdColumn()
    {
        return 'id';
    }

    /**
     * Get the tag namspace for this model. Default is null.
     *
     * @return string
     */
    public function getTagNamespace()
    {
        return null;
    }

    /**
     * Get the models tagged with the given tags.
     *
     * @param mixed     $tags
     * @param string    $namespace
     * @return Illuminate\Database\Eloquent\Collection
     */
    public static function taggedBy($tags, $depth=1, $namespace=null)
    {
        // single tag
        if (is_string($tags) or ($tags instanceof Tag))
            $tags = [$tags];

        // validate tags
        $tags = self::validateTags($tags, $namespace);
        $tag_ids = $tags->pluck('id')->toArray();

        if ($depth > 1)
        {
            foreach($tags as $tag)
            {
                $nested_tags = $tag->getTags($depth-1);
                $other_ids = $nested_tags->pluck('id')->toArray();
                $tag_ids = array_merge($tag_ids, $other_ids);
            }
        }

        $model_ids = TaggableModel::select('model_id')
            ->where('model', self::class)
            ->whereIn('tag_id', $tag_ids)
            ->get()->pluck('model_id')->toArray();

        return self::whereIn(self::getModelIdColumn(), $model_ids)->get();
    }

    /**
     * Attach the corresponding tags to the given models
     *
     * @param  Illuminate\Database\Eloquent\Collection $models
     * @return Illuminate\Database\Eloquent\Collection
     */
    public static function withTags($models)
    {
        $id2model = []; // hashmap ID -> model
        $ids = [];
        $id_column = self::getModelIdColumn();
        foreach ($models as $model)
        {
            $id = $model->{$id_column};
            $id2model[$id] = $model;
            $ids[] = $id;
            $model->tags = new Collection(); // no tag initially
        }

        // get the association of tag & model
        $tagged = TaggableModel::query()
            ->where('model', self::class)
            ->whereIn('model_id', $ids)
            ->get();

        // get tags by their ids
        $tag_ids = $tagged->pluck('tag_id')->unique()->toArray();
        $tags = Tag::whereIn('id', $tag_ids)->get();

        $id2tag = []; // hash map ID -> tag
        foreach($tags as $tag)
            $id2tag[$tag->id] = $tag;

        // add the tags to the models, efficiently using the hashmaps
        foreach ($tagged as $tagged_model)
        {
            $model_id = $tagged_model->model_id;
            $tag_id = $tagged_model->tag_id;
            $model = $id2model[$model_id];
            $tag = $id2tag[$tag_id];
            $model->tags->add($tag);
        }

        return $models;
    }

    /**
     * Get the tags associated with this model
     *
     * @param int $depth=1 The depth of the search for nested tags.
     * @return Illuminate\Database\Eloquent\Collection<Tag>
     */
    public function getTags($depth=1)
    {
        $this->validateDepth($depth);

        $ids = TaggableModel::select('tag_id')->where([
            'model_id' => $this->id,
            'model' => $this::class
        ])->pluck('tag_id')->toArray();
        $tags = Tag::whereIn('id', $ids)->get();

        if ($depth==1)
            return $tags;

        foreach($tags as $tag)
        {
            $nested_tags = $tag->getTags($depth-1);
            $other_ids = $nested_tags->pluck('id')->toArray();
            $ids = array_merge($ids, $other_ids);
        }
        $ids = array_unique($ids);
        return Tag::whereIn('id', $ids)->get();
    }

    /**
     * Get the tags associated with this model that match a pattern.
     *
     * @param string    $pattern    The pattern to search for.
     * @param int       $depth=1    The depth of the search for nested tags.
     * @return Illuminate\Database\Eloquent\Collection<Tag>
     */
    public function getTagsMatching($pattern, $depth=1)
    {
        $tags = $this->getTags($depth);
        $filtered = $tags->filter(fn($tag) =>  Str::isMatch($pattern, $tag->name));
        return $filtered;
    }

    /**
     * Add the tag to this model
     *
     * @param  mixed $tag
     * @return void
     */
    public function addTag($tag)
    {
        $this->addTags([$tag]);
    }

    /**
     * Add the tags to this model
     *
     * @param  mixed $tags
     * @return void
     */
    public function addTags($tags)
    {
        $tags = $this::validateTags($tags, $this->getTagNamespace());

        // insert the tags
        $tagged = [];
        foreach ($tags as $tag)
        {
            $tagged[] = [
                'tag_id' => $tag->id,
                'model_id' => $this->id,
                'model' => $this::class
            ];
        }
        TaggableModel::insert($tagged);
    }

    /**
     * Add the given tags to this model, deleting previous ones
     *
     * @param  mixed $tags
     * @return void
     */
    public function setTags($tags)
    {
        $this->delAllTags();
        $this->addTags($tags);
    }

    /**
     * Indicates whether this model has the given tag.
     *
     * @param  mixed    $tag        Tag or name string.
     * @param  int      $depth=1    The depth of the search for nested tags.
     * @return bool
     */
    public function hasTag($tag, $depth=1)
    {
        return $this->hasTags([$tag], $depth);
    }

    /**
     * Indicates whether this model has the given tags.
     *
     * @param  mixed    $tag        Tag or name string.
     * @param  int      $depth=1    The depth of the search for nested tags.
     * @return bool
     */
    public function hasTags($tags, $depth=1)
    {
        $n = $this->hasHowManyTags($tags, $depth);
        $m = count($tags);
        return $n == $m;
    }

    /**
     * Indicates whether this model has at least one of the given tags.
     *
     * @param  mixed    $tag        Tag or name string.
     * @param  int      $depth=1    The depth of the search for nested tags.
     * @return bool
     */
    public function hasAnyTags($tags, $depth=1)
    {
        $n = $this->hasHowManyTags($tags, $depth);
        return $n >= 1;
    }

    /**
     * Delete the association between the given tag and this model.
     *
     * @param  mixed $tag
     * @return void
     */
    public function delTag($tag)
    {
        $this->delTags([$tag]);
    }

    /**
     * Delete the association between the given tags and this model.
     *
     * @param  mixed $tags
     * @return void
     */
    public function delTags($tags)
    {
        $tags = $this::validateTags($tags, $this->getTagNamespace());
        $ids = $tags->pluck('id')->toArray();

        // delete the tags
        TaggableModel::whereIn('tag_id', $ids)->where([
            'model_id' => $this->id,
            'model' => $this::class
        ])->delete();
    }

    /**
     * Delete the tags of this model that match the pattern
     *
     * @param  string $pattern
     * @return void
     */
    public function delTagsMatching($pattern)
    {
        $tags = $this->getTagsMatching($pattern);

        // no tags found
        if ($tags->count() == 0)
            return;

        // found some tags
        $ids = $tags->pluck('id')->toArray();

        // delete the tags
        TaggableModel::whereIn('tag_id', $ids)->where([
            'model_id' => $this->id,
            'model' => $this::class
        ])->delete();
    }

    /**
     * Delete all tags associated with this model
     *
     * @param  mixed $tags
     * @return void
     */
    public function delAllTags()
    {
        $ids = TaggableModel::where([
            'model' => $this::class,
            'model_id' => $this->id,
        ])->get()->pluck('tag_id')->toArray();
        Tag::whereIn('id', $ids)->delete();
    }

    // -------------------------------------------------------------------------
    // HELPERS


    // helper to get how many tags of the given tags this model has
    private function hasHowManyTags($tags, $depth)
    {
        $tags = $this::validateTags($tags, $this->getTagNamespace());
        $this_tags = $this->getTags($depth);

        // we will get the ids of the given tags
        // and also the ids of the this model's tags
        $a = $tags->pluck('id')->toArray();
        $b = $this_tags->pluck('id')->toArray();

        // then, we will perform an efficient search to
        // count how many items from subset A are in subset B too
        sort($a);
        sort($b);
        $counter = 0;
        $la = count($a);
        $lb = count($b);
        $i = 0; $j = 0;
        while ($i < $la && $j < $lb) // linear time
        {
            if ($a[$i] === $b[$j])
            {
                $i += 1;
                $j += 1;
                $counter += 1;
            } else if ($a[$i] > $b[$j]) {
                $j += 1;
            } else {
                $i += 1;
            }
        }
        // the algorithm above complexity is upper-bounded by `sort`,
        // which is likely to be O(n log(n)).

        return $counter;
    }

    // depth validation helper
    private function validateDepth($depth)
    {
        if ($depth < 1)
            throw new Exception("Depth cannot be less than 1");
        if ($depth > $this->maxTagDepth())
            throw new Exception("Depth value is too high");
    }

    // tag validation helper
    private static function validateTags($tags, $namespace): Collection
    {
        if (! is_countable($tags))
            throw new Exception("Expected a iterable value.");
        if (count($tags) < 1)
            throw new Exception("Got empty tags.");

        // get the tag models if string
        if (gettype($tags[0]) == 'string')
        {
            $tags = Tag::findManyByName($tags, $namespace);
            if ($tags->count() < 1)
                throw new Exception("The provided tags do not exist.");
        }

        // check instance type
        if (! $tags[0] instanceof TagContract)
            throw new Exception("Tag provided must be string or Eloquent model");

        if (is_array($tags))
            $tags = collect($tags);

        return $tags;
    }
}
