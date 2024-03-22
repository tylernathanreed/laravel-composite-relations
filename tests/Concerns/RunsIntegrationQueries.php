<?php

namespace Reedware\LaravelCompositeRelations\Tests\Concerns;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Reedware\LaravelCompositeRelations\Tests\EloquentTaskModelStub;

trait RunsIntegrationQueries
{
    /**
     * The database connection implementation.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $db;

    /**
     * Setup the database.
     *
     * @return void
     */
    protected function setUpDatabase()
    {
        $db = new DB;

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->bootEloquent();
        $db->setAsGlobal();

        $this->db = $db->connection();

        $this->createSchema();
        $this->seedData();
    }

    /**
     * Setup the database schema.
     *
     * @return void
     */
    public function createSchema()
    {
        $this->schema()->create('tasks', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('vendor_id');
            $table->string('vendor_name');
            $table->timestamps();
        });

        $this->schema()->create('task_import_summaries', function ($table) {
            $table->increments('id');
            $table->string('task_vendor_id');
            $table->string('task_vendor_name');
            $table->string('summary');
            $table->timestamps();
        });

        $this->schema()->create('task_import_data', function ($table) {
            $table->increments('id');
            $table->string('task_vendor_id');
            $table->string('task_vendor_name');
            $table->integer('data_index');
            $table->string('data_value');
            $table->timestamps();
        });
    }

    /**
     * Seeds the database with data.
     *
     * @return void
     */
    protected function seedData()
    {
        $this->seedTask([
            'id' => 1,
            'name' => 'go shopping',
            'vendor_name' => 'ABC',
            'vendor_id' => 'ABC-001',
            'summary' => [
                'summary' => 'get the things',
            ],
            'data' => [
                ['data_index' => 0, 'data_value' => 'milk'],
            ],
        ]);
    }

    /**
     * Seeds the specified data as a task in the database.
     *
     * @param  array  $task
     * @return void
     */
    protected function seedTask($task)
    {
        $summary = $task['summary'] ?? null;
        $data = $task['data'] ?? [];

        unset($task['summary']);
        unset($task['data']);

        $task = EloquentTaskModelStub::create($task);

        if (! is_null($summary)) {
            $task->importSummary()->create($summary);
        }

        if (! empty($data)) {
            $task->importData()->createMany($data);
        }
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    protected function tearDownDatabase()
    {
        $this->schema()->drop('tasks');
        $this->schema()->drop('task_import_summaries');
        $this->schema()->drop('task_import_data');
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }
}
