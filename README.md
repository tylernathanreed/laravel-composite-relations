# Laravel Composite Relations

[![Latest Stable Version](https://poser.pugx.org/reedware/laravel-composite-relations/v)](//packagist.org/packages/reedware/laravel-composite-relations)
[![Total Downloads](https://poser.pugx.org/reedware/laravel-composite-relations/downloads)](//packagist.org/packages/reedware/laravel-composite-relations)
[![Laravel Version](https://img.shields.io/badge/Laravel-8.x--10.x%2B-blue)](https://laravel.com/)
[![Build Status](https://github.com/tylernathanreed/laravel-composite-relations/workflows/tests/badge.svg)](https://github.com/tylernathanreed/laravel-composite-relations/actions)

This package adds the ability to have multiple foreign keys in a relation.

## Introduction

Eloquent does not natively support using composite keys in relationships. While single key relationships are typically preferred, there are times where they are unfortunately needed, and there's good way around it. This package offers a solution where you can use composite keys, and everything still feels like it's Eloquent.

This package will allow you to define the following composite relations:
* Belongs To
* Has One
* Has Many

All composite relations support eager loading and existence queries (e.g. "where has").

There currently is no intention to add support for additional relations, as these should be enough for the vast majority of use cases.

## Installation

#### Using Composer

```
composer require reedware/laravel-composite-relations
```

#### Versioning

This package currently only supports Laravel 5.5, but future support will eventually be added.

#### Code Changes

This package does not use a service provider or facade, but rather a trait. On your base model instance, you'll want to include the following:

```php
use Illuminate\Database\Eloquent\Model as Eloquent;
use Reedware\LaravelCompositeRelations\HasCompositeRelations;

abstract class Model extends Eloquent
{
    use HasCompositeRelations;
}
```

## Usage

### 1. Defining Relations

#### Composite Belongs To

Imagine you were defining a *non-composite* belongs to relation:

```php
public function myRelation()
{
    return $this->belongsTo(MyRelated::class, 'my_column_on_this_table', 'my_column_on_the_other_table');
}
```

Since composite relations use multiple keys, you'll simply define the keys as an array:

```php
public function myCompositeRelation()
{
    return $this->compositeBelongsTo(MyRelated::class, ['my_first_key', 'my_second_key'], ['their_first_key', 'their_second_key']);
}
```

#### Composite Has One

This follows the same structure as the composite belongs to relation:

```php
public function myCompositeRelation()
{
    return $this->compositeHasOne(MyRelated::class, ['their_first_key', 'their_second_key'], ['my_first_key', 'my_second_key']);
}
```

#### Composite Has Many

The pattern continues:

```php
public function myCompositeRelation()
{
    return $this->compositeHasMany(MyRelated::class, ['foreign_1', 'foreign_2'], ['local_1', 'local_2']);
}
```

### 2. Omitting Foreign and Local Keys

With non-composite relationships, you aren't actually required to provide the foreign and local key, assuming you follow a certain convention. This functionality is also available for composite relations, but must be defined differently. Here's how:

```php
class Task extends Models
{
    /**
     * The primary keys for the model.
     *
     * @var array
     */
    protected $primaryKeys = ['vendor_id', 'vendor_name'];

    public function importSummary()
    {
        return $this->compositeHasOne(TaskImportSummary::class);
    }
}

class TaskImportSummary extends Models
{
    public function task()
    {
        return $this->compositeBelongsTo(TaskImportSummary::class);
    }
}
```

### 3. Joining through Composite Relations

This package is compatible with [reedware/laravel-relation-joins](https://github.com/tylernathanreed/laravel-relation-joins), meaning you can join through composite relations just like anything else:

```php
$task->joinRelation('importSummary', function($join) {
    $join->where('task_import_summaries.name', 'like', '%Relation joins are cool!%');
});
```

You must separately include [reedware/laravel-relation-joins](https://github.com/tylernathanreed/laravel-relation-joins) for this to work.
