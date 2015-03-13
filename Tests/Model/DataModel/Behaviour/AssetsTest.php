<?php
/**
 * @package	    FrameworkOnFramework.UnitTest
 * @subpackage  TableBehaviors
 *
 * @copyright   Copyright (C) 2010 - 2015 Akeeba Ltd. All rights reserved.
 * @license	    GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace FOF30\Tests\DataModel;

use FOF30\Model\DataModel\Behaviour\Assets;
use FOF30\Tests\Helpers\ClosureHelper;
use FOF30\Tests\Helpers\DatabaseTest;
use FOF30\Tests\Helpers\TestContainer;
use FOF30\Tests\Stubs\Model\DataModelStub;

require_once 'AssetsDataprovider.php';

/**
 * @covers      FOF30\Model\DataModel\Behaviour\Assets::<protected>
 * @covers      FOF30\Model\DataModel\Behaviour\Assets::<private>
 * @package     FOF30\Tests\DataModel\Behaviour\Assets
 */
class AssetsTest extends DatabaseTest
{
    /**
     * @group           Behaviour
     * @group           AssetsOnAfterSave
     * @covers          FOF30\Model\DataModel\Behaviour\Assets::onAfterBuildQuery
     * @dataProvider    AssetsDataprovider::getTestOnAfterSave
     */
    public function testOnAfterSave($test, $check)
    {
        $msg = 'Own::onAfterBuildQuery %s - Case: '.$check['case'];
        $db  = \JFactory::getDbo();

        $config = array(
            'idFieldName' => $test['tableid'],
            'tableName'   => $test['table']
        );

        $model      = new DataModelStub(static::$container, $config);
        $dispatcher = $model->getBehavioursDispatcher();
        $behavior   = new Assets($dispatcher);

        $model->setAssetsTracked($test['track']);

        if($test['load'])
        {
            $model->find($test['load']);
        }

        if($test['rules'])
        {
            $model->setRules($test['rules']);
        }

        $query       = $db->getQuery(true)->select('COUNT(*)')->from('#__assets');
        $beforeTotal = $db->setQuery($query)->loadResult();

        $result = $behavior->onAfterSave($model);

        $this->assertTrue($result, sprintf($msg, 'Should always return true'));

        $asset = null;

        if($check['count'] == 0)
        {
            $query      = $db->getQuery(true)->select('COUNT(*)')->from('#__assets');
            $afterTotal = $db->setQuery($query)->loadResult();

            $this->assertEquals(0, $beforeTotal - $afterTotal, sprintf($msg, 'Wrong number of assets saved'));
        }
        else
        {
            // Let's check what has been saved
            $query = $db->getQuery(true)
                ->select('id, rules')
                ->from('#__assets')
                ->where('name = '.$db->q($model->getAssetName()));
            $asset = $db->setQuery($query)->loadObject();

            $this->assertEquals($check['count'], (int) (!is_null($asset)), sprintf('Wrong number of assets saved'));
        }

        if(!is_null($check['rules']))
        {
            $this->assertEquals($check['rules'], $asset->rules, sprintf($msg, 'Wrong rule stored'));
        }

        if($asset)
        {
            $asset_field = $model->getFieldAlias('asset_id');
            $model->find($test['load']);

            $this->assertEquals($asset->id, $model->$asset_field, sprintf($msg, 'Asset id not stored inside the model'));
        }
    }

    /**
     * @group           Behaviour
     * @group           AssetsOnAfterBind
     * @covers          FOF30\Model\DataModel\Behaviour\Assets::onAfterBind
     * @dataProvider    AssetsDataprovider::getTestOnAfterBind
     */
    public function testOnAfterBind($test, $check)
    {
        $msg = 'Own::onAfterBuildQuery %s - Case: '.$check['case'];

        $config = array(
            'idFieldName' => $test['tableid'],
            'tableName'   => $test['table']
        );

        $model      = new DataModelStub(static::$container, $config);
        $dispatcher = $model->getBehavioursDispatcher();
        $behavior   = new Assets($dispatcher);

        $model->setAssetsTracked($test['track']);

        if($test['load'])
        {
            $model->find($test['load']);
        }

        $return = $behavior->onAfterBind($model, $test['bind']);

        $rules  = $model->getRules();

        $this->assertTrue($return, sprintf($msg, 'Returned a wrong value'));
        $this->assertJsonStringEqualsJsonString($check['rules'], (string) $rules, sprintf($msg, 'Set rules wrong'));
    }
    
    public function tXestOnBeforeDelete($tableinfo, $test, $check)
    {
        $db              = JFactory::getDbo();
        $config['input'] = new F0FInput(array('option' => 'com_foftest', 'view' => $tableinfo['name']));

        if(isset($test['tbl_key']))
        {
            $config['tbl_key'] = $test['tbl_key'];
        }

        $table = F0FTable::getAnInstance($tableinfo['name'], 'FoftestTable', $config);

        $reflection = new ReflectionProperty($table, 'tableDispatcher');
        $reflection->setAccessible(true);
        $dispatcher = $reflection->getValue($table);

        $behavior = new F0FTableBehaviorAssets($dispatcher);

        if(isset($test['alias']))
        {
            foreach($test['alias'] as $column => $alias)
            {
                $table->setColumnAlias($column, $alias);
            }

            $table->setAssetsTracked(true);
        }

        if(isset($test['loadid']))
        {
            $table->load($test['loadid']);
        }

        $query       = $db->getQuery(true)->select('COUNT(*)')->from('#__assets');
        $beforeTotal = $db->setQuery($query)->loadResult();

        $return = $behavior->onBeforeDelete($table, isset($test['id']) ? $test['id'] : null);

        $this->assertEquals($check['return'], $return, 'F0FTableBehaviorAssets::onBeforeDelete returned a wrong value');

        $query      = $db->getQuery(true)->select('COUNT(*)')->from('#__assets');
        $afterTotal = $db->setQuery($query)->loadResult();

        $this->assertEquals($check['count'], $beforeTotal - $afterTotal, 'F0FTableBehaviorAssets::onBeforeDelete deleted a wrong number of assets');
    }
}
