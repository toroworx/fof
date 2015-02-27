<?php

namespace FOF30\Tests\TreeModel;

use FOF30\Tests\Helpers\DatabaseTest;
use FOF30\Tests\Helpers\ReflectionHelper;
use FOF30\Tests\Helpers\TestContainer;
use FOF30\Tests\Stubs\Model\TreeModelStub;

require_once 'TreeModelDataprovider.php';

/**
 * @covers      FOF30\Model\TreeModel::<protected>
 * @covers      FOF30\Model\TreeModel::<private>
 * @package     FOF30\Tests\TreeModel
 */
class TreeModelTest extends DatabaseTest
{
    /**
     * @group           TreeModel
     * @group           TreeModelCheck
     * @covers          FOF30\Model\TreeModel::check
     * @dataProvider    TreeModelDataprovider::getTestCheck
     */
    public function testCheck($test, $check)
    {
        $config = array(
            'autoChecks'  => false,
            'idFieldName' => $test['id'],
            'tableName'   => $test['table']
        );

        $table = $this->getMock('\\FOF30\\Tests\\Stubs\\Model\\TreeModelStub', array('resetTreeCache'), array(static::$container, $config));

        foreach($test['fields'] as $field => $value)
        {
            $table->$field = $value;
        }

        $return = $table->check();

        $this->assertInstanceOf('\\FOF30\Model\\TreeModel', $return, 'TreeModel::check should return an instance of itself - Case: '.$check['case']);

        foreach($check['fields'] as $field => $expected)
        {
            if(is_null($expected))
            {
                $this->assertObjectNotHasAttribute($field, $table, 'TreeModel::check set the field '.$field.' even if it should not - Case: '.$check['case']);
            }
            else
            {
                $this->assertEquals($expected, $table->$field, 'TreeModel::check failed to set the field '.$field.' - Case: '.$check['case']);
            }
        }
    }

    /**
     * @group               TreeModel
     * @group               TreeModelForceDelete
     * @covers              FOF30\Model\TreeModel::forceDelete
     * @dataProvider        TreeModelDataprovider::getTestForceDelete
     */
    public function testForceDelete($test, $check)
    {
        $msg = 'TreeModel::forceDelete %s - Case: '.$check['case'];

        $config = array(
            'autoChecks'  => false,
            'idFieldName' => 'foftest_nestedset_id',
            'tableName'   => '#__foftest_nestedsets'
        );

        $db = \JFactory::getDbo();

        $table = new TreeModelStub(static::$container, $config, array(
            'onBeforeDelete' => $test['mock']['before']
        ));

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        $return = $table->forceDelete($test['delete']);

        if($check['return'])
        {
            $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $return, sprintf($msg, 'Should return an instance of itself'));
        }
        else
        {
            $this->assertFalse($return, sprintf($msg, 'Should return false'));
        }


        $pk    = $table->getIdFieldName();
        $query = $db->getQuery(true)->select($pk)->from($table->getTableName());
        $items = $db->setQuery($query)->loadColumn();

        $this->assertEmpty(array_intersect($check['deleted'], $items), sprintf($msg, 'Faiiled to delete all the items'));

        $query = $db->getQuery(true)
                    ->select('*')
                    ->from($table->getTableName())
                    ->where($db->qn($pk).' IN('.implode(',', array_keys($check['nodes'])).')');
        $nodes = $db->setQuery($query)->loadObjectList();

