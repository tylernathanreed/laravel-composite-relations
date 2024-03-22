<?php

namespace Reedware\LaravelCompositeRelations\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Reedware\LaravelCompositeRelations\HasCompositeRelations;
use Reedware\LaravelRelationJoins\LaravelRelationJoinServiceProvider;

class DatabaseEloquentCompositeRelationJoinTest extends TestCase
{
    use Concerns\RunsIntegrationQueries;

    protected function setUp(): void
    {
        parent::setUp();

        $app = m::mock('Illuminate\\Contracts\\Container\\Container');
        (new LaravelRelationJoinServiceProvider($app))->boot();

        $this->setUpDatabase();
    }

    public function tearDown(): void
    {
        m::close();

        $this->tearDownDatabase();
    }

    public function testCompositeSimpleHasOneRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('phone');

        $this->assertEquals('select * from "users" inner join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeSimpleHasOneInverseRelationJoin()
    {
        $builder = (new EloquentPhoneModelStub)->newQuery()->joinRelation('user');

        $this->assertEquals('select * from "phones" inner join "users" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeSimpleHasManyRelationJoin()
    {
        $builder = (new EloquentPostModelStub)->newQuery()->joinRelation('comments');

        $this->assertEquals('select * from "posts" inner join "comments" on ("comments"."post_service_name" = "posts"."service_name" and "comments"."post_service_id" = "posts"."service_id")', $builder->toSql());
    }

    public function testCompositeSimpleHasManyInverseRelationJoin()
    {
        $builder = (new EloquentCommentModelStub)->newQuery()->joinRelation('post');

        $this->assertEquals('select * from "comments" inner join "posts" on ("comments"."post_service_name" = "posts"."service_name" and "comments"."post_service_id" = "posts"."service_id")', $builder->toSql());
    }

    public function testCompositeHasOneUsingAliasRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('phone as telephones');

        $this->assertEquals('select * from "users" inner join "phones" as "telephones" on ("telephones"."user_vendor_name" = "users"."vendor_name" and "telephones"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeHasOneInverseUsingAliasRelationJoin()
    {
        $builder = (new EloquentPhoneModelStub)->newQuery()->joinRelation('user as contacts');

        $this->assertEquals('select * from "phones" inner join "users" as "contacts" on ("phones"."user_vendor_name" = "contacts"."vendor_name" and "phones"."user_vendor_id" = "contacts"."vendor_id")', $builder->toSql());
    }

    public function testCompositeHasManyUsingAliasRelationJoin()
    {
        $builder = (new EloquentPostModelStub)->newQuery()->joinRelation('comments as feedback');

        $this->assertEquals('select * from "posts" inner join "comments" as "feedback" on ("feedback"."post_service_name" = "posts"."service_name" and "feedback"."post_service_id" = "posts"."service_id")', $builder->toSql());
    }

    public function testCompositeHasManyInverseUsingAliasRelationJoin()
    {
        $builder = (new EloquentCommentModelStub)->newQuery()->joinRelation('post as article');

        $this->assertEquals('select * from "comments" inner join "posts" as "article" on ("comments"."post_service_name" = "article"."service_name" and "comments"."post_service_id" = "article"."service_id")', $builder->toSql());
    }

    public function testCompositeParentSoftDeletesHasOneRelationJoin()
    {
        $builder = (new EloquentSoftDeletingUserModelStub)->newQuery()->joinRelation('phone');

        $this->assertEquals('select * from "users" inner join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id") where "users"."deleted_at" is null', $builder->toSql());
    }

    public function testCompositeParentSoftDeletesHasOneWithTrashedRelationJoin()
    {
        $builder = (new EloquentSoftDeletingUserModelStub)->newQuery()->joinRelation('phone')->withTrashed();

        $this->assertEquals('select * from "users" inner join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeChildSoftDeletesHasOneRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('softDeletingPhone');

        $this->assertEquals('select * from "users" inner join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id") and "phones"."deleted_at" is null', $builder->toSql());
    }

    public function testCompositeChildSoftDeletesHasOneWithTrashedRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('softDeletingPhone', function ($join) {
            $join->withTrashed();
        });

        $this->assertEquals('select * from "users" inner join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeParentAndChildSoftDeletesHasOneRelationJoin()
    {
        $builder = (new EloquentSoftDeletingUserModelStub)->newQuery()->joinRelation('softDeletingPhone');

        $this->assertEquals('select * from "users" inner join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id") and "phones"."deleted_at" is null where "users"."deleted_at" is null', $builder->toSql());
    }

    public function testCompositeParentAndChildSoftDeletesHasOneWithTrashedParentRelationJoin()
    {
        $builder = (new EloquentSoftDeletingUserModelStub)->newQuery()->joinRelation('softDeletingPhone')->withTrashed();

        $this->assertEquals('select * from "users" inner join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id") and "phones"."deleted_at" is null', $builder->toSql());
    }

    public function testCompositeParentAndChildSoftDeletesHasOneWithTrashedChildRelationJoin()
    {
        $builder = (new EloquentSoftDeletingUserModelStub)->newQuery()->joinRelation('softDeletingPhone', function ($join) {
            $join->withTrashed();
        });

        $this->assertEquals('select * from "users" inner join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id") where "users"."deleted_at" is null', $builder->toSql());
    }

    public function testCompositeParentAndChildSoftDeletesHasOneWithTrashedRelationJoin()
    {
        $builder = (new EloquentSoftDeletingUserModelStub)->newQuery()->withTrashed()->joinRelation('softDeletingPhone', function ($join) {
            $join->withTrashed();
        });

        $this->assertEquals('select * from "users" inner join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeParentSoftSimpleHasOneInverseRelationJoin()
    {
        $builder = (new EloquentPhoneModelStub)->newQuery()->joinRelation('softDeletingUser');

        $this->assertEquals('select * from "phones" inner join "users" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id") and "users"."deleted_at" is null', $builder->toSql());
    }

    public function testCompositeChildSoftSimpleHasOneInverseRelationJoin()
    {
        $builder = (new EloquentSoftDeletingPhoneModelStub)->newQuery()->joinRelation('user');

        $this->assertEquals('select * from "phones" inner join "users" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id") where "phones"."deleted_at" is null', $builder->toSql());
    }

    public function testCompositeBelongsToSelfRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('manager');

        $this->assertEquals('select * from "users" inner join "users" as "self_alias_hash" on ("users"."manager_vendor_name" = "self_alias_hash"."vendor_name" and "users"."manager_vendor_id" = "self_alias_hash"."vendor_id")', preg_replace('/\b(laravel_reserved_\d)(\b|$)/i', 'self_alias_hash', $builder->toSql()));
    }

    public function testCompositeBelongsToSelfUsingAliasRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('manager as managers');

        $this->assertEquals('select * from "users" inner join "users" as "managers" on ("users"."manager_vendor_name" = "managers"."vendor_name" and "users"."manager_vendor_id" = "managers"."vendor_id")', $builder->toSql());
    }

    public function testCompositeHasManySelfRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('employees');

        $this->assertEquals('select * from "users" inner join "users" as "self_alias_hash" on ("self_alias_hash"."user_vendor_name" = "users"."vendor_name" and "self_alias_hash"."user_vendor_id" = "users"."vendor_id")', preg_replace('/\b(laravel_reserved_\d)(\b|$)/i', 'self_alias_hash', $builder->toSql()));
    }

    public function testCompositeHasManySelfUsingAliasRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('employees as employees');

        $this->assertEquals('select * from "users" inner join "users" as "employees" on ("employees"."user_vendor_name" = "users"."vendor_name" and "employees"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeThroughJoinForHasManyRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('posts', function ($join) {
            $join->where('posts.is_active', '=', 1);
        })->joinThroughRelation('posts.comments', function ($join) {
            $join->whereColumn('comments.created_by_id', '=', 'users.id');
        });

        $this->assertEquals('select * from "users" inner join "posts" on ("posts"."user_vendor_name" = "users"."vendor_name" and "posts"."user_vendor_id" = "users"."vendor_id") and "posts"."is_active" = ? inner join "comments" on ("comments"."post_service_name" = "posts"."service_name" and "comments"."post_service_id" = "posts"."service_id") and "comments"."created_by_id" = "users"."id"', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testCompositeLeftHasOneRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->leftJoinRelation('phone');

        $this->assertEquals('select * from "users" left join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeLeftHasOneInverseRelationJoin()
    {
        $builder = (new EloquentPhoneModelStub)->newQuery()->leftJoinRelation('user');

        $this->assertEquals('select * from "phones" left join "users" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeLeftHasManyRelationJoin()
    {
        $builder = (new EloquentPostModelStub)->newQuery()->leftJoinRelation('comments');

        $this->assertEquals('select * from "posts" left join "comments" on ("comments"."post_service_name" = "posts"."service_name" and "comments"."post_service_id" = "posts"."service_id")', $builder->toSql());
    }

    public function testCompositeLeftHasManyInverseRelationJoin()
    {
        $builder = (new EloquentCommentModelStub)->newQuery()->leftJoinRelation('post');

        $this->assertEquals('select * from "comments" left join "posts" on ("comments"."post_service_name" = "posts"."service_name" and "comments"."post_service_id" = "posts"."service_id")', $builder->toSql());
    }

    public function testCompositeRightHasOneRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->rightJoinRelation('phone');

        $this->assertEquals('select * from "users" right join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeCrossHasOneRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->crossJoinRelation('phone');

        $this->assertEquals('select * from "users" cross join "phones" on ("phones"."user_vendor_name" = "users"."vendor_name" and "phones"."user_vendor_id" = "users"."vendor_id")', $builder->toSql());
    }

    public function testCompositeLeftThroughJoinForHasManyRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('posts', function ($join) {
            $join->where('posts.is_active', '=', 1);
        })->leftJoinThroughRelation('posts.comments', function ($join) {
            $join->whereColumn('comments.created_by_id', '=', 'users.id');
        });

        $this->assertEquals('select * from "users" inner join "posts" on ("posts"."user_vendor_name" = "users"."vendor_name" and "posts"."user_vendor_id" = "users"."vendor_id") and "posts"."is_active" = ? left join "comments" on ("comments"."post_service_name" = "posts"."service_name" and "comments"."post_service_id" = "posts"."service_id") and "comments"."created_by_id" = "users"."id"', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testCompositeRightThroughJoinForHasManyRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('posts', function ($join) {
            $join->where('posts.is_active', '=', 1);
        })->rightJoinThroughRelation('posts.comments', function ($join) {
            $join->whereColumn('comments.created_by_id', '=', 'users.id');
        });

        $this->assertEquals('select * from "users" inner join "posts" on ("posts"."user_vendor_name" = "users"."vendor_name" and "posts"."user_vendor_id" = "users"."vendor_id") and "posts"."is_active" = ? right join "comments" on ("comments"."post_service_name" = "posts"."service_name" and "comments"."post_service_id" = "posts"."service_id") and "comments"."created_by_id" = "users"."id"', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testCompositeCrossThroughJoinForHasManyRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('posts', function ($join) {
            $join->where('posts.is_active', '=', 1);
        })->crossJoinThroughRelation('posts.comments', function ($join) {
            $join->whereColumn('comments.created_by_id', '=', 'users.id');
        });

        $this->assertEquals('select * from "users" inner join "posts" on ("posts"."user_vendor_name" = "users"."vendor_name" and "posts"."user_vendor_id" = "users"."vendor_id") and "posts"."is_active" = ? cross join "comments" on ("comments"."post_service_name" = "posts"."service_name" and "comments"."post_service_id" = "posts"."service_id") and "comments"."created_by_id" = "users"."id"', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testCompositeMultipleAliasesForBelongsToRelationJoin()
    {
        $builder = (new EloquentPostModelStub)->newQuery()->joinRelation('user as authors.country as nations');

        $this->assertEquals('select * from "posts" inner join "users" as "authors" on ("posts"."user_vendor_name" = "authors"."vendor_name" and "posts"."user_vendor_id" = "authors"."vendor_id") inner join "countries" as "nations" on ("authors"."country_planet_name" = "nations"."planet_name" and "authors"."country_planet_id" = "nations"."planet_id")', $builder->toSql());
    }

    public function testCompositeMultipleAliasesForHasManyRelationJoin()
    {
        $builder = (new EloquentUserModelStub)->newQuery()->joinRelation('posts as articles.comments as reviews');

        $this->assertEquals('select * from "users" inner join "posts" as "articles" on ("articles"."user_vendor_name" = "users"."vendor_name" and "articles"."user_vendor_id" = "users"."vendor_id") inner join "comments" as "reviews" on ("reviews"."article_service_name" = "articles"."service_name" and "reviews"."article_service_id" = "articles"."service_id")', $builder->toSql());
    }

    public function testCompositeHasManyUsingLocalScopeRelationJoin()
    {
        $builder = (new EloquentCountryModelStub)->newQuery()->joinRelation('users', function ($join) {
            $join->active();
        });

        $this->assertEquals('select * from "countries" inner join "users" on ("users"."country_planet_name" = "countries"."planet_name" and "users"."country_planet_id" = "countries"."planet_id") and "active" = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }
}

class EloquentRelationJoinModelStub extends Model
{
    use HasCompositeRelations;

    public function getForeignKeys()
    {
        return array_map(function ($keyName) {
            return Str::singular($this->table).'_'.$keyName;
        }, $this->getKeyNames());
    }
}

class EloquentRelationJoinPivotStub extends Pivot
{
    use HasCompositeRelations;
}

class EloquentUserModelStub extends EloquentRelationJoinModelStub
{
    protected $table = 'users';

    protected array $primaryKeys = ['vendor_name', 'vendor_id'];

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function phone()
    {
        return $this->compositeHasOne(EloquentPhoneModelStub::class);
    }

    public function softDeletingPhone()
    {
        return $this->compositeHasOne(EloquentSoftDeletingPhoneModelStub::class);
    }

    public function posts()
    {
        return $this->compositeHasMany(EloquentPostModelStub::class);
    }

    public function country()
    {
        return $this->compositeBelongsTo(EloquentCountryModelStub::class);
    }

    public function manager()
    {
        return $this->compositeBelongsTo(static::class);
    }

    public function employees()
    {
        return $this->compositeHasMany(static::class);
    }
}

class EloquentPhoneModelStub extends EloquentRelationJoinModelStub
{
    protected $table = 'phones';

    public function user()
    {
        return $this->compositeBelongsTo(EloquentUserModelStub::class);
    }

    public function softDeletingUser()
    {
        return $this->compositeBelongsTo(EloquentSoftDeletingUserModelStub::class, null, null, 'user');
    }
}

class EloquentPostModelStub extends EloquentRelationJoinModelStub
{
    protected $table = 'posts';

    protected array $primaryKeys = ['service_name', 'service_id'];

    public function comments()
    {
        return $this->compositeHasMany(EloquentCommentModelStub::class);
    }

    public function user()
    {
        return $this->compositeBelongsTo(EloquentUserModelStub::class);
    }
}

class EloquentCommentModelStub extends EloquentRelationJoinModelStub
{
    protected $table = 'comments';

    public function post()
    {
        return $this->compositeBelongsTo(EloquentPostModelStub::class);
    }
}

class EloquentCountryModelStub extends EloquentRelationJoinModelStub
{
    protected $table = 'countries';

    protected array $primaryKeys = ['planet_name', 'planet_id'];

    public function users()
    {
        return $this->compositeHasMany(EloquentUserModelStub::class);
    }
}

class EloquentSoftDeletingUserModelStub extends EloquentUserModelStub
{
    use SoftDeletes;
}

class EloquentSoftDeletingPhoneModelStub extends EloquentPhoneModelStub
{
    use SoftDeletes;
}

class EloquentSoftDeletingPostModelStub extends EloquentPostModelStub
{
    use SoftDeletes;
}

class EloquentSoftDeletingCommentModelStub extends EloquentCommentModelStub
{
    use SoftDeletes;
}

class EloquentSoftDeletingCountryModelStub extends EloquentCountryModelStub
{
    use SoftDeletes;
}
