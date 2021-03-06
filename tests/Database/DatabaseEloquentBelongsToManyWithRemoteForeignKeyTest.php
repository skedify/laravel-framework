<?php

use Mockery as m;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DatabaseEloquentBelongsToManyWithRemoteForeignKeyTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}
    
    
	public function testModelsAreProperlyHydrated()
	{
		$model1 = new EloquentBelongsToManyWithRemoteForeignKeyModelStub;
		$model1->fill(array('name' => 'taylor', 'pivot_user_uid' => 'uid_1', 'pivot_role_id' => 2));
		$model2 = new EloquentBelongsToManyWithRemoteForeignKeyModelStub;
		$model2->fill(array('name' => 'dayle', 'pivot_user_uid' => 'uid_2', 'pivot_role_id' => 4));
		$models = array($model1, $model2);

		$baseBuilder = m::mock('Illuminate\Database\Query\Builder');

		$relation = $this->getRelation();
		
		$relation->getParent()->shouldReceive('getConnectionName')->andReturn('foo.connection');
		$relation->getQuery()->shouldReceive('addSelect')->once()->with(array('roles.*', 'user_role.user_uid as pivot_user_uid', 'user_role.role_id as pivot_role_id'))->andReturn($relation->getQuery());
		$relation->getQuery()->shouldReceive('getModels')->once()->andReturn($models);
		$relation->getQuery()->shouldReceive('eagerLoadRelations')->once()->with($models)->andReturn($models);
		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function($array) { return new Collection($array); });
		$relation->getQuery()->shouldReceive('getQuery')->once()->andReturn($baseBuilder);
		$results = $relation->get();

		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $results);

		// Make sure the foreign keys were set on the pivot models...
        $this->assertEquals('user_uid', $results[0]->pivot->getForeignKey());
        $this->assertEquals('role_id', $results[0]->pivot->getOtherKey());
        
        $this->assertEquals('taylor', $results[0]->name);
		$this->assertEquals('uid_1', $results[0]->pivot->user_uid);
		$this->assertEquals(2, $results[0]->pivot->role_id);
		$this->assertEquals('foo.connection', $results[0]->pivot->getConnectionName());
		$this->assertEquals('dayle', $results[1]->name);
		$this->assertEquals('uid_2', $results[1]->pivot->user_uid);
		$this->assertEquals(4, $results[1]->pivot->role_id);
		$this->assertEquals('foo.connection', $results[1]->pivot->getConnectionName());
		$this->assertEquals('user_role', $results[0]->pivot->getTable());
		$this->assertTrue($results[0]->pivot->exists);
	}
    
    
	public function testTimestampsCanBeRetrievedProperly()
	{
		$model1 = new EloquentBelongsToManyModelStub;
		$model1->fill(array('name' => 'taylor', 'pivot_user_uid' => 'uid_1', 'pivot_role_id' => 2));
		$model2 = new EloquentBelongsToManyModelStub;
		$model2->fill(array('name' => 'dayle', 'pivot_user_uid' => 'uid_1', 'pivot_role_id' => 4));
		$models = array($model1, $model2);

		$baseBuilder = m::mock('Illuminate\Database\Query\Builder');

		$relation = $this->getRelation()->withTimestamps();
		$relation->getParent()->shouldReceive('getConnectionName')->andReturn('foo.connection');
		$relation->getQuery()->shouldReceive('addSelect')->once()->with(array(
			'roles.*',
			'user_role.user_uid as pivot_user_uid',
			'user_role.role_id as pivot_role_id',
			'user_role.created_at as pivot_created_at',
			'user_role.updated_at as pivot_updated_at',
		))->andReturn($relation->getQuery());
		$relation->getQuery()->shouldReceive('getModels')->once()->andReturn($models);
		$relation->getQuery()->shouldReceive('eagerLoadRelations')->once()->with($models)->andReturn($models);
		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function($array) { return new Collection($array); });
		$relation->getQuery()->shouldReceive('getQuery')->once()->andReturn($baseBuilder);
		$results = $relation->get();
	}
    
    
	public function testModelsAreProperlyMatchedToParents()
	{
		$relation = $this->getRelation();

		$result1 = new EloquentBelongsToManyModelPivotStub;
		$result1->pivot->user_uid = 'uid_1';
		$result2 = new EloquentBelongsToManyModelPivotStub;
		$result2->pivot->user_uid = 'uid_2';
		$result3 = new EloquentBelongsToManyModelPivotStub;
		$result3->pivot->user_uid = 'uid_2';

		$model1 = new EloquentBelongsToManyModelStub;
		$model1->id = 'uid_1';
		$model2 = new EloquentBelongsToManyModelStub;
		$model2->id = 'uid_2';
		$model3 = new EloquentBelongsToManyModelStub;
		$model3->id = 'uid_3';

		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function($array) { return new Collection($array); });
		$models = $relation->match(array($model1, $model2, $model3), new Collection(array($result1, $result2, $result3)), 'foo');

		$this->assertEquals('uid_1', $models[0]->foo[0]->pivot->user_uid);
		$this->assertEquals(1, count($models[0]->foo));
		$this->assertEquals('uid_2', $models[1]->foo[0]->pivot->user_uid);
		$this->assertEquals('uid_2', $models[1]->foo[1]->pivot->user_uid);
		$this->assertEquals(2, count($models[1]->foo));
		$this->assertEquals(0, count($models[2]->foo));
	}
    
    
	public function testRelationIsProperlyInitialized()
	{
		$relation = $this->getRelation();
		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function($array = array()) { return new Collection($array); });
		$model = m::mock('Illuminate\Database\Eloquent\Model');
		$model->shouldReceive('setRelation')->once()->with('foo', m::type('Illuminate\Database\Eloquent\Collection'));
		$models = $relation->initRelation(array($model), 'foo');

		$this->assertEquals(array($model), $models);
	}
    
    
	public function testEagerConstraintsAreProperlyAdded()
	{
		$relation = $this->getRelation();
		$relation->getQuery()->shouldReceive('whereIn')->once()->with('user_role.user_uid', array('uid_1', 'uid_2'));
		$model1 = new EloquentBelongsToManyModelStub;
		$model1->uid = 'uid_1';
		$model2 = new EloquentBelongsToManyModelStub;
		$model2->uid = 'uid_2';
		$relation->addEagerConstraints(array($model1, $model2));
	}
    
    
	public function testAttachInsertsPivotTableRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('touchIfTouching'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(array(array('user_uid' => 'uid_1', 'role_id' => 2, 'foo' => 'bar')))->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach(2, array('foo' => 'bar'));
	}
    
    
	public function testAttachMultipleInsertsPivotTableRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('touchIfTouching'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(
			array(
				array('user_uid' => 'uid_1', 'role_id' => 2, 'foo' => 'bar'),
				array('user_uid' => 'uid_1', 'role_id' => 4, 'baz' => 'boom', 'foo' => 'bar'),
			)
		)->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach(array(2, 4 => array('baz' => 'boom')), array('foo' => 'bar'));
	}
    
    
	public function testAttachInsertsPivotTableRecordWithTimestampsWhenNecessary()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('touchIfTouching'), $this->getRelationArguments());
		$relation->withTimestamps();
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(array(array('user_uid' => 'uid_1', 'role_id' => 2, 'foo' => 'bar', 'created_at' => 'time', 'updated_at' => 'time')))->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach(2, array('foo' => 'bar'));
	}
    
    
	public function testAttachInsertsPivotTableRecordWithACreatedAtTimestamp()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('touchIfTouching'), $this->getRelationArguments());
		$relation->withPivot('created_at');
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(array(array('user_uid' => 'uid_1', 'role_id' => 2, 'foo' => 'bar', 'created_at' => 'time')))->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach(2, array('foo' => 'bar'));
	}
    
    
	public function testAttachInsertsPivotTableRecordWithAnUpdatedAtTimestamp()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('touchIfTouching'), $this->getRelationArguments());
		$relation->withPivot('updated_at');
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(array(array('user_uid' => 'uid_1', 'role_id' => 2, 'foo' => 'bar', 'updated_at' => 'time')))->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach(2, array('foo' => 'bar'));
	}
    
    
	public function testDetachRemovesPivotTableRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('touchIfTouching'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_uid', 'uid_1')->andReturn($query);
		$query->shouldReceive('whereIn')->once()->with('role_id', array(1, 2, 3));
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertTrue($relation->detach(array(1,2,3)));
	}
    
    
	public function testDetachWithSingleIDRemovesPivotTableRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('touchIfTouching'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_uid', 'uid_1')->andReturn($query);
		$query->shouldReceive('whereIn')->once()->with('role_id', [1]);
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertTrue($relation->detach(array(1)));
	}
    
    
	public function testDetachMethodClearsAllPivotRecordsWhenNoIDsAreGiven()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('touchIfTouching'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_uid', 'uid_1')->andReturn($query);
		$query->shouldReceive('whereIn')->never();
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertTrue($relation->detach());
	}
    
    
	public function testCreateMethodCreatesNewModelAndInsertsAttachmentRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('attach'), $this->getRelationArguments());
		$relation->getRelated()->shouldReceive('newInstance')->once()->andReturn($model = m::mock('StdClass'))->with(array('attributes'));
		$model->shouldReceive('save')->once();
		$model->shouldReceive('getKey')->andReturn('foo');
		$relation->expects($this->once())->method('attach')->with('foo', array('joining'));

		$this->assertEquals($model, $relation->create(array('attributes'), array('joining')));
	}


	/**
	 * @dataProvider syncMethodListProvider
	 */
	public function testSyncMethodSyncsIntermediateTableWithGivenArray($list)
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('attach', 'detach'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_uid', 'uid_1')->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn(array(1, 2, 3));
		$relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo(array()), $this->equalTo(false));
		$relation->expects($this->once())->method('detach')->with($this->equalTo(array(1)));
		$relation->getRelated()->shouldReceive('touches')->andReturn(false);
		$relation->getParent()->shouldReceive('touches')->andReturn(false);

		$this->assertEquals(array('attached' => array(4), 'detached' => array(1), 'updated' => array()), $relation->sync($list));
	}


	public function syncMethodListProvider()
	{
		return array(
			array(array(2, 3, 4)),
			array(array('2', '3', '4')),
		);
	}
    
    
	public function testSyncMethodSyncsIntermediateTableWithGivenArrayAndAttributes()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('attach', 'detach', 'touchIfTouching', 'updateExistingPivot'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_uid', 'uid_1')->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn(array(1, 2, 3));
		$relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo(array('foo' => 'bar')), $this->equalTo(false));
		$relation->expects($this->once())->method('updateExistingPivot')->with($this->equalTo(3), $this->equalTo(array('baz' => 'qux')), $this->equalTo(false))->will($this->returnValue(true));
		$relation->expects($this->once())->method('detach')->with($this->equalTo(array(1)));
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertEquals(array('attached' => array(4), 'detached' => array(1), 'updated' => array(3)), $relation->sync(array(2, 3 => array('baz' => 'qux'), 4 => array('foo' => 'bar'))));
	}
    
    
	public function testSyncMethodDoesntReturnValuesThatWereNotUpdated()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('attach', 'detach', 'touchIfTouching', 'updateExistingPivot'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_uid', 'uid_1')->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn(array(1, 2, 3));
		$relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo(array('foo' => 'bar')), $this->equalTo(false));
		$relation->expects($this->once())->method('updateExistingPivot')->with($this->equalTo(3), $this->equalTo(array('baz' => 'qux')), $this->equalTo(false))->will($this->returnValue(false));
		$relation->expects($this->once())->method('detach')->with($this->equalTo(array(1)));
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertEquals(array('attached' => array(4), 'detached' => array(1), 'updated' => array()), $relation->sync(array(2, 3 => array('baz' => 'qux'), 4 => array('foo' => 'bar'))));
	}
    
    
	public function testTouchMethodSyncsTimestamps()
	{
		$relation = $this->getRelation();
		$relation->getRelated()->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');
		$relation->getRelated()->shouldReceive('freshTimestamp')->andReturn(100);
		$relation->getRelated()->shouldReceive('getQualifiedKeyName')->andReturn('table.id');
		$relation->getQuery()->shouldReceive('select')->once()->with('table.id')->andReturn($relation->getQuery());
		$relation->getQuery()->shouldReceive('lists')->once()->with('id')->andReturn(array(1, 2, 3));
		$relation->getRelated()->shouldReceive('newQuery')->once()->andReturn($query = m::mock('StdClass'));
		$query->shouldReceive('whereIn')->once()->with('id', array(1, 2, 3))->andReturn($query);
		$query->shouldReceive('update')->once()->with(array('updated_at' => 100));

		$relation->touch();
	}
    
    
	public function testTouchIfTouching()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('touch', 'touchingParent'), $this->getRelationArguments());
		$relation->expects($this->once())->method('touchingParent')->will($this->returnValue(true));
		$relation->getParent()->shouldReceive('touch')->once();
		$relation->getParent()->shouldReceive('touches')->once()->with('relation_name')->andReturn(true);
		$relation->expects($this->once())->method('touch');

		$relation->touchIfTouching();
	}
    
    
	public function testSyncMethodConvertsCollectionToArrayOfKeys()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', array('attach', 'detach', 'touchIfTouching', 'formatSyncList'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_uid', 'uid_1')->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn(array(1, 2, 3));

		$collection = m::mock('Illuminate\Database\Eloquent\Collection');
		$collection->shouldReceive('modelKeys')->once()->andReturn(array(1, 2, 3));
		$relation->expects($this->once())->method('formatSyncList')->with(array(1, 2, 3))->will($this->returnValue(array(1 => array(),2 => array(),3 => array())));
		$relation->sync($collection);
	}
    
    
	public function testWherePivotParamsUsedForNewQueries()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach', 'touchIfTouching', 'formatSyncList'], $this->getRelationArguments());

		// we expect to call $relation->wherePivot()
		$relation->getQuery()->shouldReceive('where')->once()->andReturn($relation);

		// Our sync() call will produce a new query
		$mockQueryBuilder = m::mock('stdClass');
		$query            = m::mock('stdClass');
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder);
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);

		// BelongsToMany::newPivotStatement() sets this
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);

		// BelongsToMany::newPivotQuery() sets this
		$query->shouldReceive('where')->once()->with('user_uid', 'uid_1')->andReturn($query);

		// This is our test! The wherePivot() params also need to be called
		$query->shouldReceive('where')->once()->with('foo', '=', 'bar')->andReturn($query);

		// This is so $relation->sync() works
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('formatSyncList')->with([1, 2, 3])->will($this->returnValue([1 => [],2 => [],3 => []]));


		$relation = $relation->wherePivot('foo', '=', 'bar'); // these params are to be stored
		$relation->sync([1,2,3]); // triggers the whole process above
	}


	public function getRelation()
	{
		list($builder, $parent) = $this->getRelationArguments();

		return new BelongsToMany($builder, $parent, 'user_role', 'user_uid', 'role_id', 'relation_name', 'uid', null);
	}


	public function getRelationArguments()
	{
		$parent = m::mock('Illuminate\Database\Eloquent\Model');
		$parent->shouldReceive('getKey')->andReturn(1);
		$parent->shouldReceive('getAttribute')->andReturn('uid_1');
		$parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
		$parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

		$builder = m::mock('Illuminate\Database\Eloquent\Builder');
		$related = m::mock('Illuminate\Database\Eloquent\Model');
		$builder->shouldReceive('getModel')->andReturn($related);

		$related->shouldReceive('getTable')->andReturn('roles');
		$related->shouldReceive('getKeyName')->andReturn('id');
		$related->shouldReceive('newPivot')->andReturnUsing(function()
		{
			$reflector = new ReflectionClass('Illuminate\Database\Eloquent\Relations\Pivot');
			return $reflector->newInstanceArgs(func_get_args());
		});
        
        $builder->shouldReceive('join')->once()->with('user_role', 'roles.id', '=', 'user_role.role_id');
        $builder->shouldReceive('where')->once()->with('user_role.user_uid', '=', 'uid_1');
        
        return array($builder, $parent, 'user_role', 'user_uid', 'role_id', 'relation_name', 'uid', null);
	}

}

class EloquentBelongsToManyWithRemoteForeignKeyModelStub extends Illuminate\Database\Eloquent\Model {
    
    protected $guarded = [];
}

class EloquentBelongsToManyWithRemoteForeignKeyModelPivotStub extends Illuminate\Database\Eloquent\Model {
    
    public $pivot;
    
    public function __construct()
    {
        $this->pivot = new EloquentBelongsToManyWithRemoteForeignKeyPivotStub;
    }
}

class EloquentBelongsToManyWithRemoteForeignKeyPivotStub {
    
    public $user_id;
}
