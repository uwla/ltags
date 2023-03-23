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
        $all_tags = $this->create_tags($n+$m);
        $tags = $all_tags->take($n);
        $other_tags = $all_tags->diff($tags);

        // create post
        $post = Post::factory()->createOne();
        $this->assertFalse($post->hasAnyTags($tags));
        $post->addTags($tags);

        // test has tags via model collection
        $this->assertTrue($post->hasTag($tags[0]));
        $this->assertTrue($post->hasTags($tags));

        // test has tags via string names
        $names = $tags->pluck('name')->toArray();
        $this->assertTrue($post->hasTag($names[0]));
        $this->assertTrue($post->hasTags($names));

        // the next test is a risk test...
        // something is wrong and needs to be fixed
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

    public function test_get_tags_matching()
    {
        // make sure it starts with no tags
        Tag::query()->delete();
        $animal = Tag::create(['name' => 'the first']);
        $bird = Tag::create(['name' => 'animals']);
        $reptile = Tag::create(['name' => 'animated movies']);
        $mammal = Tag::create(['name' => 'anime']);
        $eagle = Tag::create(['name' => 'art']);
        $duck = Tag::create(['name' => 'martial arts']);

        $post = Post::factory()->createOne();
        $post->addTags(Tag::all());

        // first match
        $test_cases = [
            ['/.*art/', collect([$eagle, $duck])],
            ['/anim.*/', collect([$bird, $reptile, $mammal])],
            ['/ani\[a-z\]+/', collect([$bird, $mammal])],
            ['/\W \W/', collect([$animal, $reptile, $duck])],
        ];

        foreach ($test_cases as $test)
        {
            $pattern = $test[0];
            $expected = $test[1];
            $match = $post->getTagsMatching($pattern);
            $diff = $match->diff($expected);
            $this->assertTrue($diff->isEmpty());
        }
    }

    public function test_get_nested_tags()
    {
        // this test will be a little nesty (nasty + nested)
        // that was a silly pun anyway...

        // little helper for adding tags
        function add_tag($tag) {
            return fn($t) => $t->addTag($tag);
        }

        // first level tags
        $animal = Tag::create(['name' => 'animal']);

        // second level tags
        $bird    = Tag::create(['name' => 'bird']);
        $reptile = Tag::create(['name' => 'reptile']);
        $mammal  = Tag::create(['name' => 'mammal']);
        $animals = collect([$bird, $reptile, $mammal]);
        $animals->each(add_tag($animal));

        // third level tags
        $eagle   = Tag::create(['name' => 'eagle']);
        $duck    = Tag::create(['name' => 'duck']);
        $pigeon  = Tag::create(['name' => 'pigeon']);
        $whale   = Tag::create(['name' => 'whale']);
        $sapiens = Tag::create(['name' => 'sapiens']);
        $birds   = collect([$eagle, $duck, $pigeon]);
        $mammals = collect([$whale, $sapiens]);
        $birds->each(add_tag($bird));
        $mammals->each(add_tag($mammal));

        // fourth level tags
        $homo_sapiens  = Tag::create(['name' => 'homo sapiens']);
        $mountain_duck = Tag::create(['name' => 'mountain duck']);
        $silver_duck   = Tag::create(['name' => 'silver duck']);
        $wild_duck     = Tag::create(['name' => 'wild duck']);

        $ducks = collect([$mountain_duck, $silver_duck, $wild_duck]);
        $Sapiens = collect([$homo_sapiens]);
        $ducks->each(add_tag($duck));
        $Sapiens->each(add_tag($sapiens));

        // perform the tests now
        $this->assertFalse($pigeon->hasTag($animal));
        $this->assertFalse($eagle->hasTag($animal));
        $this->assertFalse($duck->hasTag($animal));
        $this->assertTrue($pigeon->hasTag($animal, 2));
        $this->assertTrue($eagle->hasTag($animal, 2));
        $this->assertTrue($duck->hasTag($animal, 2));

        $this->assertFalse($silver_duck->hasTag($animal, 2));
        $this->assertTrue($silver_duck->hasTag($animal, 3));
        $this->assertTrue($silver_duck->hasTag($bird, 2));
        $this->assertTrue($silver_duck->hasTag($duck, 1));

        $this->assertFalse($homo_sapiens->hasTag($animal, 2));
        $this->assertTrue($homo_sapiens->hasTag($animal, 3));
        $this->assertTrue($homo_sapiens->hasTag($animal, 5));
        $this->assertTrue($homo_sapiens->hasTag($mammal, 2));
        $this->assertTrue($homo_sapiens->hasTag($sapiens, 1));
    }

    public function test_tag_namespace()
    {
        $tag1 = Tag::create(['name' => 'public', 'namespace' => 'post']);
        $tag2 = Tag::create(['name' => 'public', 'namespace' => 'video']);

        $post = Post::factory()->createOne();
        $post->addTag($tag1);
        $post->tagNamespace = 'post';
        $this->assertTrue($post->hasTag($tag1));
        $this->assertTrue($post->hasTag('public'));
        $this->assertFalse($post->hasTag($tag2));
        $post->tagNamespace = 'video';
        $this->assertTrue($post->hasTag($tag1));
        $this->assertFalse($post->hasTag($tag2));
        $this->assertFalse($post->hasTag('public'));
    }
}
