<?php

namespace Reedware\LaravelCompositeRelations\Tests;

use Illuminate\Database\Eloquent\Model;
use Reedware\LaravelCompositeRelations\HasCompositeRelations;

class EloquentCompositeRelationModelStub extends Model
{
    use HasCompositeRelations;

    protected $guarded = [];
}