<?php

namespace Reedware\LaravelCompositeRelations\Tests;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Reedware\LaravelCompositeRelations\CompositeBelongsTo;

class DatabaseEloquentCompositeBelongsToTest extends TestCase
{
    use Concerns\RunsIntegrationQueries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    public function tearDown()
    {
        m::close();

        $this->tearDownDatabase();
    }

    public function testBelongsToWithoutDefault()
    {
        $relation = $this->getRelation();

        $result = $relation->getResults();

        $this->assertEquals(null, $result);
    }

    public function testBelongsToWithDefault()
    {
        $relation = $this->getRelation()->withDefault();

        $result = $relation->getResults();

        $this->assertEquals(EloquentTaskModelStub::class, get_class($result));
        $this->assertEquals(false, $result->exists);
    }

    public function testBelongsToWithDynamicDefault()
    {
        $relation = $this->getRelation()->withDefault(function ($newModel) {
            $newModel->name = 'go shopping';
        });

        $result = $relation->getResults();

        $this->assertEquals(EloquentTaskModelStub::class, get_class($result));
        $this->assertEquals(false, $result->exists);
        $this->assertEquals('go shopping', $result->name);
    }

    public function testBelongsToWithArrayDefault()
    {
        $relation = $this->getRelation()->withDefault(['name' => 'go shopping']);

        $result = $relation->getResults();

        $this->assertEquals(EloquentTaskModelStub::class, get_class($result));
        $this->assertEquals(false, $result->exists);
        $this->assertEquals('go shopping', $result->name);
    }

    public function testUpdateMethodRetrievesModelAndUpdates()
    {
        $summary = EloquentTaskImportSummaryModelStub::find(1);

        $this->assertTrue($summary->task()->update(['name' => 'go to the store']));

        $task = EloquentTaskModelStub::find(1);

        $this->assertEquals('go to the store', $task->name);
    }

    public function testEagerConstraintsAreProperlyAdded()
    {
        $relation = Relation::noConstraints(function() {
            return $this->getRelation();
        });

        $stubs = [
            new EloquentTaskImportSummaryModelStub(['task_vendor_id' => 'ABC-123', 'task_vendor_name' => 'ABC']),
            new EloquentTaskImportSummaryModelStub(['task_vendor_id' => 'ABC-123', 'task_vendor_name' => 'ABC']),
            new EloquentTaskImportSummaryModelStub(['task_vendor_id' => 'XYZ-123', 'task_vendor_name' => 'XYZ'])
        ];

        $relation->addEagerConstraints($stubs);

        $this->assertEquals('select * from "tasks" where (("tasks"."vendor_id" = ? or "tasks"."vendor_name" = ?) or ("tasks"."vendor_id" = ? or "tasks"."vendor_name" = ?))', $relation->getQuery()->toSql());
        $this->assertEquals(['ABC-123', 'ABC', 'XYZ-123', 'XYZ'], $relation->getQuery()->getBindings());
    }

    public function testIdsInEagerConstraintsCanBeZero()
    {
        $relation = Relation::noConstraints(function() {
            return $this->getRelation();
        });

        $stubs = [
            new EloquentTaskImportSummaryModelStub(['task_vendor_name' => 'ABC', 'task_vendor_id' => 'ABC-123']),
            new EloquentTaskImportSummaryModelStub(['task_vendor_name' => 'QWE', 'task_vendor_id' => 0])
        ];

        $relation->addEagerConstraints($stubs);

        $this->assertEquals('select * from "tasks" where (("tasks"."vendor_id" = ? or "tasks"."vendor_name" = ?) or ("tasks"."vendor_id" = ? or "tasks"."vendor_name" = ?))', $relation->getQuery()->toSql());
        $this->assertEquals(['ABC-123', 'ABC', '0', 'QWE'], $relation->getQuery()->getBindings());
    }

    public function testRelationIsProperlyInitialized()
    {
        $relation = $this->getRelation();
        $model = m::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('setRelation')->once()->with('foo', null);
        $models = $relation->initRelation([$model], 'foo');

        $this->assertEquals([$model], $models);
    }

