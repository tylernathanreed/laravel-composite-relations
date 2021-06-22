<?php

namespace Reedware\LaravelCompositeRelations\Tests;

use Mockery as m;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Reedware\LaravelCompositeRelations\CompositeHasMany;

class DatabaseEloquentCompositeHasManyTest extends TestCase
{
    use Concerns\RunsIntegrationQueries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    public function tearDown(): void
    {
        m::close();

        $this->tearDownDatabase();
    }

    public function testMakeMethodDoesNotSaveNewModel()
    {
        $relation = $this->getRelation();
        $instance = $relation->make(['data_index' => 0, 'data_value' => 'milk']);

        $this->assertEquals(0, $instance->data_index);
        $this->assertEquals('milk', $instance->data_value);
        $this->assertEquals(false, $instance->exists);
    }

    public function testCreateMethodProperlyCreatesNewModel()
    {
        $relation = $this->getRelation();

        $instance = null;

        Carbon::setTestNow($now = Carbon::now());

        $log = $this->db->pretend(function() use (&$instance, $relation) {
            $instance = $relation->create(['data_index' => 0, 'data_value' => 'milk']);
        });

        $this->assertEquals(1, count($log));
        $this->assertEquals('insert into "task_import_data" ("data_index", "data_value", "task_vendor_id", "task_vendor_name", "updated_at", "created_at") values (?, ?, ?, ?, ?, ?)', $log[0]['query']);
        $this->assertEquals([0, 'milk', 'ABC-123', 'ABC', $now->toDateTimeString(), $now->toDateTimeString()], $log[0]['bindings']);

        $this->assertEquals(0, $instance->data_index);
        $this->assertEquals('milk', $instance->data_value);
        $this->assertEquals(true, $instance->exists);
    }

    public function testFindOrNewMethodFindsModelWithForeignKeySet()
    {
        $relation = $this->getRelation();

        $this->assertInstanceOf(EloquentTaskImportDataModelStub::class, $instance = $relation->findOrNew('foo'));
        $this->assertEquals(false, $instance->exists);
        $this->assertEquals('ABC-123', $instance->task_vendor_id);
        $this->assertEquals('ABC', $instance->task_vendor_name);
    }

    public function testFirstOrNewMethodFindsFirstModelWithForeignKeySet()
    {
        $parent = EloquentTaskModelStub::find(1);
        $relation = $this->getRelation($parent);

        $this->assertInstanceOf(EloquentTaskImportDataModelStub::class, $instance = $relation->firstOrNew(['data_index' => 0]));
        $this->assertEquals(true, $instance->exists);
        $this->assertEquals(false, $instance->wasRecentlyCreated);
        $this->assertEquals('ABC-001', $instance->task_vendor_id);
        $this->assertEquals('ABC', $instance->task_vendor_name);
        $this->assertEquals(0, $instance->data_index);
        $this->assertEquals('milk', $instance->data_value);
    }

    public function testFirstOrNewMethodWithValuesFindsFirstModelWithForeignKeySet()
    {
        $parent = EloquentTaskModelStub::find(1);
        $relation = $this->getRelation($parent);

        $this->assertInstanceOf(EloquentTaskImportDataModelStub::class, $instance = $relation->firstOrNew(['data_index' => 0], ['data_value' => 'carrots']));

        $this->assertEquals(true, $instance->exists);
        $this->assertEquals(false, $instance->wasRecentlyCreated);
        $this->assertEquals('ABC-001', $instance->task_vendor_id);
        $this->assertEquals('ABC', $instance->task_vendor_name);
        $this->assertEquals(0, $instance->data_index);
        $this->assertEquals('milk', $instance->data_value);
    }

    public function testFirstOrCreateMethodFindsFirstModelWithForeignKeySet()
    {
        $parent = EloquentTaskModelStub::find(1);
        $relation = $this->getRelation($parent);

        $this->assertInstanceOf(EloquentTaskImportDataModelStub::class, $instance = $relation->firstOrNew(['data_index' => 0]));

        $this->assertEquals(true, $instance->exists);
        $this->assertEquals(false, $instance->wasRecentlyCreated);
        $this->assertEquals('ABC-001', $instance->task_vendor_id);
        $this->assertEquals('ABC', $instance->task_vendor_name);
        $this->assertEquals(0, $instance->data_index);
        $this->assertEquals('milk', $instance->data_value);
    }

    public function testFirstOrCreateMethodWithValuesFindsFirstModelWithForeignKeySet()
    {
        $parent = EloquentTaskModelStub::find(1);
        $relation = $this->getRelation($parent);

        $this->assertInstanceOf(EloquentTaskImportDataModelStub::class, $instance = $relation->firstOrNew(['data_index' => 0], ['data_value' => 'carrots']));

        $this->assertEquals(true, $instance->exists);
        $this->assertEquals(false, $instance->wasRecentlyCreated);
        $this->assertEquals('ABC-001', $instance->task_vendor_id);
        $this->assertEquals('ABC', $instance->task_vendor_name);
        $this->assertEquals(0, $instance->data_index);
        $this->assertEquals('milk', $instance->data_value);
    }

