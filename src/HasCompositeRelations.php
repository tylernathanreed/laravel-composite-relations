<?php

namespace Reedware\LaravelCompositeRelations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasCompositeRelations
{
    /**
     * The primary keys for the model.
     *
     * @var array
     */
    protected $primaryKeys = ['id'];

    /**
     * Get the primary keys for the model.
     *
     * @return array
     */
    public function getKeyNames()
    {
        return $this->primaryKeys;
    }

    /**
     * Set the primary keys for the model.
     *
     * @param  array  $keys
     * @return $this
     */
    public function setKeyName($keys)
    {
        $this->primaryKeys = $keys;

        return $this;
    }

    /**
     * Get the table qualified key names.
     *
     * @return array
     */
    public function getQualifiedKeyNames()
    {
        return array_map(function ($keyName) {
            return $this->qualifyColumn($keyName);
        }, $this->getKeyNames());
    }

    /**
     * Get the values of the model's primary keys.
     *
     * @return array
     */
    public function getKeys()
    {
        return array_map(function ($keyName) {
            return $this->getAttribute($keyName);
        }, $this->getKeyNames());
    }

    /**
     * Get the default foreign key names for the model.
     *
     * @return array
     */
    public function getForeignKeys()
    {
        return array_map(function ($keyName) {
            return Str::snake(class_basename($this)).'_'.$keyName;
        }, $this->getKeyNames());
    }

    /**
     * Define a one-to-one relationship.
     *
     * @param  string  $related
     * @param  string|null  $glue
     * @return \Reedware\LaravelCompositeRelations\CompositeHasOne
     */
    public function compositeHasOne($related, ?array $foreignKeys = null, ?array $localKeys = null, $glue = 'or')
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKeys = $foreignKeys ?: $this->getForeignKeys();

        $localKeys = $localKeys ?: $this->getKeyNames();

        $foreignKeys = array_map(function ($foreignKey) use ($instance) {
            return $instance->getTable().'.'.$foreignKey;
        }, $foreignKeys);

        return $this->newCompositeHasOne($instance->newQuery(), $this, $foreignKeys, $localKeys, $glue);
    }

    /**
     * Instantiate a new HasOne relationship.
     *
     * @return \Reedware\LaravelCompositeRelations\CompositeHasOne
     */
    protected function newCompositeHasOne(Builder $query, Model $parent, array $foreignKeys, array $localKeys, string $glue)
    {
        return new CompositeHasOne($query, $parent, $foreignKeys, $localKeys, $glue);
    }

    /**
     * Define an inverse one-to-one or many composite relationship.
     *
     * @param  string  $related
     * @param  string  $relation
     * @param  string  $glue
     * @return \Reedware\LaravelCompositeRelations\CompositeBelongsTo
     */
    public function compositeBelongsTo($related, ?array $foreignKeys = null, ?array $ownerKeys = null, $relation = null, $glue = 'or')
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation)) {
            $relation = $this->guessCompositeBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if (empty($foreignKeys)) {
            $foreignKeys = array_map(function ($keyName) use ($relation) {
                return Str::snake($relation).'_'.$keyName;
            }, $instance->getKeyNames());
        }

        $ownerKeys = $ownerKeys ?: $instance->getKeyNames();

        return $this->newCompositeBelongsTo(
            $instance->newQuery(), $this, $foreignKeys, $ownerKeys, $relation, $glue
        );
    }

    /**
     * Instantiate a new BelongsTo relationship.
     *
     * @param  string  $relation
     * @param  string  $glue
     * @return \Reedware\LaravelCompositeRelations\CompositeBelongsTo
     */
    protected function newCompositeBelongsTo(Builder $query, Model $child, array $foreignKeys, array $ownerKeys, $relation, $glue)
    {
        return new CompositeBelongsTo($query, $child, $foreignKeys, $ownerKeys, $relation, $glue);
    }

    /**
     * Guess the "composite belongs to" relationship name.
     *
     * @return string
     */
    protected function guessCompositeBelongsToRelation()
    {
        [$one, $two, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller['function'];
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string  $related
     * @param  string|null  $glue
     * @return \Reedware\LaravelCompositeRelations\CompositeHasMany
     */
    public function compositeHasMany($related, ?array $foreignKeys = null, ?array $localKeys = null, $glue = 'or')
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKeys = $foreignKeys ?: $this->getForeignKeys();

        $localKeys = $localKeys ?: $this->getKeyNames();

        $foreignKeys = array_map(function ($foreignKey) use ($instance) {
            return $instance->getTable().'.'.$foreignKey;
        }, $foreignKeys);

        return $this->newCompositeHasMany(
            $instance->newQuery(), $this, $foreignKeys, $localKeys, $glue
        );
    }

    /**
     * Instantiate a new HasMany relationship.
     *
     * @return \Reedware\LaravelCompositeRelations\CompositeHasMany
     */
    protected function newCompositeHasMany(Builder $query, Model $parent, array $foreignKeys, array $localKeys, string $glue)
    {
        return new CompositeHasMany($query, $parent, $foreignKeys, $localKeys, $glue);
    }
}
