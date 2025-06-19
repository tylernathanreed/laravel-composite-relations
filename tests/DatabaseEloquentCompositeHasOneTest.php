<?php

namespace Reedware\LaravelCompositeRelations\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Reedware\LaravelCompositeRelations\CompositeHasOne;

class DatabaseEloquentCompositeHasOneTest extends TestCase
{
    use Concerns\RunsIntegrationQueries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        m::close();

        $this->tearDownDatabase();
    }

    public function test_has_one_without_default()
    {
        $relation = $this->getRelation();

        $result = $relation->getResults();

        $this->assertEquals(null, $result);
    }

    public function test_has_one_with_default()
    {
        $relation = $this->getRelation()->withDefault();

        $result = $relation->getResults();

        $this->assertEquals(EloquentTaskImportSummaryModelStub::class, get_class($result));
        $this->assertEquals(false, $result->exists);
        $this->assertEquals('ABC-123', $result->task_vendor_id);
        $this->assertEquals('ABC', $result->task_vendor_name);
    }

    public function test_has_one_with_dynamic_default()
    {
        $relation = $this->getRelation()->withDefault(function ($newModel) {
            $newModel->summary = 'go to the store';
        });

        $result = $relation->getResults();

        $this->assertEquals(EloquentTaskImportSummaryModelStub::class, get_class($result));
        $this->assertEquals(false, $result->exists);
        $this->assertEquals('go to the store', $result->summary);
        $this->assertEquals('ABC-123', $result->task_vendor_id);
        $this->assertEquals('ABC', $result->task_vendor_name);
    }

    public function test_has_one_with_array_default()
    {
        $relation = $this->getRelation()->withDefault(['summary' => 'go to the store']);

        $result = $relation->getResults();

        $this->assertEquals(EloquentTaskImportSummaryModelStub::class, get_class($result));
        $this->assertEquals(false, $result->exists);
        $this->assertEquals('go to the store', $result->summary);
        $this->assertEquals('ABC-123', $result->task_vendor_id);
        $this->assertEquals('ABC', $result->task_vendor_name);
    }

    public function test_make_method_does_not_save_new_model()
    {
        $relation = $this->getRelation();

        $result = $relation->make(['summary' => 'go to the store']);

        $this->assertEquals(EloquentTaskImportSummaryModelStub::class, get_class($result));
        $this->assertEquals(false, $result->exists);
        $this->assertEquals('go to the store', $result->summary);
        $this->assertEquals('ABC-123', $result->task_vendor_id);
        $this->assertEquals('ABC', $result->task_vendor_name);
    }

    public function test_save_method_sets_foreign_key_on_model()
    {
        $relation = $this->getRelation();
        $mockModel = $this->getMockBuilder(Model::class)->onlyMethods(['save'])->getMock();
        $mockModel->expects($this->once())->method('save')->willReturn(true);
        $result = $relation->save($mockModel);

        $attributes = $result->getAttributes();

        $this->assertEquals('ABC-123', $attributes['task_vendor_id']);
        $this->assertEquals('ABC', $attributes['task_vendor_name']);
    }

    public function test_create_method_properly_creates_new_model()
    {
        $relation = $this->getRelation();

        $instance = null;

        Carbon::setTestNow($now = Carbon::now());

        $log = $this->db->pretend(function () use (&$instance, $relation) {
            $instance = $relation->create(['summary' => 'go to the store']);
        });

        $this->assertEquals(1, count($log));
        $this->assertEquals(sprintf(
            'insert into "task_import_summaries" ("summary", "task_vendor_id", "task_vendor_name", "updated_at", "created_at") values (%s, %s, %s, %s, %s)',
            "'go to the store'",
            "'ABC-123'",
            "'ABC'",
            '\''.$now->toDateTimeString().'\'',
            '\''.$now->toDateTimeString().'\'',
        ), $log[0]['query']);

        $this->assertEquals('go to the store', $instance->summary);
        $this->assertEquals(true, $instance->exists);
    }

    public function test_relation_is_properly_initialized()
    {
        $relation = $this->getRelation();
        $model = m::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('setRelation')->once()->with('foo', null);
        $models = $relation->initRelation([$model], 'foo');

        $this->assertEquals([$model], $models);
    }

    public function test_eager_constraints_are_properly_added()
    {
        $relation = Relation::noConstraints(function () {
            return $this->getRelation();
        });

        $models = [
            new EloquentTaskModelStub,
            new EloquentTaskModelStub,
            new EloquentTaskModelStub,
        ];

        $models[0]->vendor_id = 'ABC-001';
        $models[0]->vendor_name = 'ABC';
        $models[1]->vendor_id = 'ABC-001';
        $models[1]->vendor_name = 'ABC';
        $models[2]->vendor_id = 0;
        $models[2]->vendor_name = 'XYZ';

        $relation->addEagerConstraints($models);

        $this->assertEquals('select * from "task_import_summaries" where (("task_import_summaries"."task_vendor_id" = ? or "task_import_summaries"."task_vendor_name" = ?) or ("task_import_summaries"."task_vendor_id" = ? or "task_import_summaries"."task_vendor_name" = ?))', $relation->getQuery()->toSql());
        $this->assertEquals(['ABC-001', 'ABC', 0, 'XYZ'], $relation->getQuery()->getBindings());
    }

    public function test_eager_constraints_are_properly_added_using_and_glue()
    {
        $relation = Relation::noConstraints(function () {
            return $this->getRelation(null, 'and');
        });

        $models = [
            new EloquentTaskModelStub,
            new EloquentTaskModelStub,
            new EloquentTaskModelStub,
        ];

        $models[0]->vendor_id = 'ABC-001';
        $models[0]->vendor_name = 'ABC';
        $models[1]->vendor_id = 'ABC-001';
        $models[1]->vendor_name = 'ABC';
        $models[2]->vendor_id = 0;
        $models[2]->vendor_name = 'XYZ';

        $relation->addEagerConstraints($models);

        $this->assertEquals('select * from "task_import_summaries" where (("task_import_summaries"."task_vendor_id" = ? and "task_import_summaries"."task_vendor_name" = ?) or ("task_import_summaries"."task_vendor_id" = ? and "task_import_summaries"."task_vendor_name" = ?))', $relation->getQuery()->toSql());
        $this->assertEquals(['ABC-001', 'ABC', 0, 'XYZ'], $relation->getQuery()->getBindings());
    }

    public function test_models_are_properly_matched_to_parents()
    {
        $relation = $this->getRelation();

        $results = [
            new EloquentTaskImportSummaryModelStub,
            new EloquentTaskImportSummaryModelStub,
        ];

        $results[0]->task_vendor_id = 'ABC-001';
        $results[0]->task_vendor_name = 'ABC';
        $results[1]->task_vendor_id = 0;
        $results[1]->task_vendor_name = 'XYZ';

        $models = [
            new EloquentTaskModelStub,
            new EloquentTaskModelStub,
            new EloquentTaskModelStub,
        ];

        $models[0]->vendor_id = 'ABC-001';
        $models[0]->vendor_name = 'ABC';
        $models[1]->vendor_id = 'ABC-002';
        $models[1]->vendor_name = 'ABC';
        $models[2]->vendor_id = 0;
        $models[2]->vendor_name = 'XYZ';

        $models = $relation->match($models, new Collection($results), 'foo');

        $this->assertEquals(EloquentTaskImportSummaryModelStub::class, get_class($models[0]->foo));
        $this->assertEquals('ABC-001', $models[0]->foo->task_vendor_id);
        $this->assertEquals('ABC', $models[0]->foo->task_vendor_name);

        $this->assertNull($models[1]->foo);

        $this->assertEquals(EloquentTaskImportSummaryModelStub::class, get_class($models[0]->foo));
        $this->assertEquals(0, $models[2]->foo->task_vendor_id);
        $this->assertEquals('XYZ', $models[2]->foo->task_vendor_name);
    }

    public function test_relation_count_query_can_be_built()
    {
        $relation = $this->getRelation(new EloquentTaskModelStub);

        $parentQuery = (new EloquentTaskModelStub)->newQuery();
        $query = (new EloquentTaskImportSummaryModelStub)->newQuery();

        $relation->getRelationExistenceCountQuery($query, $parentQuery);

        $this->assertEquals('select count(*) from "task_import_summaries" where ("tasks"."vendor_id" = "task_import_summaries"."task_vendor_id" and "tasks"."vendor_name" = "task_import_summaries"."task_vendor_name")', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    protected function getRelation($parent = null, $glue = 'or')
    {
        $builder = (new EloquentTaskImportSummaryModelStub)->newQuery();

        if (is_null($parent)) {

            $parent = m::mock('Illuminate\Database\Eloquent\Model');
            $parent->shouldReceive('getAttribute')->with('vendor_id')->andReturn('ABC-123');
            $parent->shouldReceive('getAttribute')->with('vendor_name')->andReturn('ABC');
            $parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
            $parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

        }

        return new CompositeHasOne($builder, $parent, ['task_import_summaries.task_vendor_id', 'task_import_summaries.task_vendor_name'], ['vendor_id', 'vendor_name'], $glue);
    }
}
