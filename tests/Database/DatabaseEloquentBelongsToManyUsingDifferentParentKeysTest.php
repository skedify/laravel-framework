<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Mockery as m;

/**
 * @group belongs-to-many
 */
class DatabaseEloquentBelongsToManyUsingDifferentParentKeysTest extends PHPUnit_Framework_TestCase {
	
	public function tearDown()
	{
		m::close();
	}
	
	/**
	 * @group belongs-to-many-hydrate
	 */
	public function testModelsAreProperlyHydrated()
	{
		$model1 = new EloquentBelongsToManyUsingDifferentParentKeysModelStub;
		$model1->fill(['name' => 'taylor', 'pivot_user_id' => 1, 'pivot_role_name' => 'taylor_role']);
		$model2 = new EloquentBelongsToManyUsingDifferentParentKeysModelStub;
		$model2->fill(['name' => 'dayle', 'pivot_user_id' => 3, 'pivot_role_name' => 'dayle_role']);
		$models = [$model1, $model2];
		
		$baseBuilder = m::mock('Illuminate\Database\Query\Builder');
		
		$relation = $this->getRelation();
		$relation->getParent()->shouldReceive('getConnectionName')->andReturn('foo.connection');
		$relation->getQuery()->shouldReceive('addSelect')->once()->with(['roles.*', 'user_role.user_id as pivot_user_id', 'user_role.role_name as pivot_role_name'])->andReturn($relation->getQuery());
		$relation->getQuery()->shouldReceive('getModels')->once()->andReturn($models);
		$relation->getQuery()->shouldReceive('eagerLoadRelations')->once()->with($models)->andReturn($models);
		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function ($array) {
			return new Collection($array);
		});
		$relation->getQuery()->shouldReceive('getQuery')->once()->andReturn($baseBuilder);
		$results = $relation->get();
		
		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $results);
		
		// Make sure the foreign keys were set on the pivot models...
		$this->assertEquals('user_id', $results[0]->pivot->getForeignKey());
		$this->assertEquals('role_name', $results[0]->pivot->getOtherKey());
		
		$this->assertEquals('taylor', $results[0]->name);
		$this->assertEquals(1, $results[0]->pivot->user_id);
		$this->assertEquals('taylor_role', $results[0]->pivot->role_name);
		$this->assertEquals('foo.connection', $results[0]->pivot->getConnectionName());
		
		$this->assertEquals('dayle', $results[1]->name);
		$this->assertEquals(3, $results[1]->pivot->user_id);
		$this->assertEquals('dayle_role', $results[1]->pivot->role_name);
		$this->assertEquals('foo.connection', $results[1]->pivot->getConnectionName());
		$this->assertEquals('user_role', $results[0]->pivot->getTable());
		$this->assertTrue($results[0]->pivot->exists);
	}
	
	/**
	 * @group belongs-to-many-parents
	 */
	public function testModelsAreProperlyMatchedToParents()
	{
		$relation = $this->getRelation();
		
		$result1 = new EloquentBelongsToManyModelPivotStub;
		$result1->pivot->user_id = 1;
		$result2 = new EloquentBelongsToManyModelPivotStub;
		$result2->pivot->user_id = 2;
		$result3 = new EloquentBelongsToManyModelPivotStub;
		$result3->pivot->user_id = 2;
		
		$model1 = new EloquentBelongsToManyUsingDifferentParentKeysModelStub;
		$model1->id = 1;
		$model2 = new EloquentBelongsToManyUsingDifferentParentKeysModelStub;
		$model2->id = 2;
		$model3 = new EloquentBelongsToManyUsingDifferentParentKeysModelStub;
		$model3->id = 3;
		
		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function ($array) {
			return new Collection($array);
		});
		$models = $relation->match([$model1, $model2, $model3], new Collection([$result1, $result2, $result3]), 'foo');
		
		$this->assertEquals(1, $models[0]->foo[0]->pivot->user_id);
		$this->assertEquals(1, count($models[0]->foo));
		$this->assertEquals(2, $models[1]->foo[0]->pivot->user_id);
		$this->assertEquals(2, $models[1]->foo[1]->pivot->user_id);
		$this->assertEquals(2, count($models[1]->foo));
		$this->assertEquals(0, count($models[2]->foo));
	}
	
	
	/**
	 * @group belongs-to-many-initialized
	 */
	public function testRelationIsProperlyInitialized()
	{
		$relation = $this->getRelation();
		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function ($array = []) {
			return new Collection($array);
		});
		$model = m::mock('Illuminate\Database\Eloquent\Model');
		$model->shouldReceive('setRelation')->once()->with('foo', m::type('Illuminate\Database\Eloquent\Collection'));
		$models = $relation->initRelation([$model], 'foo');
		
		$this->assertEquals([$model], $models);
	}
	
	
	/**
	 * @group belongs-to-many-eager
	 */
	public function testEagerConstraintsAreProperlyAdded()
	{
		$relation = $this->getRelation();
		$relation->getQuery()->shouldReceive('whereIn')->once()->with('user_role.user_id', [1, 2]);
		$model1 = new EloquentBelongsToManyUsingDifferentParentKeysModelStub;
		$model1->id = 1;
		$model2 = new EloquentBelongsToManyUsingDifferentParentKeysModelStub;
		$model2->id = 2;
		$relation->addEagerConstraints([$model1, $model2]);
	}
	
	/**
	 * @group belongs-to-many-attach-inserts
	 */
	public function testAttachInsertsPivotTableRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with([['user_id' => 1, 'role_name' => 'taylor_role', 'foo' => 'bar']])->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');
		
		$relation->attach('taylor_role', ['foo' => 'bar']);
	}
	
	
	/**
	 * @group belongs-to-many-attach-inserts-multiple
	 */
	public function testAttachMultipleInsertsPivotTableRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(
			[
				['user_id' => 1, 'role_name' => 'taylor_role', 'foo' => 'bar'],
				['user_id' => 1, 'role_name' => 'shared_role', 'baz' => 'boom', 'foo' => 'bar'],
			]
		)->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');
		
		$relation->attach(['taylor_role', 'shared_role' => ['baz' => 'boom']], ['foo' => 'bar']);
	}
	
	
	/**
	 * @group belongs-to-many-attach-timestamps
	 */
	public function testAttachInsertsPivotTableRecordWithTimestampsWhenNecessary()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
		$relation->withTimestamps();
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with([['user_id' => 1, 'role_name' => 'taylor_role', 'foo' => 'bar', 'created_at' => 'time', 'updated_at' => 'time']])->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
		$relation->expects($this->once())->method('touchIfTouching');
		
		$relation->attach('taylor_role', ['foo' => 'bar']);
	}
	
	
	public function testAttachInsertsPivotTableRecordWithACreatedAtTimestamp()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
		$relation->withPivot('created_at');
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with([['user_id' => 1, 'role_id' => 2, 'foo' => 'bar', 'created_at' => 'time']])->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
		$relation->expects($this->once())->method('touchIfTouching');
		
		$relation->attach(2, ['foo' => 'bar']);
	}
	
	
	public function testAttachInsertsPivotTableRecordWithAnUpdatedAtTimestamp()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
		$relation->withPivot('updated_at');
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with([['user_id' => 1, 'role_id' => 2, 'foo' => 'bar', 'updated_at' => 'time']])->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
		$relation->expects($this->once())->method('touchIfTouching');
		
		$relation->attach(2, ['foo' => 'bar']);
	}
	
	
	public function testDetachRemovesPivotTableRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$query->shouldReceive('whereIn')->once()->with('role_id', [1, 2, 3]);
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');
		
		$this->assertTrue($relation->detach([1, 2, 3]));
	}
	
	
	public function testDetachWithSingleIDRemovesPivotTableRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$query->shouldReceive('whereIn')->once()->with('role_id', [1]);
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');
		
		$this->assertTrue($relation->detach([1]));
	}
	
	
	public function testDetachMethodClearsAllPivotRecordsWhenNoIDsAreGiven()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$query->shouldReceive('whereIn')->never();
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');
		
		$this->assertTrue($relation->detach());
	}
	
	
	public function testCreateMethodCreatesNewModelAndInsertsAttachmentRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach'], $this->getRelationArguments());
		$relation->getRelated()->shouldReceive('newInstance')->once()->andReturn($model = m::mock('StdClass'))->with(['attributes']);
		$model->shouldReceive('save')->once();
		$model->shouldReceive('getKey')->andReturn('foo');
		$relation->expects($this->once())->method('attach')->with('foo', ['joining']);
		
		$this->assertEquals($model, $relation->create(['attributes'], ['joining']));
	}
	
	
	/**
	 * @dataProvider syncMethodListProvider
	 */
	public function testSyncMethodSyncsIntermediateTableWithGivenArray($list)
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo([]), $this->equalTo(false));
		$relation->expects($this->once())->method('detach')->with($this->equalTo([1]));
		$relation->getRelated()->shouldReceive('touches')->andReturn(false);
		$relation->getParent()->shouldReceive('touches')->andReturn(false);
		
		$this->assertEquals(['attached' => [4], 'detached' => [1], 'updated' => []], $relation->sync($list));
	}
	
	
	public function syncMethodListProvider()
	{
		return [
			[[2, 3, 4]],
			[['2', '3', '4']],
		];
	}
	
	
	public function testSyncMethodSyncsIntermediateTableWithGivenArrayAndAttributes()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach', 'touchIfTouching', 'updateExistingPivot'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo(['foo' => 'bar']), $this->equalTo(false));
		$relation->expects($this->once())->method('updateExistingPivot')->with($this->equalTo(3), $this->equalTo(['baz' => 'qux']), $this->equalTo(false))->will($this->returnValue(true));
		$relation->expects($this->once())->method('detach')->with($this->equalTo([1]));
		$relation->expects($this->once())->method('touchIfTouching');
		
		$this->assertEquals(['attached' => [4], 'detached' => [1], 'updated' => [3]], $relation->sync([2, 3 => ['baz' => 'qux'], 4 => ['foo' => 'bar']]));
	}
	
	
	public function testSyncMethodDoesntReturnValuesThatWereNotUpdated()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach', 'touchIfTouching', 'updateExistingPivot'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo(['foo' => 'bar']), $this->equalTo(false));
		$relation->expects($this->once())->method('updateExistingPivot')->with($this->equalTo(3), $this->equalTo(['baz' => 'qux']), $this->equalTo(false))->will($this->returnValue(false));
		$relation->expects($this->once())->method('detach')->with($this->equalTo([1]));
		$relation->expects($this->once())->method('touchIfTouching');
		
		$this->assertEquals(['attached' => [4], 'detached' => [1], 'updated' => []], $relation->sync([2, 3 => ['baz' => 'qux'], 4 => ['foo' => 'bar']]));
	}
	
	
	public function testTouchMethodSyncsTimestamps()
	{
		$relation = $this->getRelation();
		$relation->getRelated()->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');
		$relation->getRelated()->shouldReceive('freshTimestamp')->andReturn(100);
		$relation->getRelated()->shouldReceive('getQualifiedKeyName')->andReturn('table.id');
		$relation->getQuery()->shouldReceive('select')->once()->with('table.id')->andReturn($relation->getQuery());
		$relation->getQuery()->shouldReceive('lists')->once()->with('id')->andReturn([1, 2, 3]);
		$relation->getRelated()->shouldReceive('newQuery')->once()->andReturn($query = m::mock('StdClass'));
		$query->shouldReceive('whereIn')->once()->with('id', [1, 2, 3])->andReturn($query);
		$query->shouldReceive('update')->once()->with(['updated_at' => 100]);
		
		$relation->touch();
	}
	
	
	public function testTouchIfTouching()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touch', 'touchingParent'], $this->getRelationArguments());
		$relation->expects($this->once())->method('touchingParent')->will($this->returnValue(true));
		$relation->getParent()->shouldReceive('touch')->once();
		$relation->getParent()->shouldReceive('touches')->once()->with('relation_name')->andReturn(true);
		$relation->expects($this->once())->method('touch');
		
		$relation->touchIfTouching();
	}
	
	
	public function testSyncMethodConvertsCollectionToArrayOfKeys()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach', 'touchIfTouching', 'formatSyncList'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
		
		$collection = m::mock('Illuminate\Database\Eloquent\Collection');
		$collection->shouldReceive('modelKeys')->once()->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('formatSyncList')->with([1, 2, 3])->will($this->returnValue([1 => [], 2 => [], 3 => []]));
		$relation->sync($collection);
	}
	
	
	public function testWherePivotParamsUsedForNewQueries()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach', 'touchIfTouching', 'formatSyncList'], $this->getRelationArguments());
		
		// we expect to call $relation->wherePivot()
		$relation->getQuery()->shouldReceive('where')->once()->andReturn($relation);
		
		// Our sync() call will produce a new query
		$mockQueryBuilder = m::mock('stdClass');
		$query = m::mock('stdClass');
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder);
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		
		// BelongsToMany::newPivotStatement() sets this
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		
		// BelongsToMany::newPivotQuery() sets this
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		
		// This is our test! The wherePivot() params also need to be called
		$query->shouldReceive('where')->once()->with('foo', '=', 'bar')->andReturn($query);
		
		// This is so $relation->sync() works
		$query->shouldReceive('lists')->once()->with('role_name')->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('formatSyncList')->with([1, 2, 3])->will($this->returnValue([1 => [], 2 => [], 3 => []]));
		
		$relation = $relation->wherePivot('foo', '=', 'bar'); // these params are to be stored
		$relation->sync([1, 2, 3]); // triggers the whole process above
	}
	
	
	public function getRelation()
	{
		list($builder, $parent) = $this->getRelationArguments();
		
		return new BelongsToMany($builder, $parent, 'user_role', 'user_id', 'role_name', 'relation_name', null, 'name');
	}
	
	
	public function getRelationArguments()
	{
		$parent = m::mock('Illuminate\Database\Eloquent\Model');
		$parent->shouldReceive('getKey')->andReturn(1);
		$parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
		$parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');
		$parent->shouldReceive('getAttribute')->with('created_at')->andReturn('2018-01-01 00:00:00');
		
		$builder = m::mock('Illuminate\Database\Eloquent\Builder');
		$related = m::mock('Illuminate\Database\Eloquent\Model');
		$builder->shouldReceive('getModel')->andReturn($related);
		
		$related->shouldReceive('getTable')->andReturn('roles');
		$related->shouldReceive('getKeyName')->andReturn('id');
		$related->shouldReceive('newPivot')->andReturnUsing(function () {
			$reflector = new ReflectionClass('Illuminate\Database\Eloquent\Relations\Pivot');
			
			return $reflector->newInstanceArgs(func_get_args());
		});
		
		$builder->shouldReceive('join')->once()->with('user_role', 'roles.name', '=', 'user_role.role_name');
		$builder->shouldReceive('where')->once()->with('user_role.user_id', '=', 1);
		
		return [$builder, $parent, 'user_role', 'user_id', 'role_name', 'relation_name', null, 'name'];
	}
}

class EloquentBelongsToManyUsingDifferentParentKeysModelStub extends Illuminate\Database\Eloquent\Model {
	
	protected $guarded = [];
	
//	protected $attributues = ['id', 'name', 'created_at', 'updated_at'];
}

class EloquentBelongsToManyUsingDifferentParentKeysModelPivotStub extends Illuminate\Database\Eloquent\Model {
	
	public $pivot;
	
	public function __construct()
	{
		$this->pivot = new EloquentBelongsToManyUsingDifferentParentKeysPivotStub;
	}
}

class EloquentBelongsToManyUsingDifferentParentKeysPivotStub {
	
	public $user_id;
}
