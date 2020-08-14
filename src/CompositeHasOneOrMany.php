<?php

namespace Reedware\LaravelCompositeRelations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class CompositeHasOneOrMany extends Relation
{
    /**
     * The foreign key of the parent model.
     *
     * @var array
     */
    protected $foreignKeys;

    /**
     * The local key of the parent model.
     *
     * @var array
     */
    protected $localKeys;

    /**
     * The count of self joins.
     *
     * @var int
     */
    protected static $selfJoinCount = 0;

    /**
     * Create a new has one or many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  array  $foreignKeys
     * @param  array  $localKeys
     * @return void
     */
    public function __construct(Builder $query, Model $parent, array $foreignKeys, array $localKeys)
    {
        $this->localKeys = $localKeys;
        $this->foreignKeys = $foreignKeys;

        parent::__construct($query, $parent);
    }

    /**
     * Create and return an un-saved instance of the related model.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function make(array $attributes = [])
    {
        return tap($this->related->newInstance($attributes), function ($instance) {
            $this->setForeignAttributesForCreate($instance);
        });
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

        $this->query->where(function($query) {

            foreach($this->getParentKeys() as $index => $parentKey) {
                $this->query->where($this->foreignKeys[$index], '=', $parentKey);

                $this->query->whereNotNull($this->foreignKeys[$index]);
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
                $mapping = array_combine($this->foreignKeys, array_map(function($localKey) use ($model) {
                    return $model->{$localKey};
                }, $this->localKeys));

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
     * Match the eagerly loaded results to their single parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function matchOne(array $models, Collection $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'one');
    }

    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function matchMany(array $models, Collection $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'many');
    }

    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @param  string  $type
     * @return array
     */
    protected function matchOneOrMany(array $models, Collection $results, $relation, $type)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {

            $dictionaryKey = json_encode(array_map(function($localKey) use ($model) {
                return $model->getAttribute($localKey);
            }, $this->localKeys));

            if (isset($dictionary[$dictionaryKey])) {
                $model->setRelation(
                    $relation, $this->getRelationValue($dictionary, $dictionaryKey, $type)
                );
            }
        }

        return $models;
    }

    /**
     * Get the value of a relationship by one or many type.
     *
     * @param  array   $dictionary
     * @param  string  $key
     * @param  string  $type
     * @return mixed
     */
    protected function getRelationValue(array $dictionary, $key, $type)
    {
        $value = $dictionary[$key];

        return $type === 'one' ? reset($value) : $this->related->newCollection($value);
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $foreigns = $this->getForeignKeyNames();

        return $results->mapToDictionary(function ($result) use ($foreigns) {
            return [json_encode(array_map(function ($foreign) use ($result){
                return $result->{$foreign};
            }, $foreigns)) => $result];
        })->all();
    }

    /**
     * Find a model by its primary key or return new instance of the related model.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model
     */
    public function findOrNew($id, $columns = ['*'])
    {
        if (is_null($instance = $this->find($id, $columns))) {
            $instance = $this->related->newInstance();

            $this->setForeignAttributesForCreate($instance);
        }

        return $instance;
    }

    /**
     * Get the first related model record matching the attributes or instantiate it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function firstOrNew(array $attributes, array $values = [])
    {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->related->newInstance($attributes + $values);

            $this->setForeignAttributesForCreate($instance);
        }

        return $instance;
    }

    /**
     * Get the first related record matching the attributes or create it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function firstOrCreate(array $attributes, array $values = [])
    {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->create($attributes + $values);
        }

        return $instance;
    }

    /**
     * Create or update a related record matching the attributes, and fill it with values.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        return tap($this->firstOrNew($attributes), function ($instance) use ($values) {
            $instance->fill($values);

            $instance->save();
        });
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model|false
     */
    public function save(Model $model)
    {
        $this->setForeignAttributesForCreate($model);

        return $model->save() ? $model : false;
    }

    /**
     * Attach a collection of models to the parent instance.
     *
     * @param  iterable  $models
     * @return iterable
     */
    public function saveMany($models)
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $attributes = [])
    {
        return tap($this->related->newInstance($attributes), function ($instance) {
            $this->setForeignAttributesForCreate($instance);

            $instance->save();
        });
    }

    /**
     * Create a Collection of new instances of the related model.
     *
     * @param  array  $records
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function createMany(array $records)
    {
        $instances = $this->related->newCollection();

        foreach ($records as $record) {
            $instances->push($this->create($record));
        }

        return $instances;
    }

    /**
     * Set the foreign ID for creating a related model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    protected function setForeignAttributesForCreate(Model $model)
    {
        $parentKeys = $this->getParentKeys();

        foreach($this->getForeignKeyNames() as $index => $foreignKey) {
            $model->setAttribute($foreignKey, $parentKeys[$index]);
        }
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
        if ($query->getQuery()->from == $parentQuery->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }

        return $query->select($columns)->where(function($query) {
            $foreignKeys = $this->getQualifiedForeignKeyNames();

            foreach($this->getQualifiedParentKeyNames() as $index => $parentKey) {
                $query->whereColumn($parentKey, '=', $foreignKeys[$index]);
            }
        });
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
        $query->from($query->getModel()->getTable().' as '.$hash = $this->getRelationCountHash());

        $query->getModel()->setTable($hash);

        return $query->select($columns)->where(function($query) use ($hash) {
            $foreignKeys = $this->getForeignKeyNames();

            foreach($this->getQualifiedParentKeyNames() as $index => $parentKey) {
                $query->whereColumn($parentKey, '=', $hash.'.'.$foreignKeys[$index]);
            }
        });
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
     * Get the key value of the parent's local keys.
     *
     * @return array
     */
    public function getParentKeys()
    {
        return array_map(function($localKey) {
            return $this->parent->getAttribute($localKey);
        }, $this->localKeys);
    }

    /**
     * Get the fully qualified parent key names.
     *
     * @return string
     */
    public function getQualifiedParentKeyNames()
    {
        return array_map(function($localKey) {
            return $this->parent->qualifyColumn($localKey);
        }, $this->localKeys);
    }

    /**
     * Get the plain foreign keys.
     *
     * @return array
     */
    public function getForeignKeyNames()
    {
        return array_map(function($foreignKey) {
            $segments = explode('.', $foreignKey);

            return end($segments);
        }, $this->getQualifiedForeignKeyNames());
    }

    /**
     * Get the foreign keys for the relationship.
     *
     * @return array
     */
    public function getQualifiedForeignKeyNames()
    {
        return $this->foreignKeys;
    }

    /**
     * Get the local key for the relationship.
     *
     * @return array
     */
    public function getLocalKeyNames()
    {
        return $this->localKeys;
    }
}
