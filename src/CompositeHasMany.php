<?php

namespace Reedware\LaravelCompositeRelations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TRelatedModel of Model
 *
 * @extends CompositeHasOneOrMany<TRelatedModel>
 */
class CompositeHasMany extends CompositeHasOneOrMany
{
    /**
     * Get the results of the relationship.
     *
     * @return Collection<int,TRelatedModel>
     */
    public function getResults(): Collection
    {
        return ! empty(array_filter($this->getParentKeys()))
            ? $this->query->get()
            : $this->related->newCollection();
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array<int,TRelatedModel>  $models
     * @param  string  $relation
     * @return array<int,TRelatedModel>
     */
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
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
        return $this->matchMany($models, $results, $relation);
    }
}