        foreach($nodes as $node)
        {
            $this->assertEquals($check['nodes'][$node->$pk]['lft'], $node->lft, sprintf($msg, 'Failed to update the lft value of the node with id '.$node->$pk));
            $this->assertEquals($check['nodes'][$node->$pk]['rgt'], $node->rgt, sprintf($msg, 'Failed to update the rgt value of the node with id '.$node->$pk));
        }
    }

    /**
     * @group               TreeModelReorder
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::reorder
     */
    public function tXestReorder()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);
        $table->reorder();
    }

    /**
     * @group               TreeModelMove
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::move
     */
    public function tXestMove()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);
        $table->move(-1);
    }

    /**
     * @group               TreeModelCreate
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::create
     * @dataProvider        TreeModelDataprovider::getTestCreate
     */
    public function tXestCreate($test)
    {
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $matcher = $this->never();

        if(!$test['root'])
        {
            $matcher = $this->once();
        }

        $table = $this->getMock('\\FOF30\\Tests\\Stubs\\Model\\TreeModelStub', array('insertAsChildOf', 'getParent'), array($container));

        // This is just a little trick, so insertAsChildOf won't complain about the argument passed
        \PHPUnit_Framework_Error_Notice::$enabled = false;
        $table->expects($this->once())->method('insertAsChildOf')->willReturnSelf();
        $table->expects($matcher)->method('getParent')->willReturnSelf();

        $table->findOrFail($test['loadid']);
        $table->create($test['data']);

        \PHPUnit_Framework_Error_Notice::$enabled = true;
    }

    /**
     * @group               TreeModelInsertAsRoot
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::insertAsRoot
     */
    public function tXestInsertAsRoot()
    {
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);

        $table->title = 'New root';
        $table->insertAsRoot();

        $this->assertTrue($table->isRoot(), 'TreeModel::insertAsRoot failed to create a new root');
    }

    /**
     * @group               TreeModelInsertAsRoot
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::insertAsRoot
     */
    public function tXestInsertAsRootException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);

        $table->findOrFail(1);
        $table->insertAsRoot();
    }

    /**
     * @group               TreeModelInsertAsFirstChildOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::insertAsFirstChildOf
     * @dataProvider        TreeModelDataprovider::getTestInsertAsFirstChildOf
     */
    public function tXestInsertAsFirstChildOf($test, $check)
    {
        /** @var TreeModelStub $table */
        /** @var TreeModelStub $parent */

        $msg = 'TreeModel::insertAsFirstChildOf %s - Case: '.$check['case'];
        $db  = self::$driver;

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table  = new TreeModelStub($container);
        $parent = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['title'])
        {
            $table->title = $test['title'];
        }

        $parent->findOrFail($test['parentid']);
        $parentLft = $parent->lft;
        $parentRgt = $parent->rgt;

        $return = $table->insertAsFirstChildOf($parent);

        $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $return, sprintf($msg, 'Should return an instance of itself'));

        // Assertions on the objects
        $this->assertNotEquals($test['loadid'], $table->getId(), sprintf($msg, 'Should always create a new node'));

        $this->assertEquals($parentLft, $parent->lft, sprintf($msg, 'Should not touch the lft value of the parent'));
        $this->assertEquals($parentRgt + 2, $parent->rgt, sprintf($msg, 'Should increase the rgt value by 2'));
        $this->assertEquals(1, $table->rgt - $table->lft, sprintf($msg, 'Should insert the node as leaf'));
        $this->assertEquals(1, $table->lft - $parent->lft, sprintf($msg, 'Should insert the node as first child'));

        // Great, the returned objects are ok, what about the ACTUAL data saved inside the db?
        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$table->dbtest_nestedset_id);
        $nodeDb = $db->setQuery($query)->loadObject();

        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$parent->dbtest_nestedset_id);
        $parentDb = $db->setQuery($query)->loadObject();

        $this->assertEquals($table->lft, $nodeDb->lft, sprintf($msg, 'Children object and database lft values are not the same'));
        $this->assertEquals($table->rgt, $nodeDb->rgt, sprintf($msg, 'Children object and database rgt values are not the same'));
        $this->assertEquals($parent->lft, $parentDb->lft, sprintf($msg, 'Parent object and database lft values are not the same'));
        $this->assertEquals($parent->rgt, $parentDb->rgt, sprintf($msg, 'Parent object and database rgt values are not the same'));
    }

    /**
     * @group               TreeModelInsertAsFirstChildOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::insertAsFirstChildOf
     */
    public function tXestInsertAsFirstChildOfException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table  = new TreeModelStub($container);
        $parent = $table->getClone();

        $table->insertAsFirstChildOf($parent);
    }

    /**
     * @group               TreeModelInsertAsLastChildOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::insertAsLastChildOf
     * @dataProvider        TreeModelDataprovider::getTestInsertAsLastChildOf
     */
    public function tXestInsertAsLastChildOf($test, $check)
    {
        /** @var TreeModelStub $table */
        /** @var TreeModelStub $parent */

        $msg = 'TreeModel::insertAsLastChildOf %s - Case: '.$check['case'];
        $db  = self::$driver;

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table  = new TreeModelStub($container);
        $parent = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['title'])
        {
            $table->title = $test['title'];
        }

        $parent->findOrFail($test['parentid']);
        $parentLft = $parent->lft;
        $parentRgt = $parent->rgt;

        $return = $table->insertAsLastChildOf($parent);

        $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $return, sprintf($msg, 'Should return an instance of itself'));

        // Assertions on the objects
        $this->assertNotEquals($test['loadid'], $table->getId(), sprintf($msg, 'Should always create a new node'));

        $this->assertEquals($parentLft, $parent->lft, sprintf($msg, 'Should not touch the lft value of the parent'));
        $this->assertEquals($parentRgt + 2, $parent->rgt, sprintf($msg, 'Should increase the rgt value by 2'));
        $this->assertEquals(1, $table->rgt - $table->lft, sprintf($msg, 'Should insert the node as leaf'));
        $this->assertEquals(1, $parent->rgt - $table->rgt, sprintf($msg, 'Should insert the node as last child'));

        // Great, the returned objects are ok, what about the ACTUAL data saved inside the db?
        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$table->dbtest_nestedset_id);
        $nodeDb = $db->setQuery($query)->loadObject();

        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$parent->dbtest_nestedset_id);
        $parentDb = $db->setQuery($query)->loadObject();

        $this->assertEquals($table->lft, $nodeDb->lft, sprintf($msg, 'Children object and database lft values are not the same'));
        $this->assertEquals($table->rgt, $nodeDb->rgt, sprintf($msg, 'Children object and database rgt values are not the same'));
        $this->assertEquals($parent->lft, $parentDb->lft, sprintf($msg, 'Parent object and database lft values are not the same'));
        $this->assertEquals($parent->rgt, $parentDb->rgt, sprintf($msg, 'Parent object and database rgt values are not the same'));
    }

    /**
     * @group               TreeModelInsertAsLastChildOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::insertAsLastChildOf
     */
    public function tXestInsertAsLastChildOfException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table  = new TreeModelStub($container);
        $parent = $table->getClone();

        $table->insertAsLastChildOf($parent);
    }

    /**
     * @group               TreeModelInsertLeftOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::insertLeftOf
     * @dataProvider        TreeModelDataprovider::getTestInsertLeftOf
     */
    public function tXestInsertLeftOf($test, $check)
    {
        /** @var TreeModelStub $table */
        /** @var TreeModelStub $sibling */

        $msg = 'TreeModel::insertLeftOf %s - Case: '.$check['case'];
        $db  = self::$driver;

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table  = new TreeModelStub($container);
        $sibling = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['title'])
        {
            $table->title = $test['title'];
        }

        $sibling->findOrFail($test['siblingid']);
        $siblingLft = $sibling->lft;
        $siblingRgt = $sibling->rgt;

        $return = $table->insertLeftOf($sibling);

        $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $return, sprintf($msg, 'Should return an instance of itself for chaining'));

        // Assertions on the objects
        $this->assertNotEquals($test['loadid'], $table->getId(), sprintf($msg, 'Should always create a new node'));
        $this->assertEquals($siblingLft + 2, $sibling->lft, sprintf($msg, 'Should increase the lft value by 2'));
        $this->assertEquals($siblingRgt + 2, $sibling->rgt, sprintf($msg, 'Should increase the rgt value by 2'));
        $this->assertEquals(1, $table->rgt - $table->lft, sprintf($msg, 'Should insert the node as leaf'));
        $this->assertEquals(1, $sibling->lft - $table->rgt, sprintf($msg, 'Should insert the node on the left of the sibling'));

        // Great, the returned objects are ok, what about the ACTUAL data saved inside the db?
        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$table->dbtest_nestedset_id);
        $nodeDb = $db->setQuery($query)->loadObject();

        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$sibling->dbtest_nestedset_id);
        $siblingDb = $db->setQuery($query)->loadObject();

        $this->assertEquals($table->lft, $nodeDb->lft, sprintf($msg, 'Node object and database lft values are not the same'));
        $this->assertEquals($table->rgt, $nodeDb->rgt, sprintf($msg, 'Node object and database rgt values are not the same'));
        $this->assertEquals($sibling->lft, $siblingDb->lft, sprintf($msg, 'Sibling object and database lft values are not the same'));
        $this->assertEquals($sibling->rgt, $siblingDb->rgt, sprintf($msg, 'Sibling object and database rgt values are not the same'));
    }

    /**
     * @group               TreeModelInsertLeftOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::insertLeftOf
     */
    public function tXestInsertLeftOfException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table  = new TreeModelStub($container);
        $sibling = $table->getClone();

        $table->insertLeftOf($sibling);
    }

    /**
     * @group               TreeModelInsertRightOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::insertRightOf
     * @dataProvider        TreeModelDataprovider::getTestInsertRightOf
     */
    public function tXestInsertRightOf($test, $check)
    {
        /** @var TreeModelStub $table */
        /** @var TreeModelStub $sibling */

        $msg = 'TreeModel::insertRightOf %s - Case: '.$check['case'];
        $db  = self::$driver;

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table  = new TreeModelStub($container);
        $sibling = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['title'])
        {
            $table->title = $test['title'];
        }

        $sibling->findOrFail($test['siblingid']);
        $siblingLft = $sibling->lft;
        $siblingRgt = $sibling->rgt;

        $return = $table->insertRightOf($sibling);

        $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $return, sprintf($msg, 'Should return an instance of itself'));

        // Assertions on the objects
        $this->assertNotEquals($test['loadid'], $table->getId(), sprintf($msg, 'Should always create a new node'));
        $this->assertEquals($siblingLft, $sibling->lft, sprintf($msg, 'Should not modify the lft value'));
        $this->assertEquals($siblingRgt, $sibling->rgt, sprintf($msg, 'Should not modify the rgt value'));
        $this->assertEquals(1, $table->rgt - $table->lft, sprintf($msg, 'Should insert the node as leaf'));
        $this->assertEquals(1, $table->lft - $sibling->rgt, sprintf($msg, 'Should insert the node on the right of the sibling'));

        // Great, the returned objects are ok, what about the ACTUAL data saved inside the db?
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__dbtest_nestedsets')
            ->where('dbtest_nestedset_id = '.$table->dbtest_nestedset_id);
        $nodeDb = $db->setQuery($query)->loadObject();

        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__dbtest_nestedsets')
            ->where('dbtest_nestedset_id = '.$sibling->dbtest_nestedset_id);
        $siblingDb = $db->setQuery($query)->loadObject();

        $this->assertEquals($table->lft, $nodeDb->lft, sprintf($msg, 'Node object and database lft values are not the same'));
        $this->assertEquals($table->rgt, $nodeDb->rgt, sprintf($msg, 'Node object and database rgt values are not the same'));
        $this->assertEquals($sibling->lft, $siblingDb->lft, sprintf($msg, 'Sibling object and database lft values are not the same'));
        $this->assertEquals($sibling->rgt, $siblingDb->rgt, sprintf($msg, 'Sibling object and database rgt values are not the same'));
    }

    /**
     * @group               TreeModelInsertRightOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::insertRightOf
     */
    public function tXestInsertRightOfException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table  = new TreeModelStub($container);
        $sibling = $table->getClone();

        $table->insertRightOf($sibling);
    }

    /**
     * @group               TreeModelMoveLeft
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::moveLeft
     * @dataProvider        TreeModelDataprovider::getTestMoveLeft
     */
    public function tXestMoveLeft($test, $check)
    {
        $counter = 0;
        $sibling = null;
        $msg     = 'TreeModel::moveLeft %s - Case: '.$check['case'];

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = $this->getMock('\\FOF30\\Tests\\Stubs\\Model\\TreeModelStub', array('moveToLeftOf'), array($container));
        $table->expects($this->any())->method('moveToLeftOf')->willReturnCallback(
            function($leftSibling) use (&$counter, &$sibling){
                $counter++;
                $sibling = $leftSibling->dbtest_nestedset_id;
            }
        );

        $table->findOrFail($test['loadid']);

        $table->moveLeft();

        $this->assertEquals($check['counter'], $counter, sprintf($msg, "Invoked moveToLefOf the wrong number of time"));
        $this->assertEquals($check['sibling'], $sibling, sprintf($msg, "Invoked moveToLefOf with the wrong sibling"));
    }

    /**
     * @group               TreeModelMoveLeft
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::moveLeft
     */
    public function tXestMoveLeftException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table  = new TreeModelStub($container);

        $table->moveLeft();
    }

    /**
     * @group               TreeModelMoveRight
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::moveRight
     * @dataProvider        TreeModelDataprovider::getTestMoveRight
     */
    public function tXestMoveRight($test, $check)
    {
        $counter = 0;
        $sibling = null;
        $msg     = 'TreeModel::moveRight %s - Case: '.$check['case'];

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = $this->getMock('\\FOF30\\Tests\\Stubs\\Model\\TreeModelStub', array('moveToRightOf'), array($container));
        $table->expects($this->any())->method('moveToRightOf')->willReturnCallback(
            function($rightSibling) use (&$counter, &$sibling){
                $counter++;
                $sibling = $rightSibling->dbtest_nestedset_id;
            }
        );

        $table->findOrFail($test['loadid']);

        $table->moveRight();

        $this->assertEquals($check['counter'], $counter, sprintf($msg, "Invoked moveToRightOf the wrong number of time"));
        $this->assertEquals($check['sibling'], $sibling, sprintf($msg, "Invoked moveToRightOf with the wrong sibling"));
    }

    /**
     * @group               TreeModelMoveRight
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::moveRight
     */
    public function tXestMoveRightException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table  = new TreeModelStub($container);

        $table->moveRight();
    }

    /**
     * @group               TreeModelMoveToLeftOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::moveToLeftOf
     * @dataProvider        TreeModelDataprovider::getTestMoveToLeftOf
     */
    public function tXestMoveToLeftOf($test, $check)
    {
        /** @var TreeModelStub $table */
        /** @var TreeModelStub $sibling */

        $msg = 'TreeModel::moveToLeftOf %s - Case: '.$check['case'];
        $db  = self::$driver;

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $sibling = $table->getClone();

        // Am I request to create a different root?
        if($test['newRoot'])
        {
            $root = $table->getClone();
            $root->title = 'New root';
            $root->insertAsRoot();

            $child = $table->getClone();
            $child->title = 'First child 2nd root';
            $child->insertAsChildOf($root);

            $child->reset();

            $child->title = 'Second child 2nd root';
            $child->insertAsChildOf($root);
        }

        $table->findOrFail($test['loadid']);
        $sibling->findOrFail($test['siblingid']);

        $return = $table->moveToLeftOf($sibling);

        $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $return, sprintf($msg, 'Should return an instance of itself'));

        // Assertions on the objects
        $this->assertEquals($check['table']['lft'], $table->lft, sprintf($msg, 'Failed to assign the correct lft value to the node'));
        $this->assertEquals($check['table']['rgt'], $table->rgt, sprintf($msg, 'Failed to assign the correct rgt value to the node'));

        // Great, the returned objects are ok, what about the ACTUAL data saved inside the db?
        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$table->dbtest_nestedset_id);
        $nodeDb = $db->setQuery($query)->loadObject();

        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$sibling->dbtest_nestedset_id);
        $siblingDb = $db->setQuery($query)->loadObject();

        $this->assertEquals($table->lft, $nodeDb->lft, sprintf($msg, 'Node object and database lft values are not the same'));
        $this->assertEquals($table->rgt, $nodeDb->rgt, sprintf($msg, 'Node object and database rgt values are not the same'));
        $this->assertEquals($check['sibling']['lft'], $siblingDb->lft, sprintf($msg, 'Saved the wrong lft value for the sibling'));
        $this->assertEquals($check['sibling']['rgt'], $siblingDb->rgt, sprintf($msg, 'Saved the wrong rgt value for the sibling'));
    }

    /**
     * @group               TreeModelMoveToLeftOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::moveToLeftOf
     * @dataProvider        TreeModelDataprovider::getTestMoveToLeftOfException
     */
    public function tXestMoveToLeftOfException($test)
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $sibling = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['siblingid'])
        {
            $sibling->findOrFail($test['siblingid']);
        }

        $table->moveToLeftOf($sibling);
    }

    /**
     * @group               TreeModelMoveToRightOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::moveToRightOf
     * @dataProvider        TreeModelDataprovider::getTestMoveToRightOf
     */
    public function tXestMoveToRightOf($test, $check)
    {
        /** @var TreeModelStub $table */
        /** @var TreeModelStub $sibling */

        $msg = 'TreeModel::moveToRightOf %s - Case: '.$check['case'];
        $db  = self::$driver;

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $sibling = $table->getClone();

        // Am I request to create a different root?
        if($test['newRoot'])
        {
            $root = $table->getClone();
            $root->title = 'New root';
            $root->insertAsRoot();

            $child = $table->getClone();
            $child->title = 'First child 2nd root';
            $child->insertAsChildOf($root);

            $child->reset();

            $child->title = 'Second child 2nd root';
            $child->insertAsChildOf($root);
        }

        $table->findOrFail($test['loadid']);
        $sibling->findOrFail($test['siblingid']);

        $return = $table->moveToRightOf($sibling);

        $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $return, sprintf($msg, 'Should return an instance of itself'));

        // Assertions on the objects
        $this->assertEquals($check['table']['lft'], $table->lft, sprintf($msg, 'Failed to assign the correct lft value to the node'));
        $this->assertEquals($check['table']['rgt'], $table->rgt, sprintf($msg, 'Failed to assign the correct rgt value to the node'));

        // Great, the returned objects are ok, what about the ACTUAL data saved inside the db?
        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$table->dbtest_nestedset_id);
        $nodeDb = $db->setQuery($query)->loadObject();

        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$sibling->dbtest_nestedset_id);
        $siblingDb = $db->setQuery($query)->loadObject();

        $this->assertEquals($table->lft, $nodeDb->lft, sprintf($msg, 'Node object and database lft values are not the same'));
        $this->assertEquals($table->rgt, $nodeDb->rgt, sprintf($msg, 'Node object and database rgt values are not the same'));
        $this->assertEquals($check['sibling']['lft'], $siblingDb->lft, sprintf($msg, 'Saved the wrong lft value for the sibling'));
        $this->assertEquals($check['sibling']['rgt'], $siblingDb->rgt, sprintf($msg, 'Saved the wrong rgt value for the sibling'));
    }

    /**
     * @group               TreeModelMoveToRightOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::moveToRightOf
     * @dataProvider        TreeModelDataprovider::getTestMoveToRightOfException
     */
    public function tXestMoveToRightOfException($test)
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $sibling = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['siblingid'])
        {
            $sibling->findOrFail($test['siblingid']);
        }

        $table->moveToRightOf($sibling);
    }

    /**
     * @group               TreeModelMakeFirstChildOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::makeFirstChildOf
     * @dataProvider        TreeModelDataprovider::getTestMakeFirstChildOf
     */
    public function tXestMakeFirstChildOf($test, $check)
    {
        /** @var TreeModelStub $table */
        /** @var TreeModelStub $parent */

        $msg = 'TreeModel::makeFirstChildOf %s - Case: '.$check['case'];
        $db  = self::$driver;

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $parent = $table->getClone();

        $table->findOrFail($test['loadid']);
        $parent->findOrFail($test['parentid']);

        $return = $table->makeFirstChildOf($parent);

        $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $return, sprintf($msg, 'Should return an instance of itself'));

        // Assertions on the objects
        $this->assertEquals($check['table']['lft'], $table->lft, sprintf($msg, 'Failed to assign the correct lft value to the node'));
        $this->assertEquals($check['table']['rgt'], $table->rgt, sprintf($msg, 'Failed to assign the correct rgt value to the node'));

        // Great, the returned objects are ok, what about the ACTUAL data saved inside the db?
        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$table->dbtest_nestedset_id);
        $nodeDb = $db->setQuery($query)->loadObject();

        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$parent->dbtest_nestedset_id);
        $parentDb = $db->setQuery($query)->loadObject();

        $this->assertEquals($table->lft, $nodeDb->lft, sprintf($msg, 'Node object and database lft values are not the same'));
        $this->assertEquals($table->rgt, $nodeDb->rgt, sprintf($msg, 'Node object and database rgt values are not the same'));
        $this->assertEquals($check['parent']['lft'], $parentDb->lft, sprintf($msg, 'Saved the wrong lft value for the parent'));
        $this->assertEquals($check['parent']['rgt'], $parentDb->rgt, sprintf($msg, 'Saved the wrong rgt value for the parent'));
    }

    /**
     * @group               TreeModelMakeFirstChildOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::makeFirstChildOf
     * @dataProvider        TreeModelDataprovider::getTestMakeFirstChildOfException
     */
    public function tXestMakeFirstChildOfException($test)
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $parent = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['siblingid'])
        {
            $parent->findOrFail($test['parentid']);
        }

        $table->makeFirstChildOf($parent);
    }

    /**
     * @group               TreeModelMakeLastChildOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::makeLastChildOf
     * @dataProvider        TreeModelDataprovider::getTestMakeLastChildOf
     */
    public function tXestMakeLastChildOf($test, $check)
    {
        /** @var TreeModelStub $table */
        /** @var TreeModelStub $parent */

        $msg = 'TreeModel::makeLastChildOf %s - Case: '.$check['case'];
        $db  = self::$driver;

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $parent = $table->getClone();

        $table->findOrFail($test['loadid']);
        $parent->findOrFail($test['parentid']);

        $return = $table->makeLastChildOf($parent);

        $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $return, sprintf($msg, 'Should return an instance of itself'));

        // Assertions on the objects
        $this->assertEquals($check['table']['lft'], $table->lft, sprintf($msg, 'Failed to assign the correct lft value to the node'));
        $this->assertEquals($check['table']['rgt'], $table->rgt, sprintf($msg, 'Failed to assign the correct rgt value to the node'));

        // Great, the returned objects are ok, what about the ACTUAL data saved inside the db?
        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$table->dbtest_nestedset_id);
        $nodeDb = $db->setQuery($query)->loadObject();

        $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__dbtest_nestedsets')
                    ->where('dbtest_nestedset_id = '.$parent->dbtest_nestedset_id);
        $parentDb = $db->setQuery($query)->loadObject();

        $this->assertEquals($table->lft, $nodeDb->lft, sprintf($msg, 'Node object and database lft values are not the same'));
        $this->assertEquals($table->rgt, $nodeDb->rgt, sprintf($msg, 'Node object and database rgt values are not the same'));
        $this->assertEquals($check['parent']['lft'], $parentDb->lft, sprintf($msg, 'Saved the wrong lft value for the parent'));
        $this->assertEquals($check['parent']['rgt'], $parentDb->rgt, sprintf($msg, 'Saved the wrong rgt value for the parent'));
    }

    /**
     * @group               TreeModelMakeLastChildOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::makeLastChildOf
     * @dataProvider        TreeModelDataprovider::getTestMakeLastChildOfException
     */
    public function tXestMakeLastChildOfException($test)
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $parent = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['siblingid'])
        {
            $parent->findOrFail($test['parentid']);
        }

        $table->makeLastChildOf($parent);
    }

    /**
     * @group               TreeModelMakeRoot
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::makeRoot
     * @dataProvider        TreeModelDataprovider::getTestMakeRoot
     */
    public function tXestMakeRoot($test, $check)
    {
        $msg     = 'TreeModel::makeRoot %s - Case: '.$check['case'];
        $counter = 0;

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = $this->getMock('\\FOF30\\Tests\\Stubs\\Model\\TreeModelStub', array('moveToRightOf', 'isRoot', 'getRoot', 'equals'), array($container));
        $table->expects($this->any())->method('isRoot')->willReturn($test['mock']['isRoot']);
        $table->expects($this->any())->method('getRoot')->willReturnSelf();
        $table->expects($this->any())->method('equals')->willReturn($test['mock']['equals']);
        $table->expects($this->any())->method('moveToRightOf')->willReturnCallback(
            function() use (&$counter) {
                $counter++;
                return true;
            }
        );

        $return = $table->makeRoot();

        $this->assertEquals($check['move'], $counter, sprintf($msg, 'Invoke the moveToRightOf method the wrong number of times'));
        $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $return, 'TreeModel::makeRoot should return an instance of itself');
    }

    /**
     * @group               TreeModelGetLevel
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::getLevel
     * @dataProvider        TreeModelDataprovider::getTestGetLevel
     */
    public function tXestGetLevel($test, $check)
    {
        $msg     = 'TreeModel::getLevel %s - Case: '.$check['case'];

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $table->findOrFail($test['loadid']);

        if($test['cache'])
        {
            ReflectionHelper::setValue($table, 'treeDepth', $test['cache']);
        }

        $level = $table->getLevel();

        $this->assertEquals($check['level'], $level, sprintf($msg, 'Returned the wrong level'));
    }

    /**
     * @group               TreeModelGetLevel
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::getLevel
     */
    public function tXestGetLevelException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $table->getLevel();
    }

    /**
     * @group               TreeModelGetParent
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::getParent
     * @dataProvider        TreeModelDataprovider::getTestGetParent
     */
    public function tXestGetParent($test, $check)
    {
        $msg     = 'TreeModel::getParent %s - Case: '.$check['case'];

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table   = new TreeModelStub($container);
        $table->findOrFail($test['loadid']);

        if(!is_null($test['cache']))
        {
            if($test['cache'] == 'loadself')
            {
                ReflectionHelper::setValue($table, 'treeParent', $table);
            }
            else
            {
                ReflectionHelper::setValue($table, 'treeParent', $test['cache']);
            }
        }

        $parent = $table->getParent();

        $this->assertInstanceOf('\\FOF30\\Model\\TreeModel', $parent, sprintf($msg, 'Should return an instance of TreeModel'));
        $this->assertEquals($check['parent'], $parent->dbtest_nestedset_id, sprintf($msg, 'Returned the wrong parent id'));
    }

    /**
     * @group               TreeModelIsRoot
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::isRoot
     * @dataProvider        TreeModelDataprovider::getTestIsRoot
     */
    public function tXestIsRoot($test, $check)
    {
        $msg       = 'TreeModel::isRoot %s - Case: '.$check['case'];
        $counter   = 0;
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = $this->getMock('\\FOF30\\Tests\\Stubs\\Model\\TreeModelStub', array('getLevel'), array($container));
        $table->expects($this->any())->method('getLevel')->willReturnCallback(
            function()use (&$counter, $test){
                $counter++;
                return $test['mock']['getLevel'];
            }
        );

        $table->findOrFail($test['loadid']);

        $result = $table->isRoot();

        $this->assertEquals($check['getLevel'], $counter, sprintf($msg, 'Invoked the wrong number of times getLevel method'));
        $this->assertEquals($check['result'], $result, sprintf($msg, 'Returned the wrong value'));
    }

    /**
     * @group               TreeModelIsLeaf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::isLeaf
     * @dataProvider        TreeModelDataprovider::getTestIsLeaf
     */
    public function tXestIsLeaf($test, $check)
    {
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);

        $table->lft = $test['lft'];
        $table->rgt = $test['rgt'];

        $result = $table->isLeaf();

        $this->assertEquals($check['result'], $result, 'TreeModel::isLeaf returned the wrong value - Case: '.$check['case']);
    }

    /**
     * @group               TreeModelIsLeaf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::isLeaf
     */
    public function tXestIsLeafException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);
        $table->isLeaf();
    }

    /**
     * @group               TreeModelIsDescendantOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::isDescendantOf
     * @dataProvider        TreeModelDataprovider::getTestIsDescendantOf
     */
    public function tXestIsDescendantOf($test, $check)
    {
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);
        $other = $table->getClone();

        $table->findOrFail($test['loadid']);
        $other->findOrFail($test['otherid']);

        $result = $table->isDescendantOf($other);

        $this->assertEquals($check['result'], $result, 'TreeModel::isDescendantOf returned the wrong value - Case: '.$check['case']);
    }

    /**
     * @group               TreeModelIsDescendantOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::isDescendantOf
     * @dataProvider        TreeModelDataprovider::getTestIsDescendantOfException
     */
    public function tXestIsDescendantOfException($test)
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);
        $other  = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['otherid'])
        {
            $other->findOrFail($test['otherid']);
        }

        $table->isDescendantOf($other);
    }

    /**
     * @group               TreeModelIsSelfOrDescendantOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::isSelfOrDescendantOf
     * @dataProvider        TreeModelDataprovider::getTestIsSelfOrDescendantOf
     */
    public function tXestIsSelfOrDescendantOf($test, $check)
    {
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);
        $other  = $table->getClone();

        $table->findOrFail($test['loadid']);
        $other->findOrFail($test['otherid']);

        $result = $table->isSelfOrDescendantOf($other);

        $this->assertEquals($check['result'], $result, 'TreeModel::isDescendantOf returned the wrong value - Case: '.$check['case']);
    }

    /**
     * @group               TreeModelIsSelfOrDescendantOf
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::isSelfOrDescendantOf
     * @dataProvider        TreeModelDataprovider::getTestIsSelfOrDescendantOfException
     */
    public function tXestIsSelfOrDescendantOfException($test)
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);
        $other  = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['otherid'])
        {
            $other->findOrFail($test['otherid']);
        }

        $table->isSelfOrDescendantOf($other);
    }

    /**
     * @group               TreeModelEquals
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::equals
     * @dataProvider        TreeModelDataprovider::getTestEquals
     */
    public function tXestEquals($test, $check)
    {
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);
        $other = $table->getClone();

        $table->findOrFail($test['loadid']);
        $other->findOrFail($test['otherid']);

        if(!is_null($test['forceTableId']))
        {
            $pk = $table->getIdFieldName();
            $table->$pk = $test['forceTableId'];
        }

        if(!is_null($test['forceOtherId']))
        {
            $pk = $other->getIdFieldName();
            $other->$pk = $test['forceOtherId'];
        }

        $result = $table->equals($other);

        $this->assertEquals($check['result'], $result, 'TreeModel::equals returned the wrong value - Case: '.$check['case']);
    }

    /**
     * @group               TreeModelEquals
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::equals
     * @dataProvider        TreeModelDataprovider::getTestEqualsException
     */
    public function tXestEqualsException($test)
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);
        $other = $table->getClone();

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['otherid'])
        {
            $other->findOrFail($test['otherid']);
        }

        $table->equals($other);
    }

    /**
     * @group               TreeModelInSameScope
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::inSameScope
     * @dataProvider        TreeModelDataprovider::getTestInSameScope
     */
    public function tXestInSameScope($test, $check)
    {
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = $this->getMock('\\FOF30\\Tests\\Stubs\\Model\\TreeModelStub', array('isLeaf', 'isRoot', 'isChild'), array($container));
        $table->expects($this->any())->method('isLeaf')->willReturn($test['mock']['table']['isLeaf']);
        $table->expects($this->any())->method('isRoot')->willReturn($test['mock']['table']['isRoot']);
        $table->expects($this->any())->method('isChild')->willReturn($test['mock']['table']['isChild']);

        $other = $this->getMock('\\FOF30\\Tests\\Stubs\\Model\\TreeModelStub', array('isLeaf', 'isRoot', 'isChild'), array($container));
        $other->expects($this->any())->method('isLeaf')->willReturn($test['mock']['other']['isLeaf']);
        $other->expects($this->any())->method('isRoot')->willReturn($test['mock']['other']['isRoot']);
        $other->expects($this->any())->method('isChild')->willReturn($test['mock']['other']['isChild']);

        $result = $table->inSameScope($other);

        $this->assertEquals($check['result'], $result, 'TreeModel::inSameScope returned the wrong value - Case: '.$check['case']);
    }

    /**
     * @group               TreeModelScopeImmediateDescendants
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::scopeImmediateDescendants
     * @dataProvider        TreeModelDataprovider::getTestScopeImmediateDescendants
     */
    public function tXestScopeImmediateDescendants($test, $check)
    {
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);
        $table->findOrFail($test['loadid']);

        ReflectionHelper::invoke($table, 'scopeImmediateDescendants');

        // Let's get the built where clause and "normalize" it
        $reflection = ReflectionHelper::getValue($table, 'whereClauses');
        $where = array_pop($reflection);
        preg_match_all('#IN\s?\((.*?)\)#', $where, $matches);

        $where = explode(',', str_replace("'", '', $matches[1][0]));
        $where = array_map('trim', $where);

        $this->assertEquals($check['result'], $where, 'TreeModel::scopeImmediateDescendants applied the wrong where - Case: '.$check['case']);
    }

    /**
     * @group               TreeModelScopeImmediateDescendants
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::scopeImmediateDescendants
     */
    public function tXestScopeImmediateDescendantsException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);

        ReflectionHelper::invoke($table, 'scopeImmediateDescendants');
    }

    /**
     * @group               TreeModelGetRoot
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::getRoot
     * @dataProvider        TreeModelDataprovider::getTestRoot
     */
    public function tXestGetRoot($test, $check)
    {
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);

        // Am I request to create a different root?
        if($test['newRoot'])
        {
            $root = $table->getClone();
            $root->title = 'New root';
            $root->insertAsRoot();

            $child = $table->getClone();
            $child->title = 'First child 2nd root';
            $child->insertAsChildOf($root);

            $child->reset();

            $child->title = 'Second child 2nd root';
            $child->insertAsChildOf($root);

            $grandson = $child->getClone();
            $grandson->reset();
            $grandson->title = 'First grandson of second child';
            $grandson->insertAsChildOf($child);
        }

        $table->findOrFail($test['loadid']);

        if(!is_null($test['cache']))
        {
            if($test['cache'] == 'loadself')
            {
                ReflectionHelper::setValue($table, 'treeRoot', $table);
            }
            else
            {
                ReflectionHelper::setValue($table, 'treeRoot', $test['cache']);
            }
        }

        // I have to check the lft value, since the id could change throught test iteration (table deleted and not truncated)
        $return = $table->getRoot();
        $root   = $return->lft;

        $this->assertEquals($check['result'], $root, 'TreeModel::getRoot returned the wrong root - Case: '.$check['case']);
    }

    /**
     * @group               TreeModelGetRoot
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::getRoot
     * @dataProvider        TreeModelDataprovider::getTestRootException
     */
    public function tXestGetRootException($test)
    {
        $this->setExpectedException('RuntimeException');

        $counter   = 0;
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = $this->getMock('\\FOF30\\Tests\\Stubs\\Model\\TreeModelStub', array('firstOrFail', 'isRoot'), array($container));
        $table->expects($this->any())->method('isRoot')->willReturn(false);

        // I want to throw an exception at the first run
        if($test['mock']['firstOrFail'][0])
        {
            $table->expects($this->any())->method('firstOrFail')->willThrowException(new \RuntimeException());
        }
        // The first run is ok, the exception will be thrown at the second call
        else
        {
            $table->expects($this->any())->method('firstOrFail')->willReturnCallback(
                function() use($table, &$counter){
                    if(!$counter)
                    {
                        $counter++;

                        $clone = $table->getClone();
                        $clone->lft = 1000;
                        $clone->rgt = 1001;

                        return $clone;
                    }
                    else
                    {
                        throw new \RuntimeException();
                    }
                }
            );
        }

        if($test['loadid'])
        {
            $table->findOrFail($test['loadid']);
        }

        if($test['wrongNode'])
        {
            $table->lft = 2000;
            $table->rgt = 2001;
        }

        $table->getRoot();
    }

    /**
     * @group               TreeModelGetNestedList
     * @group               TreeModel
     * @covers              FOF30\Model\TreeModel::getNestedList
     * @dataProvider        TreeModelDataprovider::getTestGetNestedList
     */
    public function tXestGetNestedList($test, $check)
    {
        $container = new TestContainer(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'dbtest_nestedset_id',
                'tableName'   => '#__dbtest_nestedsets'
            )
        ));

        $table = new TreeModelStub($container);

        $result = $table->getNestedList($test['column'], $test['key'], $test['separator']);

        $this->assertEquals($check['result'], $result, 'TreeModel::getNestedList returned the wrong list');
    }
}