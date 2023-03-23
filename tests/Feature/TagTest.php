<?php

namespace Tests\Feature;

use Tests\TestCase;
use Uwla\Ltags\Models\Tag;

class TagTest extends TestCase
{
    public function test_get_tags_by_name()
    {
        // tag names for movies
        $names = [ 'war', 'scifi', 'drama', 'comedy', 'action', 'romance'];

        // create the tags
        $attr = [];
        foreach ($names as $name)
            $attr[] = ['name' => $name];
        Tag::insert($attr);

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
}
