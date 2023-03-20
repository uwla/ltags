<?php

namespace Uwla\Ltags\Traits;

use Exception;
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
     * Get the tag namspace for this model. Default is null.
     *
     * @return string
     */
    public function getTagNamespace()
    {
        return null;
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
            $otherIds = $nested_tags->pluck('tag_id')->toArray();
            $ids = array_merge($ids, $otherIds);
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
        $tags = $this->validateTags($tags);

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
        return $n > 0;
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
        $tags = $this->validateTags($tags);
        $ids = [];
        foreach ($tags as $tag)
            $ids[] = $tag->id;

        // delete the tags
        TaggableModel::whereIn('tag_id', $ids)->where([
            'model_id' => $this->id,
            'model' => $this::class
        ])->delete();
    }

    // -------------------------------------------------------------------------
    // HELPERS

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

    // helper to get how many tags of the given tags this model has
    private function hasHowManyTags($tags, $depth)
    {
        $tags = $this->validateTags($tags);
        $this_tags = $this->getTags($depth);

        // we will get the ids of the given tags
        // and also the ids of the this model's tags
        $a = $tags->pluck('tag_id')->toArray();
        $b = $this_tags->pluck('tag_id')->toArray();

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
            if ($a[$i] == $b[$j])
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
    private function validateTags($tags)
    {
        if (is_iterable($tags))
            throw new Exception("Expected a iterable value.");
        if (count($tags) < 1)
            throw new Exception("Got empty tags.");

        // the namespace for the tags
        $namespace = $this->getTagNamespace();

        // get the tag models
        if (gettype($tags[0]) == 'string')
            $tags = Tag::findManyByName($tags, $namespace);
        if (! $tags[0] instanceof TagContract)
            throw new Exception("Tag provided must be string or Eloquent model");
        if (count($tags) < 1)
            throw new Exception("The provided tags do not exist.");
        return $tags;
    }
}
