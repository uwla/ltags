# LTAGS

Simple Tagging system for Laravel.

## FEATURES

- **arbitrary models**: tags can be attached to any Eloquent Model.
- **nested tags**: a tag can also be tagged, allowing hierarchical structures.
- **context aware**: multiple tags with the same label can be created for different contexts.
- **non intrusive**: a resource can be tagged without any modification to its classes or DB tables.

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

### Tags

Create tag:

```php
<?php
use Uwla\Ltags\Models\Tag;

// create a single tag
$tag = Tag::createOne('foo');           // shorter way
$tag = Tag::create(['name' => 'foo']);  // default way to create Eloqquent models

// create a multiple tags tag
$tags = Tag::createMany(['foo', 'bar', 'zoo']); // Eloquent is way more verbose
```

Get tag:

```php
<?php
$tag = Tag::findByName('foo');  // get single tag
$tags = Tag::findManyByName(['foo', 'bar', 'zoo']); // get many tags
```

Delete tag or tags by name:

```php
<?php
// delete a tag by name
Tag::del('foo');  // delete single tag
Tag::del(['foo', 'bar', 'zoo']); // delete multiple tags

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

Any model can be tagged. The only thing needed is to add  the  `Taggable`  trait
and make sure the model implements the `TaggableContract` (which is  implemented
by the trait, so you don't have to worry about that. For example:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Uwla\Ltags\Contracts\Taggable as TaggableContract;
use Uwla\Ltags\Trait\Taggable

class Post extends Model implements TaggableContract
{
    use Taggable;   // just add this

    // more code...
}
```

In other words, just add `use Taggable` and you are good to go. It  is  strongly
recommended to add `implements TaggableContract` to the model's class  in  order
to ensure proper type hint.

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
$tag = Tag::createOne('public');
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
```

To set the tags of a model:

```php
<?php
// the set method basically removes all tags of the model
// and add the new ones, thus 'setting' its tags.
// It is syntax sugar.
$post->setTags($tags);
```

### Namespaces

There can be multiple tags with the same name (aka, label) but  associated  with
different namespaces (aka, contexts).

For example, the developer may want a "top" tag for posts and a  "top"  tag  for
videos (although I personally think it is good enough to have just one "top"  tag
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
$tag = Tag::createOne($name, $namespace); // one tag
$tags = Tag::createMany($names, $namespace); // multiple tags

// find the tags
$tag = Tag::findByName($name, $namespace); // one tag
$tags = Tag::findManyByName($names, $namespace); // multiple tags

// delete the tags
Tag::del($name, $namespace);  // one tag
Tag::del($names, $namespace); // multiple tags
```

When the  `getTags`  and  `hasTags`  methods  are  called,  they  will  use  the
namespace obtained by calling `getTagNamespace`, which  by  default  is  `null`.
You could use a `static` namespace for a given model or a dynamic one.

Here is an example of a static (aka, does not change) tag namespace:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Uwla\Ltags\Contracts\Taggable as TaggableContract;
use Uwla\Ltags\Trait\Taggable

class Post extends Model implements TaggableContract
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
use Uwla\Ltags\Contracts\Taggable as TaggableContract;
use Uwla\Ltags\Trait\Taggable

class Post extends Model implements TaggableContract
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

In the example above, we avoid having to add a `is_public` column to the
posts table.

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

## ROADMAP

Here is a roadmap of the project:

- fix a risky test
- maybe add demo apps

## CONTRIBUTIONS

Contributions are welcome. Fork the repository and make a PR.

## FIND HELP

Open an issue on this package repository on Github. I will be glad to help.