    public function testFirstOrCreateMethodCreatesNewModelWithForeignKeySet()
    {
        $parent = EloquentTaskModelStub::find(1);
        $relation = $this->getRelation($parent);

        $instance = null;

        Carbon::setTestNow($now = Carbon::now());

        $log = $this->db->pretend(function() use (&$instance, $relation) {
            $instance = $relation->firstOrCreate(['data_index' => 1], ['data_value' => 'carrots']);
        });

        $this->assertEquals(2, count($log));
        $this->assertEquals('select * from "task_import_data" where "task_vendor_id" = ? and "task_vendor_id" is not null and "task_vendor_name" = ? and "task_vendor_name" is not null and ("data_index" = ?) limit 1', $log[0]['query']);
        $this->assertEquals(['ABC-001', 'ABC', 1], $log[0]['bindings']);
        $this->assertEquals('insert into "task_import_data" ("data_index", "data_value", "task_vendor_id", "task_vendor_name", "updated_at", "created_at") values (?, ?, ?, ?, ?, ?)', $log[1]['query']);
        $this->assertEquals([1, 'carrots', 'ABC-001', 'ABC', $now->toDateTimeString(), $now->toDateTimeString()], $log[1]['bindings']);

        $this->assertInstanceOf(EloquentTaskImportDataModelStub::class, $instance);

        $this->assertEquals(true, $instance->exists);
        $this->assertEquals(true, $instance->wasRecentlyCreated);
        $this->assertEquals('ABC-001', $instance->task_vendor_id);
        $this->assertEquals('ABC', $instance->task_vendor_name);
        $this->assertEquals(1, $instance->data_index);
        $this->assertEquals('carrots', $instance->data_value);

    }

    public function testUpdateOrCreateMethodFindsFirstModelAndUpdates()
    {
        $parent = EloquentTaskModelStub::find(1);
        $relation = $this->getRelation($parent);

        $instance = null;

        Carbon::setTestNow($now = Carbon::now());

        $this->db->enableQueryLog();

        $instance = $relation->updateOrCreate(['data_index' => 0], ['data_value' => 'carrots']);

        $log = $this->db->getQueryLog();

        $this->assertEquals(2, count($log));
        $this->assertEquals('select * from "task_import_data" where "task_vendor_id" = ? and "task_vendor_id" is not null and "task_vendor_name" = ? and "task_vendor_name" is not null and ("data_index" = ?) limit 1', $log[0]['query']);
        $this->assertEquals(['ABC-001', 'ABC', 0], $log[0]['bindings']);
        $this->assertEquals('update "task_import_data" set "data_value" = ?, "updated_at" = ? where "id" = ?', $log[1]['query']);
        $this->assertEquals(['carrots', $now->toDateTimeString(), 1], $log[1]['bindings']);

        $this->assertInstanceOf(EloquentTaskImportDataModelStub::class, $instance);

        $this->assertEquals(true, $instance->exists);
        $this->assertEquals(false, $instance->wasRecentlyCreated);
        $this->assertEquals('ABC-001', $instance->task_vendor_id);
        $this->assertEquals('ABC', $instance->task_vendor_name);
        $this->assertEquals(0, $instance->data_index);
        $this->assertEquals('carrots', $instance->data_value);
    }

    public function testUpdateOrCreateMethodCreatesNewModelWithForeignKeySet()
    {
        $parent = EloquentTaskModelStub::find(1);
        $relation = $this->getRelation($parent);

        $instance = null;

        Carbon::setTestNow($now = Carbon::now());

        $this->db->enableQueryLog();

        $instance = $relation->updateOrCreate(['data_index' => 1], ['data_value' => 'carrots']);

        $log = $this->db->getQueryLog();

        $this->assertEquals(2, count($log));
        $this->assertEquals('select * from "task_import_data" where "task_vendor_id" = ? and "task_vendor_id" is not null and "task_vendor_name" = ? and "task_vendor_name" is not null and ("data_index" = ?) limit 1', $log[0]['query']);
        $this->assertEquals(['ABC-001', 'ABC', 1], $log[0]['bindings']);
        $this->assertEquals('insert into "task_import_data" ("data_index", "task_vendor_id", "task_vendor_name", "data_value", "updated_at", "created_at") values (?, ?, ?, ?, ?, ?)', $log[1]['query']);
        $this->assertEquals([1, 'ABC-001', 'ABC', 'carrots', $now->toDateTimeString(), $now->toDateTimeString()], $log[1]['bindings']);

        $this->assertInstanceOf(EloquentTaskImportDataModelStub::class, $instance);

        $this->assertEquals(true, $instance->exists);
        $this->assertEquals(true, $instance->wasRecentlyCreated);
        $this->assertEquals('ABC-001', $instance->task_vendor_id);
        $this->assertEquals('ABC', $instance->task_vendor_name);
        $this->assertEquals(1, $instance->data_index);
        $this->assertEquals('carrots', $instance->data_value);
    }