    public function testModelsAreProperlyMatchedToParents()
    {
        $relation = $this->getRelation();
        $result1 = m::mock('stdClass');
        $result1->shouldReceive('getAttribute')->with('vendor_id')->andReturn(1);
        $result1->shouldReceive('getAttribute')->with('vendor_name')->andReturn('ABC');
        $result2 = m::mock('stdClass');
        $result2->shouldReceive('getAttribute')->with('vendor_id')->andReturn(2);
        $result2->shouldReceive('getAttribute')->with('vendor_name')->andReturn('XYZ');
        $model1 = new EloquentBelongsToModelStub;
        $model1->task_vendor_id = 1;
        $model1->task_vendor_name = 'ABC';
        $model2 = new EloquentBelongsToModelStub;
        $model2->task_vendor_id = 2;
        $model2->task_vendor_name = 'XYZ';
        $models = $relation->match([$model1, $model2], new Collection([$result1, $result2]), 'foo');

        $this->assertEquals(1, $models[0]->foo->getAttribute('vendor_id'));
        $this->assertEquals('ABC', $models[0]->foo->getAttribute('vendor_name'));
        $this->assertEquals(2, $models[1]->foo->getAttribute('vendor_id'));
        $this->assertEquals('XYZ', $models[1]->foo->getAttribute('vendor_name'));
    }

    public function testAssociateMethodSetsForeignKeyOnModel()
    {
        $child = m::mock('Illuminate\Database\Eloquent\Model');
        $child->shouldReceive('getAttribute')->once()->with('task_vendor_id')->andReturn('ABC-123');
        $child->shouldReceive('getAttribute')->once()->with('task_vendor_name')->andReturn('ABC');

        $relation = $this->getRelation($child);

        $parent = m::mock('Illuminate\Database\Eloquent\Model');
        $parent->shouldReceive('getAttribute')->once()->with('vendor_id')->andReturn('ABC-123');
        $parent->shouldReceive('getAttribute')->once()->with('vendor_name')->andReturn('ABC');

        $child->shouldReceive('setAttribute')->once()->with('task_vendor_id', 'ABC-123');
        $child->shouldReceive('setAttribute')->once()->with('task_vendor_name', 'ABC');
        $child->shouldReceive('setRelation')->once()->with('relation', $parent);

        $relation->associate($parent);
    }

    public function testDissociateMethodUnsetsForeignKeyOnModel()
    {
        $child = m::mock('Illuminate\Database\Eloquent\Model');
        $child->shouldReceive('getAttribute')->once()->with('task_vendor_id')->andReturn('ABC-123');
        $child->shouldReceive('getAttribute')->once()->with('task_vendor_name')->andReturn('ABC');

        $relation = $this->getRelation($child);

        $child->shouldReceive('setAttribute')->once()->with('task_vendor_id', null);
        $child->shouldReceive('setAttribute')->once()->with('task_vendor_name', null);
        $child->shouldReceive('setRelation')->once()->with('relation', null);

        $relation->dissociate();
    }

    public function testAssociateMethodSetsForeignKeyOnModelByIds()
    {
        $child = m::mock('Illuminate\Database\Eloquent\Model');
        $child->shouldReceive('getAttribute')->once()->with('task_vendor_id')->andReturn('ABC-123');
        $child->shouldReceive('getAttribute')->once()->with('task_vendor_name')->andReturn('ABC');

        $relation = $this->getRelation($child);

        $child->shouldReceive('setAttribute')->once()->with('task_vendor_id', 'XYZ-123');
        $child->shouldReceive('setAttribute')->once()->with('task_vendor_name', 'XYZ');

        $relation->associate(['XYZ-123', 'XYZ']);
    }

    protected function getRelation($child = null, $incrementing = true, $keyType = 'int')
    {
        if(is_null($child)) {
            return (new EloquentTaskImportSummaryModelStub)->task();
        }

        return new CompositeBelongsTo(
            (new EloquentTaskModelStub)->newQuery(), $child, ['task_vendor_id', 'task_vendor_name'], ['vendor_id', 'vendor_name'], 'relation'
        );
    }
}

class EloquentBelongsToModelStub extends EloquentCompositeRelationModelStub
{

}