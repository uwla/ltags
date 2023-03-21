<?php

namespace Tests\Feature;

use Tests\Post;
use Tests\TestCase;
use Uwla\Ltags\Models\Tag;
use Uwla\Ltags\Models\Taggable;

class TaggableTest extends TestCase
{

    // I did not want to mess up with Uwla\Ltags\Models\Tag by adding a factory
    // into it just for the sake of testing. Instead, I thought it would be
    // better to use a helper.
    private function create_tags($n=1)
    {
        $words = $this->faker()->unique()->words($n);
        $attr = [];
        foreach ($words as $word)
            $attr[] = ['name' => $word ];
        Tag::insert($attr);
        $tags = Tag::whereIn('name', $words)->get();
        if ($n == 1)
            return $tags[0];
        return $tags;
    }

    public function test_add_tags()
    {
        $n = 5;
        $tags = $this->create_tags($n);
        $post = Post::factory()->createOne();
        $post->addTags($tags);
        $tag_ids = $tags->pluck('id')->toArray();
        $m = Taggable::query()
            ->whereIn('tag_id', $tag_ids)
            ->where([
                'model_id' => $post->id,
                'model' => $post::class,
            ])->count();
        $this->assertTrue($m == $n);
    }

    public function test_has_tags()
    {
        // create tags
        $n = 5;
        $m = 7;
        $tags = $this->create_tags($n);
        $other_tags = $this->create_tags($m);

        // create post
        $post = Post::factory()->createOne();
        $post->addTags($tags);

        $this->assertTrue($post->hasTags($tags));
        $this->assertFalse($post->hasAnyTags($other_tags));
        $post->addTag($other_tags[0]);
        $this->assertTrue($post->hasAnyTags($other_tags));
    }

    public function test_del_tags()
    {
        $n = 10;
        $m = 3;
        $tags = $this->create_tags($n);
        $toDel = $tags->take($m);
        $diff = $tags->diff($toDel);

        // create post
        $post = Post::factory()->createOne();
        $post->addTags($tags);
        $post->delTags($toDel);

        $this->assertFalse($post->hasTags($tags));
        $this->assertTrue($post->hasTags($diff));
    }

    public function test_get_tags()
    {
        $n = 10;
        $tags = $this->create_tags($n);
        $post = Post::factory()->createOne();
        $post->addTags($tags);
        $ids = Taggable::where([
                'model_id' => $post->id,
                'model' => $post::class
            ])->pluck('tag_id')->toArray();
        $candidate = Tag::whereIn('id', $ids)->get();

        // by asserting that there is no difference between the collections,
        // we can infer they are equal, thus getting the tags do work.
        $this->assertTrue($tags->diff($candidate)->isEmpty());
    }

    // TO DO
    // public function test_get_tags_matching()
    // {
    //     //
    // }
    //
    // public function test_get_nested_tags()
    // {
    //     //
    // }
    //
    // public function test_tag_namespace()
    // {
    //     //
    // }
}
