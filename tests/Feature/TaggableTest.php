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
        $words = $this->faker('en_US')->unique()->words($n);

        // faker default provider for  `words` is Lorem, which has only 182
        // unique words. Thus, faker->unique() may not return unique words
        // see https://github.com/fzaninotto/Faker/issues/1359
        foreach ($words as $i => $word)
            $words[$i] = $i . ':' . $word;

        return Tag::createMany($words);
    }

    public function test_add_tags()
    {
        $n = 5;
        $tags = $this->create_tags($n);
        $post = Post::factory()->createOne();
        $post->addTags($tags);
        $tag_ids = $tags->pluck('id');
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
            ])->pluck('tag_id');
        $candidate = Tag::whereIn('id', $ids)->get();

        // by asserting that there is no difference between the collections,
        // we can infer they are equal, thus getting the tags do work.
        $this->assertTrue($tags->diff($candidate)->isEmpty());
    }

    public function test_get_tags_matching()
    {
        $animal  = Tag::createOne('the first');
        $bird    = Tag::createOne('animals');
        $reptile = Tag::createOne('animated movies');
        $mammal  = Tag::createOne('anime');
        $eagle   = Tag::createOne('art');
        $duck    = Tag::createOne('martial arts');

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
        $animal = Tag::createOne('animal');

        // second level tags
        $bird    = Tag::createOne('bird');
        $reptile = Tag::createOne('reptile');
        $mammal  = Tag::createOne('mammal');
        $animals = collect([$bird, $reptile, $mammal]);
        $animals->each(add_tag($animal));

        // third level tags
        $eagle   = Tag::createOne('eagle');
        $duck    = Tag::createOne('duck');
        $pigeon  = Tag::createOne('pigeon');
        $whale   = Tag::createOne('whale');
        $sapiens = Tag::createOne('sapiens');
        $birds   = collect([$eagle, $duck, $pigeon]);
        $mammals = collect([$whale, $sapiens]);
        $birds->each(add_tag($bird));
        $mammals->each(add_tag($mammal));

        // fourth level tags
        $homo_sapiens  = Tag::createOne('homo sapiens');
        $mountain_duck = Tag::createOne('mountain duck');
        $silver_duck   = Tag::createOne('silver duck');
        $wild_duck     = Tag::createOne('wild duck');

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
        $tag1 = Tag::createOne('public', 'post');
        $tag2 = Tag::createOne('public', 'video');

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

    public function test_get_models_tagged_by()
    {
        // create tags
        $tags = $this->create_tags(50);
        $t1 = $tags->random(5);
        $t2 = $tags->random(5);
        $t3 = $tags->random(5);

        // create posts
        $posts = Post::factory(20)->create();
        $g3 = $posts->take(3); // group of the first 3 posts
        $g6 = $posts->take(6); // group of the first 6 posts

        Post::addTagsTo($tags, $g3);
        $posts[3]->addTags($t1);
        $posts[4]->addTags($t2);
        $posts[5]->addTags($t3);

        $tagged_posts = Post::taggedByAll($tags);
        $this->assertTrue($tagged_posts->diff($g3)->isEmpty());

        $tagged_posts = Post::taggedBy($tags);
        $this->assertTrue($tagged_posts->diff($g6)->isEmpty());

    }

    public function test_get_models_nested_tagged_by()
    {
        // now, try nested tags...
        $posts = Post::factory(20)->create();
        $tags = $this->create_tags(50);
        $t1 = $tags->slice(1, 5);
        $t2 = $tags->slice(6, 10);
        $t3 = $tags->slice(11, 15);
        $t4 = $tags->slice(16, 20);

        $t2->first()->addTags($t1->take(2));
        $t3->first()->addTags($t2);
        $t4->first()->addTags($t3);

        $posts[0]->addTags($t1);
        $posts[1]->addTags($t2);
        $posts[2]->addTags($t3);
        $posts[3]->addTags($t4);

        $g2 = $posts->take(2); // group of the first 2 posts
        $g3 = $posts->take(3); // group of the first 2 posts
        $g4 = $posts->take(4); // group of the first 4 posts

        // test tagged by some tags, but not all
        $tagged_posts = Post::taggedBy($t1, 2);
        $this->assertTrue($g2->diff($tagged_posts)->isEmpty());
        $tagged_posts = Post::taggedBy($t1, 3);
        $this->assertTrue($g3->diff($tagged_posts)->isEmpty());
        $tagged_posts = Post::taggedBy($t1, 4);
        $this->assertTrue($g4->diff($tagged_posts)->isEmpty());

        // test tagged by all
        $tagged_posts = Post::taggedByAll($t1, 2);
        $this->assertFalse($g2->diff($tagged_posts)->isEmpty());
        $tagged_posts = Post::taggedByAll($t1, 3);
        $this->assertFalse($g3->diff($tagged_posts)->isEmpty());
        $tagged_posts = Post::taggedByAll($t1, 4);
        $this->assertFalse($g4->diff($tagged_posts)->isEmpty());
    }

    public function test_get_models_with_tags()
    {
        // create tags
        $tags = $this->create_tags(70);
        $t1 = $tags->random(5);
        $t2 = $tags->random(15);
        $t3 = $tags->random(5);
        $t4 = $tags->random(10);

        // create posts
        $posts = Post::factory(6)->create();
        $posts[1]->addTags($t1);
        $posts[2]->addTags($t2);
        $posts[3]->addTags($t3);
        $posts[4]->addTags($t4);

        // test post with tags
        $posts = Post::withTags($posts);
        $this->assertTrue($posts[0]->tags->isEmpty());
        $this->assertTrue($posts[1]->tags->diff($t1)->isEmpty());
        $this->assertTrue($posts[2]->tags->diff($t2)->isEmpty());
        $this->assertTrue($posts[3]->tags->diff($t3)->isEmpty());
        $this->assertTrue($posts[4]->tags->diff($t4)->isEmpty());
        $this->assertTrue($posts[5]->tags->isEmpty());

        // test tag names
        $t1 = $t1->pluck('name');
        $t2 = $t2->pluck('name');
        $t3 = $t3->pluck('name');
        $t4 = $t4->pluck('name');
        $posts = Post::withTagNames($posts);
        $this->assertTrue($posts[0]->tags->isEmpty());
        $this->assertTrue($posts[1]->tags->diff($t1)->isEmpty());
        $this->assertTrue($posts[2]->tags->diff($t2)->isEmpty());
        $this->assertTrue($posts[3]->tags->diff($t3)->isEmpty());
        $this->assertTrue($posts[4]->tags->diff($t4)->isEmpty());
        $this->assertTrue($posts[5]->tags->isEmpty());
    }


    public function test_get_models_without_tags()
    {
        $tags = $this->create_tags(10);
        $posts1 = Post::factory(30)->create();
        $posts2 = Post::factory(30)->create();

        // add tags to the posts1
        Post::addTagsTo($tags, $posts1);

        // get posts not tagged by the tags
        $notTagged = Post::notTaggedBy($tags);

        // if the diff is empty, they are equal
        $this->assertTrue($posts2->diff($notTagged)->isEmpty());
    }

    public function test_models_mapped_by_tags()
    {
        $map = [];
        $tags = $this->create_tags(15);
        foreach ($tags as $tag)
        {
            $n = random_int(10, 30);
            $posts = Post::factory($n)->create();
            Post::addTagTo($tag, $posts);
            $map[$tag->name] = $posts;
        }
        $postsByTag = Post::byTagNames(Post::all());

        foreach ($postsByTag as $tag => $posts)
        {
            $ids1 = collect($posts)->pluck('id');
            $ids2 = $map[$tag]->pluck('id');
            $this->assertEmpty($ids1->diff($ids2));
        }
    }


    public function test_bulk_tag_operations()
    {
        $m = 10;
        $n = 10;
        $tags = $this->create_tags($n);
        $posts = Post::factory($m)->create();

        Post::addTagsTo($tags, $posts);

        // get the ids
        $tids = $tags->pluck('id');
        $pids = $posts->pluck('id');

        // there should be m*n rows in tagged table,
        // since each tag is attached to each model once
        $k = Taggable::query()
            ->whereIn('tag_id', $tids)
            ->whereIn('model_id', $pids)
            ->where('model', Post::class)
            ->count();
        $this->assertTrue($k == $m * $n);

        // delete the tags
        Post::delTagsFrom($tags, $posts);
        $this->assertTrue(Taggable::all()->count() == 0);

        // test deleting all tags
        Post::addTagsTo($tags, $posts);
        Post::delAllTagsFrom($posts);
        $this->assertTrue(Taggable::all()->count() == 0);
    }
}
