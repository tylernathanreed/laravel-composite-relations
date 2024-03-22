<?php

namespace Reedware\LaravelCompositeRelations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;

/**
 * @template TRelatedModel of Model
 *
 * @extends CompositeHasOneOrMany<TRelatedModel>
 */
class CompositeHasOne extends CompositeHasOneOrMany
{
    /** @use SupportsDefaultModels<TRelatedModel> */
    use SupportsDefaultModels;

    /**
     * Get the results of the relationship.
     *
     * @return ?TRelatedModel
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
     * @param  array<int,TRelatedModel>  $models
     * @param  string  $relation
     * @return array<int,TRelatedModel>
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
     * @param  array<int,TRelatedModel>  $models
     * @param  Collection<int,TRelatedModel>  $results
     * @param  string  $relation
     * @return array<int,TRelatedModel>
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Make a new related instance for the given model.
     *
     * @return TRelatedModel
     */
    public function newRelatedInstanceFor(Model $parent): Model
    {
        $instance = $this->related->newInstance();

        foreach ($this->getForeignKeyNames() as $index => $foreignKeyName) {
            $instance->setAttribute($foreignKeyName, $parent->{$this->localKeys[$index]});
        }

        return $instance;
    }
}
