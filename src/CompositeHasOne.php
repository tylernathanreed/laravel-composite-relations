<?php

namespace Reedware\LaravelCompositeRelations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;

class CompositeHasOne extends CompositeHasOneOrMany
{
    use SupportsDefaultModels;

    /**
     * Get the results of the relationship.
     */
    public function getResults(): ?Model
    {
        foreach ($this->getParentKeys() as $parentKey) {
            if (is_null($parentKey)) {
                return $this->getDefaultFor($this->parent);
            }
        }

        return $this->query->first() ?: $this->getDefaultFor($this->parent);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param array<int,Model> $models
     * @param  string  $relation
     * @return array<int,Model>
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
     * @param array<int,Model> $models
     * @param Collection<int,Model> $results
     * @param  string  $relation
     * @return array<int,Model>
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Make a new related instance for the given model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function newRelatedInstanceFor(Model $parent)
    {
        $instance = $this->related->newInstance();

        foreach ($this->getForeignKeyNames() as $index => $foreignKeyName) {
            $instance->setAttribute($foreignKeyName, $parent->{$this->localKeys[$index]});
        }

        return $instance;
    }
}
