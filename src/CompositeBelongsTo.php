<?php

namespace Reedware\LaravelCompositeRelations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;

class CompositeBelongsTo extends Relation
{
    use SupportsDefaultModels;

    /**
     * The child model instance of the relation.
     */
    protected $child;

    /**
     * The foreign keys of the parent model.
     *
     * @var array
     */
    protected $foreignKeys;

    /**
     * The associated keys on the parent model.
     *
     * @var array
     */
    protected $ownerKeys;

    /**
     * The name of the relationship.
     *
     * @var string
     */
    protected $relationName;

    /**
     * The count of self joins.
     *
     * @var int
     */
    protected static $selfJoinCount = 0;

    /**
     * Create a new belongs to relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $child
     * @param  array  $foreignKeys
     * @param  array  $ownerKeys
     * @param  string  $relationName
     *
     * @return void
     */
    public function __construct(Builder $query, Model $child, array $foreignKeys, array $ownerKeys, $relationName)
    {
        $this->ownerKeys = $ownerKeys;
        $this->relationName = $relationName;
        $this->foreignKeys = $foreignKeys;

        // In the underlying base relationship class, this variable is referred to as
        // the "parent" since most relationships are not inversed. But, since this
        // one is we will create a "child" variable for much better readability.
        $this->child = $child;

        parent::__construct($query, $child);
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        foreach($this->foreignKeys as $foreignKey) {
            if (is_null($this->child->{$foreignKey})) {
                return $this->getDefaultFor($this->parent);
            }
        }

        return $this->query->first() ?: $this->getDefaultFor($this->parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (!static::$constraints) {
            return;
        }

        // For belongs to relationships, which are essentially the inverse of has one
        // or has many relationships, we need to actually query on the primary key
        // of the related models matching on the foreign key that's on a parent.
        $table = $this->related->getTable();

        $this->query->where(function($query) use ($table) {
            foreach($this->foreignKeys as $index => $foreignKey) {
                $this->query->where($table.'.'.$this->ownerKeys[$index], '=', $this->child->{$foreignKey});
            }
        });
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        // Wrap everything in a "where" clause
        $this->query->where(function($query) use ($models) {

            // Determine the table name
            $table = $this->related->getTable();

            // We can't use a "where in" clause, as there are multiple keys. We instead
            // need to use a nested "or where" clause for each individual model. It's
            // not very speed efficient, but that is the cost of using composites.

            // Initialize a hash map
            $mapped = [];

            // Iterate through each model
            foreach($models as $model) {

                // We'll grab the primary key names of the related models since it could be set to
                // a non-standard name and not "id". We will then construct the constraint for
                // our eagerly loading query so it returns the proper models from execution.

                // Determine the pair mapping
                $mapping = array_combine(array_map(function($ownerKey) use ($table) {
                    return $table.'.'.$ownerKey;
                }, $this->ownerKeys), array_map(function($foreignKey) use ($model) {
                    return $model->{$foreignKey};
                }, $this->foreignKeys));

                // If the pairing has already been mapped, skip it
                if(isset($mapped[$mappedKey = json_encode($mapping)])) {
                    continue;
                }

                // Add an "or where" clause for each key pairing
                $query->orWhere($mapping);

                // To prevent the same entry from appearing multiple times within the sql, we are
                // going to keep track of the combinations that we've already added, and ensure
                // that we only include them once. We have to get cute for the multiple keys.

                // Mark the pairing as mapped
                $mapped[$mappedKey] = true;

            }

        });

    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        // First we will get to build a dictionary of the child models by their primary
        // key of the relationship, then we can easily match the children back onto
        // the parents using that dictionary and the primary key of the children.
        $dictionary = [];

        foreach ($results as $result) {

            $dictionaryKey = json_encode(array_map(function($ownerKey) use ($result) {
                return (string) $result->getAttribute($ownerKey);
            }, $this->ownerKeys));

            $dictionary[$dictionaryKey] = $result;
        }

        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {

            $dictionaryKey = json_encode(array_map(function($foreignKey) use ($model) {
                return (string) $model->getAttribute($foreignKey);
            }, $this->foreignKeys));

            if (isset($dictionary[$dictionaryKey])) {
                $model->setRelation($relation, $dictionary[$dictionaryKey]);
            }
        }

        return $models;
    }

    /**
     * Update the parent model on the relationship.
     *
     * @param  array  $attributes
     * @return mixed
     */
    public function update(array $attributes)
    {
        return $this->getResults()->fill($attributes)->save();
    }

    /**
     * Associate the model instance to the given parent.
     *
     * @param  \Illuminate\Database\Eloquent\Model|array  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate($model)
    {
        $attributes = $model instanceof Model ? array_map(function($ownerKey) use ($model) {
            return $model->getAttribute($ownerKey);
        }, $this->ownerKeys) : array_values($model);

        foreach($this->foreignKeys as $index => $foreignKey) {
            $this->child->setAttribute($foreignKey, $attributes[$index]);
        }

        if ($model instanceof Model) {
            $this->child->setRelation($this->relationName, $model);
        }

        return $this->child;
    }

    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function dissociate()
    {
        foreach($this->foreignKeys as $foreignKey) {
            $this->child->setAttribute($foreignKey, null);
        }

        return $this->child->setRelation($this->relationName, null);
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }

        return $query->select($columns)->whereColumn(array_combine($this->getQualifiedForeignKeyNames(), array_map(function($ownerKey) use ($query) {
            return $query->qualifyColumn($ownerKey);
        }, $this->ownerKeys)));
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQueryForSelfRelation(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query->select($columns)->from(
            $query->getModel()->getTable().' as '.$hash = $this->getRelationCountHash()
        );

        $query->getModel()->setTable($hash);

        return $query->whereColumn(array_combine(array_map(function($ownerKey) {
            return $hash.'.'.$ownerKey;
        }, $this->ownerKeys), $this->getQualifiedForeignKeyNames()));
    }

    /**
     * Adds the constraints for a relationship join.
     *
     * @link https://github.com/tylernathanreed/laravel-relation-joins
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  string  $type
     * @param  string|null  $alias
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationJoinQuery(Builder $query, Builder $parentQuery, $type = 'inner', $alias = null)
    {
        if (is_null($alias) && $query->getQuery()->from == $parentQuery->getQuery()->from) {
            $alias = $this->getRelationCountHash();
        }

        if (! is_null($alias) && $alias != $query->getModel()->getTable()) {
            $query->from($query->getModel()->getTable().' as '.$alias);

            $query->getModel()->setTable($alias);
        }

        return $query->whereColumn(array_combine($this->getQualifiedForeignKeyNames(), array_map(function($ownerKey) use ($query) {
            return $query->qualifyColumn($ownerKey);
        }, $this->ownerKeys)));
    }

    /**
     * Get a relationship join table hash.
     *
     * @return string
     */
    public function getRelationCountHash()
    {
        return 'laravel_reserved_'.static::$selfJoinCount++;
    }

    /**
     * Determine if the related model has an auto-incrementing ID.
     *
     * @return bool
     */
    protected function relationHasIncrementingId()
    {
        return $this->related->getIncrementing() && $this->related->getKeyType() === 'int';
    }

    /**
     * Make a new related instance for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function newRelatedInstanceFor(Model $parent)
    {
        return $this->related->newInstance();
    }

    /**
     * Get the child of the relationship.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getChild()
    {
        return $this->child;
    }

    /**
     * Get the foreign keys of the relationship.
     *
     * @return array
     */
    public function getForeignKeyNames()
    {
        return $this->foreignKeys;
    }

    /**
     * Get the fully qualified foreign keys of the relationship.
     *
     * @return array
     */
    public function getQualifiedForeignKeyNames()
    {
        return array_map(function($foreignKey) {
            return $this->child->qualifyColumn($foreignKey);
        }, $this->foreignKeys);
    }

    /**
     * Get the associated keys of the relationship.
     *
     * @return array
     */
    public function getOwnerKeyNames()
    {
        return $this->ownerKeys;
    }

    /**
     * Get the fully qualified associated keys of the relationship.
     *
     * @return array
     */
    public function getQualifiedOwnerKeyNames()
    {
        return array_map(function($ownerKey) {
            return $this->related->qualifyColumn($ownerKey);
        }, $this->ownerKeys);
    }

    /**
     * Get the name of the relationship.
     *
     * @return string
     */
    public function getRelationName()
    {
        return $this->relationName;
    }

    /**
     * Get the name of the relationship.
     *
     * @return string
     * @deprecated The getRelationName() method should be used instead. Will be removed in Laravel 6.0.
     */
    public function getRelation()
    {
        return $this->relationName;
    }
}
