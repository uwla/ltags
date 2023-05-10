<?php

namespace Uwla\Ltags\Traits;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
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
     * Get the id column of this model.
     * Developers may want to override it.
     *
     * @return int
     */
    protected static function getModelIdColumn()
    {
        return 'id';
    }

    /**
     * Get the class of the Tag model.
     * Developers may want to override it.
     *
     * @return class
     */
    protected static function getTagClass()
    {
        return Tag::class;
    }

    /**
     * Get the class of the Tagged model.
     * Developers may want to override it.
     *
     * @return class
     */
    protected static function getTaggedClass()
    {
        return TaggableModel::class;
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
     * Attach the tag to the given models
     *
     * @param  mixed $tag
     * @param  \Illuminate\Database\Eloquent\Collection|array $models
     * @return void
     */
    public static function addTagTo($tag, $models)
    {
        self::addTagsTo([$tag], $models);
    }

    /**
     * Attach the tags to the given models
     *
     * @param  mixed $tag
     * @param  \Illuminate\Database\Eloquent\Collection|array $models
     * @return void
     */
    public static function addTagsTo($tags, $models)
    {
        $tags = self::validateTags($tags);
        $id_column = self::getModelIdColumn();
        $tagged = [];
        foreach ($tags as $tag)
        {
            foreach ($models as $model)
            {
                $tagged[] = [
                    'tag_id' => $tag->id,
                    'model_id' => $model->{$id_column},
                    'model' => self::class
                ];
            }
        }

        self::getTaggedClass()::insert($tagged);
    }

    /**
     * Delete the association between the given tag and the given models.
     *
     * @param  mixed $tags
     * @param  \Illuminate\Database\Eloquent\Collection|array $models
     * @return void
     */
    public static function delTagFrom($tag, $models)
    {
        self::delTagsFrom([$tag], $models);
    }

    /**
     * Delete the association between the given tags and the given models.
     *
     * @param  mixed $tags
     * @param  \Illuminate\Database\Eloquent\Collection|array $models
     * @return void
     */
    public static function delTagsFrom($tags, $models)
    {
        $tags = self::validateTags($tags);
        $tag_ids = $tags->pluck('id');
        $model_ids = $models->pluck(self::getModelIdColumn());
        self::getTaggedClass()::query()
            ->whereIn('tag_id', $tag_ids)
            ->whereIn('model_id', $model_ids)
            ->where('model', self::class)
            ->delete();
    }

    /**
     * Delete the association between the tags associated with the given models.
     *
     * @param  mixed $tags
     * @param  \Illuminate\Database\Eloquent\Collection|array $models
     * @return void
     */
    public static function delAllTagsFrom($models)
    {
        $model_ids = $models->pluck(self::getModelIdColumn());
        self::getTaggedClass()::query()
            ->whereIn('model_id', $model_ids)
            ->where('model', self::class)
            ->delete();
    }

    /**
     * Get the models tagged with at least one of the given tags.
     *
     * @param mixed     $tags
     * @param string    $namespace
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function taggedBy($tags, $depth=1, $namespace=null)
    {
        // single tag
        if (is_string($tags) or ($tags instanceof Tag))
            $tags = [$tags];

        // validate tags
        $tags = self::validateTags($tags, $namespace);
        $tag_ids = $tags->pluck('id');

        if ($depth > 1)
        {
            $nested_tags = self::getTagClass()::taggedBy($tags, $depth - 1);
            $other_ids = $nested_tags->pluck('id');
            $tag_ids = $tag_ids->merge($other_ids);
        }

        $model_ids = self::getTaggedClass()::select('model_id')
            ->where('model', self::class)
            ->whereIn('tag_id', $tag_ids)
            ->get()->pluck('model_id');

        return self::whereIn(self::getModelIdColumn(), $model_ids)->get();
    }

    /**
     * Get the models not tagged by any of the given tags.
     *
     * @param mixed     $tags
     * @param int       $depth
     * @param string    $namespace
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function notTaggedBy($tags, $depth=1, $namespace=null)
    {
         // single tag
        if (is_string($tags) or ($tags instanceof Tag))
            $tags = [$tags];

        // validate tags
        $tags = self::validateTags($tags, $namespace);
        $tag_ids = $tags->pluck('id');

        if ($depth > 1)
        {
            $nested_tags = self::getTagClass()::taggedBy($tags, $depth - 1);
            $other_ids = $nested_tags->pluck('id');
            $tag_ids = $tag_ids->merge($other_ids);
        }

        $model_ids = self::getTaggedClass()::select('model_id')
            ->where('model', self::class)
            ->whereIn('tag_id', $tag_ids)
            ->get()->pluck('model_id');

        return self::whereNotIn(self::getModelIdColumn(), $model_ids)->get();
    }

    /**
     * Get the models tagged with at least one of the given tags.
     *
     * @param mixed     $tags
     * @param string    $namespace
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function taggedByAll($tags, $depth=1, $namespace=null)
    {
        // single tag
        if (is_string($tags) or ($tags instanceof Tag))
            $tags = [$tags];

        // validate tags
        $tags = self::validateTags($tags, $namespace);

        // if there are many tags or if the depth is high, then
        // the code block below for depth>1 becomes very slow
        if ($depth > 1)
        {
            $models = self::taggedBy($tags->first(), $depth, $namespace);
            foreach ($tags->skip(1) as $tag)
            {
                if ($models->isEmpty()) break;
                $models = $models->intersect(self::taggedBy($tag, $depth, $namespace));
            }
            return $models;
        }

        // otherwise, if depth == 1 the code is efficient, despite many tags

        // get the tagged information
        $tag_ids = $tags->pluck('id');
        $tagged = self::getTaggedClass()::query()
            ->where('model', self::class)
            ->whereIn('tag_id', $tag_ids)
            ->get();

        // count how many tags each tagged model has
        $counter = [];
        foreach ($tagged as $tagged_model)
        {
            $id = $tagged_model->model_id;
            if (array_key_exists($id, $counter))
                $counter[$id] += 1;
            else
                $counter[$id] = 1;
        }

        // collect the id of the models that have all the tags
        $model_ids = [];
        $n = $tags->count();
        foreach ($counter as $key => $value)
        {
            if ($value == $n)
                $model_ids[] = $key;
        }

        // return the models that have all the tags
        return self::whereIn(self::getModelIdColumn(), $model_ids)->get();
    }

    /**
     * Attach the corresponding tags to the given models
     *
     * @param  \Illuminate\Database\Eloquent\Collection $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function withTags($models)
    {
        return self::withTagsMapped($models, fn($tag) => $tag);
    }

    /**
     * Attach the corresponding tag names to the given models
     *
     * @param  \Illuminate\Database\Eloquent\Collection $models
     * @param  callable $mapper
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function withTagNames($models)
    {
        return self::withTagsMapped($models, fn($tag) => $tag->name);
    }

    /**
     * Return a map tag name -> models
     *
     * @param  \Illuminate\Database\Eloquent\Collection $models
     * @param  \Illuminate\Database\Eloquent\Collection|array<string> $tags
     * @return array
     */
    public static function byTags($models, $tags=[])
    {
        // if the user did not provide tags,
        // then use the tags of the models
        if (count($tags) == 0)
        {
            $models = self::withTagNames($models);
            $map = [];
            foreach ($models as $model)
            {
                foreach ($model->tags as $tag)
                {
                    if (array_key_exists($tag, $map))
                        $map[$tag][] = $model; // append syntax
                    else
                        $map[$tag] = [$model];
                }
                unset($model->tags);
            }
            return $map;
        }

        // otherwise, use the provided tags
        $tags = self::validateTags($tags);

        $id2tag_name = [];
        foreach ($tags as $tag)
            $id2tag_name[$tag->id] = $tag->name;

        $idcol = self::getModelIdColumn();
        $id2model  = [];
        foreach ($models as $model)
            $id2model[$model->{$idcol}] = $model;

        $map = [];
        $tids = $tags->pluck('id');
        $mids = $models->pluck($idcol);
        $tagged = self::getTaggedClass()::query()
            ->whereIn('tag_id', $tids)
            ->whereIn('model_id', $mids)
            ->where('model', self::class)
            ->get();
        foreach ($tagged as $t)
        {
            $mid = $t->model_id;
            $tid = $t->tag_id;
            $tag_name = $id2tag_name[$tid];
            $model = $id2model[$mid];
            if (! array_key_exists($tag_name, $map))
                $map[$tag_name] = [];
            $map[$tag_name][] = $model;
        }
        return $map;
    }

    /**
     * Attach the corresponding tags to the given models, mapping the tags
     *
     * @param  \Illuminate\Database\Eloquent\Collection $models
     * @param  callable $mapper
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function withTagsMapped($models, $mapper=null)
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
        $tagged = self::getTaggedClass()::query()
            ->where('model', self::class)
            ->whereIn('model_id', $ids)
            ->get();

        // get tags by their ids
        $tag_ids = $tagged->pluck('tag_id')->unique();
        $tags = self::getTagClass()::whereIn('id', $tag_ids)->get();

        $id2tag = []; // hash map ID -> tag
        foreach ($tags as $tag)
            $id2tag[$tag->id] = $tag;

        // the mapper will map a tag to a value.
        if (! is_callable($mapper))
            throw new InvalidArgumentException("Second param must be callable");

        // add the tags to the models, efficiently using the hashmaps
        foreach ($tagged as $tagged_model)
        {
            $model_id = $tagged_model->model_id;
            $tag_id = $tagged_model->tag_id;
            $model = $id2model[$model_id];
            $tag = $id2tag[$tag_id];
            $model->tags->add($mapper($tag));
        }

        return $models;
    }

    /**
     * Get the tags associated with this model
     *
     * @param int $depth=1 The depth of the search for nested tags.
     * @return \Illuminate\Database\Eloquent\Collection<Tag>
     */
    public function getTags($depth=1)
    {
        $this->validateDepth($depth);

        $ids = self::getTaggedClass()::select('tag_id')->where([
            'model_id' => $this->id,
            'model' => $this::class
        ])->pluck('tag_id');
        $tags = self::getTagClass()::whereIn('id', $ids)->get();

        if ($depth==1)
            return $tags;

        foreach($tags as $tag)
        {
            $nested_tags = $tag->getTags($depth-1);
            $other_ids = $nested_tags->pluck('id');
            $ids = $ids->merge($other_ids);
        }
        $ids = $ids->unique();
        return self::getTagClass()::whereIn('id', $ids)->get();
    }

    /**
     * Get the name of the tags associated with this model
     *
     * @param int $depth=1 The depth of the search for nested tags.
     * @return array
     */
    public function getTagNames($depth=1)
    {
        return $this->getTags($depth)->pluck('name')->toArray();
    }

    /**
     * Get the tags associated with this model that match a pattern.
     *
     * @param string    $pattern    The pattern to search for.
     * @param int       $depth=1    The depth of the search for nested tags.
     * @return \Illuminate\Database\Eloquent\Collection<Tag>
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
        self::getTaggedClass()::insert($tagged);
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
        $ids = $tags->pluck('id');

        // delete the tags
        self::getTaggedClass()::whereIn('tag_id', $ids)->where([
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
        $ids = $tags->pluck('id');

        // delete the tags
        self::getTaggedClass()::whereIn('tag_id', $ids)->where([
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
        $ids = self::getTaggedClass()::where([
            'model' => $this::class,
            'model_id' => $this->id,
        ])->get()->pluck('tag_id');
        self::getTagClass()::whereIn('id', $ids)->delete();
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
            throw new Exception('Depth cannot be less than 1');
        if ($depth > $this->maxTagDepth())
            throw new Exception('Depth value is too high');
    }

    // tag validation helper
    private static function validateTags($tags, $namespace=null): Collection
    {
        if (! is_countable($tags))
            throw new Exception('Expected a iterable value.');
        if (count($tags) < 1)
            throw new Exception('Got empty tags.');

        // array to collection
        $original_tags = $tags;
        if (! $tags instanceof Collection)
            $tags = collect($tags);

        // get the tag models if string
        if (gettype($tags->first()) == 'string')
        {
            $tags = self::getTagClass()::findByName($original_tags, $namespace);
            if ($tags->count() < 1)
                throw new Exception('The provided tags do not exist.');
        }

        // check instance type
        if (! $tags->first() instanceof TagContract)
            throw new Exception('Tag provided must be string or Eloquent model');

        return $tags;
    }
}