    public function testRelationIsProperlyInitialized()
    {
        $relation = $this->getRelation();

        $model = m::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('setRelation')->once()->with('foo', m::type('Illuminate\Database\Eloquent\Collection'));

        $models = $relation->initRelation([$model], 'foo');

        $this->assertEquals([$model], $models);
    }

    public function testEagerConstraintsAreProperlyAdded()
    {
        $relation = Relation::noConstraints(function() {
            return $this->getRelation();
        });

        $models = [
            new EloquentTaskModelStub,
            new EloquentTaskModelStub,
            new EloquentTaskModelStub
        ];

        $models[0]->vendor_id = 'ABC-001';
        $models[0]->vendor_name = 'ABC';
        $models[1]->vendor_id = 'ABC-001';
        $models[1]->vendor_name = 'ABC';
        $models[2]->vendor_id = 0;
        $models[2]->vendor_name = 'XYZ';

        $relation->addEagerConstraints($models);

        $this->assertEquals('select * from "task_import_data" where (("task_vendor_id" = ? or "task_vendor_name" = ?) or ("task_vendor_id" = ? or "task_vendor_name" = ?))', $relation->getQuery()->toSql());
        $this->assertEquals(['ABC-001', 'ABC', 0, 'XYZ'], $relation->getQuery()->getBindings());
    }

    public function testModelsAreProperlyMatchedToParents()
    {
        $relation = $this->getRelation();

        $results = [
            new EloquentTaskImportDataModelStub,
            new EloquentTaskImportDataModelStub,
            new EloquentTaskImportDataModelStub
        ];

        $results[0]->task_vendor_id = 'ABC-001';
        $results[0]->task_vendor_name = 'ABC';
        $results[1]->task_vendor_id = 'ABC-001';
        $results[1]->task_vendor_name = 'ABC';
        $results[2]->task_vendor_id = 0;
        $results[2]->task_vendor_name = 'XYZ';

        $models = [
            new EloquentTaskModelStub,
            new EloquentTaskModelStub,
            new EloquentTaskModelStub
        ];

        $models[0]->vendor_id = 'ABC-001';
        $models[0]->vendor_name = 'ABC';
        $models[1]->vendor_id = 'ABC-002';
        $models[1]->vendor_name = 'ABC';
        $models[2]->vendor_id = 0;
        $models[2]->vendor_name = 'XYZ';

        $models = $relation->match($models, new Collection($results), 'foo');

        $this->assertEquals(2, count($models[0]->foo));
        $this->assertEquals('ABC-001', $models[0]->foo[0]->task_vendor_id);
        $this->assertEquals('ABC', $models[0]->foo[0]->task_vendor_name);
        $this->assertEquals('ABC-001', $models[0]->foo[1]->task_vendor_id);
        $this->assertEquals('ABC', $models[0]->foo[1]->task_vendor_name);

        $this->assertNull($models[1]->foo);

        $this->assertEquals(1, count($models[2]->foo));
        $this->assertEquals(0, $models[2]->foo[0]->task_vendor_id);
        $this->assertEquals('XYZ', $models[2]->foo[0]->task_vendor_name);
    }

    public function testCreateManyCreatesARelatedModelForEachRecord()
    {
        $parent = EloquentTaskModelStub::find(1);
        $relation = $this->getRelation($parent);

        $records = [
            ['data_index' => 1, 'data_value' => 'carrots'],
            ['data_index' => 2, 'data_value' => 'peas'],
        ];

        $instances = $relation->createMany($records);

        $this->assertInstanceOf(Collection::class, $instances);
        $this->assertEquals(2, count($instances));

        $this->assertEquals('ABC', $instances[0]->task_vendor_name);
        $this->assertEquals('ABC-001', $instances[0]->task_vendor_id);
        $this->assertEquals(1, $instances[0]->data_index);
        $this->assertEquals('carrots', $instances[0]->data_value);

        $this->assertEquals('ABC', $instances[1]->task_vendor_name);
        $this->assertEquals('ABC-001', $instances[1]->task_vendor_id);
        $this->assertEquals(2, $instances[1]->data_index);
        $this->assertEquals('peas', $instances[1]->data_value);
    }

    protected function getRelation($parent = null)
    {
        $related = new EloquentTaskModelStub;

        $builder = (new EloquentTaskImportDataModelStub)->newQuery();

        if(is_null($parent)) {

            $parent = m::mock('Illuminate\Database\Eloquent\Model');
            $parent->shouldReceive('getAttribute')->with('vendor_id')->andReturn('ABC-123');
            $parent->shouldReceive('getAttribute')->with('vendor_name')->andReturn('ABC');
            $parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
            $parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

        }

        return new CompositeHasMany($builder, $parent, ['task_vendor_id', 'task_vendor_name'], ['vendor_id', 'vendor_name']);
    }
}
