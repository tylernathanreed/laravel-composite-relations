<?php

namespace Reedware\LaravelCompositeRelations\Tests;

use Reedware\LaravelCompositeRelations\Tests\EloquentTaskImportDataModelStub as TaskImportData;
use Reedware\LaravelCompositeRelations\Tests\EloquentTaskImportSummaryModelStub as TaskImportSummary;

class EloquentTaskModelStub extends EloquentCompositeRelationModelStub
{
    protected $table = 'tasks';

    public function importSummary()
    {
        return $this->compositeHasOne(TaskImportSummary::class, ['task_vendor_id', 'task_vendor_name'], ['vendor_id', 'vendor_name']);
    }

    public function importData()
    {
        return $this->compositeHasMany(TaskImportData::class, ['task_vendor_id', 'task_vendor_name'], ['vendor_id', 'vendor_name']);
    }
}