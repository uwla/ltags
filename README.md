# LTAGS

Tagging system for Laravel.

## FEATURES

- **arbitrary models**: tags can be attached to any Eloquent Model.
- **nested tags**: a tag can also be tagged, allowing hierarchical structures.
- **context aware**: multiple tags with the same label can be created for different contexts.
- **non intrusive**: a resource can be tagged without any modification to its classes or DB tables.
- **handy API**: handy API to add/set/get/delete tags to a model, and to fetch models by their tags.

## INSTALL

Install using composer:

```shell
composer require uwla/ltags
```

Publish the ACL table migrations:

```shell
php artisan vendor:publish --provider="Uwla\Ltags\TagServiceProvider"
```

Run the migrations:

```shell
php artisan migrate
```

## USAGE

This sections explains how to use the package. A demo app is also  available  on
[uwla/ltags-demo](https://github.com/uwla/ltags-demo) to illustrate use case.

### Tags

Create tag:

```php
<?php
use Uwla\Ltags\Models\Tag;

// create single tag
$tag = Tag::createOne('foo');           // shorter way
$tag = Tag::create(['name' => 'foo']);  // default way to create Eloquent models

// create multiple tags tag
$tags = Tag::createMany(['foo', 'bar', 'zoo']); // Eloquent is way more verbose
```

Get tag:

```php
<?php
$tag = Tag::findByName('foo');  // get single tag
$tags = Tag::findByName(['foo', 'bar', 'zoo']); // get many tags
```

Delete tag or tags by name:

```php
<?php
// delete a tag by name
Tag::delByName('foo');                  // delete single tag
Tag::delByName(['foo', 'bar', 'zoo']);  // delete multiple tags

// The method above only works for string and string arrays.
// If you have a Eloquent model or collection and want to delete it or them,
// do the following:
$model->delete();
$models->delete();
```

To update a tag  or  tags,  use  Eloquent's  `update`  method,  as  provided  by
Laravel's documentation. This  package  provides  alternative  ways  to  create,
fetch and delete tags via their names just to make it more convenient  and  less
verbose,  but  when  it  comes  to  updating   tags   Laravel's   interface   is
straightforward and we cannot make it less verbose.

### Tagged Models

Any model can be tagged. The only thing needed is to add the `Taggable` trait:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Uwla\Ltags\Trait\Taggable

class Post extends Model
{
    use Taggable;   // just add this

    // more code...
}
```

We are going to use a Post model as an example  of  an  application  for  user's
posts, but it could be any model to be tagged including the user itself!

To add a tag or tags to a model:

```php
<?php
// add single tag, which is Eloquent
$tag = Tag::createOne('public');
$post->addTag($tag);

// add single tag by its name, no need to for you to fetch it first
$post->addTag('public');

// add tags
$tags = Tag::all();
$post->addTag($tags);

// add tags by name
$post->addTag(['php', 'laravel', 'composer']);

// add a tag or tags to multiple models at once
// (the second argument must be an Eloquent Collection of the model)
Post::addTagsTo($tags, $posts);           // tags can be am Eloquent Collection
Post::addTagsTo(['html', 'css'], $posts); // tags can be an array of strings too
Post::addTagTo('php', $posts);            // to pass a single tag, call addTagTo
                                          // instead of addTagsTo (shorter syntax)
```

To get a model's tags:

```php
<?php
// get all tags
$tags = $post->getTags();

// you specify the depth of the search to get nested tags
// that is, if a post has a tag and that tag has another tag,
// you will get the parent tag as well.
$depth = 4;
$tags = $post->getTags($depth);

// get tags matching a regex
// (in this case, any tag whose name is made up of two words)
$tags = $post->getTagsMatching('/\W \W/');

// again, the depth of the search can be specified
$tags = $post->getTagsMatching('/\W \W/', $depth);
```

To check if a model has a tag or tags:

```php
<?php
// check if has a single tag
$post->hasTag($tag);        // Eloquent model
$post->hasTag('public');    // name string

// check if has all provided tags
$post->hasTags($tags);              // Eloquent collection
$post->hasTags(['foo', 'bar']);     // name string

// check if it has any of the provided tags
$post->hasAnyTags($tags);              // Eloquent collection
$post->hasAnyTags(['foo', 'bar']);     // name string

// the depth of the search can also be provided
$depth = 3;
$post->hasTag($tag, $depth);
$post->hasTags($tags, $depth);
$post->hasAnyTags($tags, $depth);
```

To remove a tag or tags from a model:

```php
<?php
// remove single tag, which is Eloquent
$tag = Tag::createByName('public');
$post->delTag($tag);

// remove via tag name
$post->delTag('public');

// remove tags from Eloquent collection
$tags = $post->getTagsMatching('*www*');
$post->delTags($tags);

// remove tags by name
$post->delTags(['php', 'laravel', 'composer']);

// remove all tags
$post->delAllTags();

// remove a tag or tags from multiple models at once
// (the second argument must be an Eloquent Collection of the model)
Post::delTagsFrom($tags, $posts);           // tags can be an Eloquent Collection
Post::delTagsFrom(['html', 'css'], $posts); // tags can be an array of strings
Post::delTagFrom('php', $posts);            // to pass a single tag you can call
                                            // delTagFrom instead of delTagsFrom

// remove all tags from the given models
Post::delAllTagFrom($posts);
```

To set the tags of a model:

```php
<?php
// the set method basically removes all tags of the model
// and add the new ones, thus 'setting' its tags.
// It is syntax sugar.
$post->setTags($tags);
```

To get the models along with their tags or with the name (only) of the tags:

```php
<?php
// attach the tags to the given posts
$posts = Post::withTags($posts);
$posts = Post::withTagNames($posts);

// all posts
$posts = Post::withTags(Post::all());
$posts = Post::withTagNames(Post::all());

// posts that match a condition
$posts = Post::withTags(Post::where($condition)->get());
$posts = Post::withTagNames(Post::where($condition)->get());
```

In the second line of each example, only the name of the tag is attached to the
model, not the tag itself (which is an instance of `Tag`).

In the example above, each model will have a new attribute called `tags`,  which
is a `Collection` of the model's directed tags (that is,  nested  tags  are  not
included). You can then apply other operations on  top  of  the  tags  by  using
Laravel's Collection handy methods, such as `map`, `pluck`, `filter`, etc.

To get the models tagged by a tag, or tags:

```php
<?php
// by a single tag
$posts = Post::taggedBy($tag);      // Eloquent model
$posts = Post::taggedBy('public');  // name string

// posts tagged by at least one of the given tags
$posts = Post::taggedBy($tags);               // Eloquent collection
$posts = Post::taggedBy(['php', 'laravel']);  // array of strings

// you can provide the depth of the search in the second argument
// default depth is 1
$posts = Post::taggedBy($tags, 3);

// it is possible to specify the namespace (explained in the next section)
$posts = Post::taggedBy($tags, 1, 'posts');
$posts = Post::taggedBy($tags, namespace: 'posts'); // named arguments syntax
```

In the example above, `taggedBy` will give you all models that are tagged by  at
least one of the provided tags. If you want the models to be tagged by  all  the
tags, then use `taggedByAll` static method, which has the exact same  syntax  as
`taggedBy` (in other words, the expected parameters are the same).

**Notice**:  The  `taggedByAll`  needs  to  check  if  all  are  matched,  while
`taggedBy` only needs to check one; thus, the `taggedByAll` may be come slow  if
you are using a relational database and are trying to  fetch  models  tagged  by
many tags with a high depth value. A graph database is  more  suitable  in  this
case. But for most applications this won't be a problem even if the `depth`  is,
let's say, equal to five or six.

### Namespaces

There can be multiple tags with different namespaces (aka, contexts).

For example, the developer may want a "top" tag for posts and a  "top"  tag  for
videos (although I personally think it is good enough to have just one "top" tag
for both posts and video as long as the developers take proper care).

Another example, there could be a tag "free" for the context of payments  and  a
tag "free" in the context of Free Software, in which it means "freedom".

Ultimately, it is up to the developers to decide  if  `namespaces`  are  needed,
since they are the ones who know the application requirements.

Let's see how to use them. To create, find or delete  a  tag  for  a  particular
namespace:

```php
<?php
// just add the namespace as the second parameter
$namespace = 'posts';

// create the tags
$tag = Tag::createOne($name, $namespace);    // one tag
$tags = Tag::createMany($names, $namespace); // multiple tags

// find the tags
$tag = Tag::findByName($name, $namespace); // one tag
$tags = Tag::findByName($names, $namespace); // multiple tags

// delete the tags
Tag::delByName($name, $namespace);  // one tag
Tag::delByName($names, $namespace); // multiple tags
```

When the  `getTags`  and  `hasTags`  methods  are  called,  they  will  use  the
namespace obtained by calling `getTagNamespace`, which  by  default  is  `null`.
You could use a `static` namespace for a given model or a dynamic one.

Here is an example of a static (aka, does not change) tag namespace:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Uwla\Ltags\Trait\Taggable

class Post extends Model
{
    use Taggable;   // just add this

    // override method
    public function getTagNamespace()
    {
        return 'posts';
    }
}
```

Now, an example of a dynamic one:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Uwla\Ltags\Trait\Taggable

class Post extends Model
{
    use Taggable;   // just add this

    public $tagNamespace = 'posts';

    // override method
    public function getTagNamespace()
    {
        return $this->tagNamespace;
    }
}
```

Which you can then change in real time:

```php
<?php
$tag1 = Tag::createOne('public', 'bar'); // bar namespace
$tag2 = Tag::createOne('public', 'foo'); // foo namespace

$post->tagNamespace = 'bar';    // set namespace to 'bar'
$post->addTag('public');        // adds $tag1
$post->tagNamespace = 'foo';    // set namespace to 'foo'
$post->hasTag('public');        // returns false, since the namespace is 'foo'
$post->hasTag($tag);            // returns true, since the model was provided
$post->addTag('public');        // adds $tag2
$post->tagNamespace = 'bar';    // set namespace to 'bar'
$post->delTag('public');        // deletes $tag1
$post->tagNamespace = 'foo';    // set namespace to 'foo'
$post->hasTag('public');        // returns true, since the namespace is 'foo'
```

The namespace will only affect the methods to which were passed string names  as
arguments because the tags associated with those  names  needed  to  be  fetched
behind the scenes. If a Eloquent model or a Eloquent collection is passed  as  a
argument, the namespace will have no effect because the Eloquent models  already
have the tag ids which uniquely identify the tags.

### Custom Tag Model

You can use your custom variant for the `Tag` model instead of the default  one,
which is `Uwla\Ltags\Models\Tag`.

```php
<?php

namespace App\Models;

use Uwla\Ltags\Models\Tag as BaseTag;

class Tag extends BaseTag
{
    // disable timestamps, if you want
    $timestamps = false;

    // maybe you changed the table name..
    $table = 'tag';
}
```

In order for the `Taggable` trait to use your tag instead of  the  default  one,
you can override its method `getTagClass` as follow:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Uwla\Ltags\Trait\Taggable;
use App\Models\Tag;

class Post extends Model
{
    use Taggable;

    protected static function getTagClass()
    {
        return Tag::class;
    }
}
```

Instead of doing that for every model, you could  have  your  custom  `Taggable`
trait as well:

```php
<?php

namespace App\Traits;

use Uwla\Ltags\Trait\Taggable as BaseTaggable
use App\Models\Tag;

class Taggable extends BaseTaggable
{
    protected static function getTagClass()
    {
        return Tag::class;
    }
}
```

Then, use `App\Traits\Taggable` instead of `Uwla\Ltags\Traits\Taggable`.

## EXAMPLES

Besides using tags to organize and search content such as  videos  or  articles,
there are several ways in which tags can be quite handy. Let's explore them.

Suppose you have an applications with  posts,  some  of  them  public.  In  your
Laravel `PostPolicy`, you could do the following to check if a user  is  allowed
to view a particular post:

```php
<?php

/**
 * Determine whether the user can view the post
 *
 * @param  App\User  $user
 * @param  App\Post  $post
 * @return \Illuminate\Auth\Access\Response|bool
 */
public function view(User $user, Post $post)
{
    // any user can view a public post
    if ($post->hasTag('public'))
        return true;

    // user can view a private post if he is the post owner
    // the "traditional" way is to put a `user_id` column in the posts table
    // but of course there are better ways to do this.
    return $post->user_id == $user->id;
}
```

In the example above, we avoid having to add a `is_public` column to  the  posts
table.

Another example  is  using  tags  to  tag  users  based  on  their  roles.  Some
applications just add a `role` column to the users table, then they would  check
`user->role`. But this creates  an  additional  column.  If  you  don't  need  a
sophisticated  Access Control System, but  need only a simple  way to categorize
users based on roles, you could simply add tags to the user.

```php
<?php
// instead of
if ($user->role == 'admin')
{
    // do stuff
}
// or maybe
if ($user->role == 'vip')
{
    // allow vip content
}

// you could do the following
if ($user->hasTag('admin'))
{
    // do stuff
}
// or maybe
if ($user->hasTag('vip'))
{
    // allow vip content
}
```

Other example is an application that promotes programming  contests,  which  may
have their visibility as public or private, their status as running or  expired,
and so on. Instead  of  adding  several  columns  in  the  contests  table,  the
developers may choose to use tags as an additional source of  information  about
the contests. Of course, whether it is a good decision  or  not  is  up  to  the
developers to figure out for their particular case.

These are just three simple examples. The possibilities are limited only by  the
developer's imagination. Tags can be adapted to any context which has  resources
that can be grouped by some criteria, any context which deals with clusters.

## CONTRIBUTIONS

Contributions are welcome. Fork the repository and make a PR.

## FIND HELP

Open an issue on this package repository on GitHub. I will be glad to help.
