<?php

namespace Tests\Feature;

use Tests\TestCase;
use Uwla\Ltags\Models\Tag;

class TagTest extends TestCase
{
    public $names = [
        'war', 'scifi', 'drama', 'comedy', 'action', 'romance'
    ];

    public function test_create_tags_by_name()
    {
        $names = $this->names;

        $n = $names[0];
        Tag::createOne($n);
        $this->assertTrue(Tag::where('name', $n)->exists());

        unset($names[0]);
        Tag::createMany($names);
        $this->assertTrue(count($names) == Tag::whereIn('name', $names)->count());
    }

    public function test_get_tags_by_name()
    {
        $names = $this->names;

        // create the tags
        Tag::createMany($names);

        // test find single tag
        $tag = Tag::findByName($names[0]);
        $this->assertTrue($tag->name === $names[0]);

        // test find many tag
        $tags = Tag::findManyByName($names);
        $tagNames = $tags->pluck('name')->toArray();

        // arrays needed to be sorted for the comparison
        sort($names); sort($tagNames);
        $this->assertEquals($names, $tagNames);
    }

    public function test_del_tags_by_name()
    {
        $names = $this->names;

        // create the tags
        Tag::createMany($names);

        // delete the tags
        Tag::del($names);
        Tag::createOne($names[0]);
        $this->assertTrue(Tag::all()->count() == 1);
    }
}
