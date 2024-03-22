<?php

namespace Reedware\LaravelCompositeRelations\Tests;

use Reedware\LaravelCompositeRelations\Tests\EloquentTaskImportDataModelStub as TaskImportData;
use Reedware\LaravelCompositeRelations\Tests\EloquentTaskModelStub as Task;

class EloquentTaskImportSummaryModelStub extends EloquentCompositeRelationModelStub
{
    protected $table = 'task_import_summaries';

    public function task($glue = 'or')
    {
        return $this->compositeBelongsTo(Task::class, ['task_vendor_id', 'task_vendor_name'], ['vendor_id', 'vendor_name'], null, $glue);
    }

    public function importData($glue = 'or')
    {
        return $this->compositeHasMany(TaskImportData::class, ['task_vendor_id', 'task_vendor_name'], ['task_vendor_id', 'task_vendor_name'], $glue);
    }
}
