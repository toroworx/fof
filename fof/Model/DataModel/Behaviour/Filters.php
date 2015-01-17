<?php
/**
 * @package     FOF
 * @copyright   2010-2015 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license     GNU GPL version 2 or later
 */

namespace FOF30\Model\DataModel\Behaviour;

use FOF30\Event\Observer;
use FOF30\Model\DataModel;
use JDatabaseQuery;

defined('_JEXEC') or die;

class Filters extends Observer
{
	/**
	 * This event runs after we have built the query used to fetch a record
	 * list in a model. It is used to apply automatic query filters.
	 *
	 * @param   DataModel  &$model  The model which calls this event
	 * @param   JDatabaseQuery      &$query  The query we are manipulating
	 *
	 * @return  void
	 */
	public function onAfterBuildQuery(&$model, &$query)
	{
		$tableKey = $model->getIdFieldName();
		$db = $model->getDbo();

		$fields = $model->getTableFields();

		foreach ($fields as $fieldname => $fieldmeta)
		{
			$fieldInfo = (object)array(
				'name'	=> $fieldname,
				'type'	=> $fieldmeta->Type,
			);

			$filterName = ($fieldInfo->name == $tableKey) ? 'id' : $fieldInfo->name;
			$filterState = $model->getState($filterName, null);

			$field = DataModel\Filter\AbstractFilter::getField($fieldInfo, array('dbo' => $db));

			if (!is_object($field) || !($field instanceof DataModel\Filter\AbstractFilter))
			{
				continue;
			}

			if ((is_array($filterState) && (
						array_key_exists('value', $filterState) ||
						array_key_exists('from', $filterState) ||
						array_key_exists('to', $filterState)
					)) || is_object($filterState))
			{
				$options = new \JRegistry($filterState);
			}
			else
			{
				$options = new \JRegistry();
				$options->set('value', $filterState);
			}

			$methods = $field->getSearchMethods();
			$method = $options->get('method', $field->getDefaultSearchMethod());

			if (!in_array($method, $methods))
			{
				$method = 'exact';
			}

			switch ($method)
			{
				case 'between':
				case 'outside':
					$sql = $field->$method($options->get('from', null), $options->get('to'));
					break;

				case 'interval':
					$sql = $field->$method($options->get('value', null), $options->get('interval'));
					break;

				case 'search':
					$sql = $field->$method($options->get('value', null), $options->get('operator', '='));
					break;

				case 'exact':
				case 'partial':
				default:
					$sql = $field->$method($options->get('value', null));
					break;
			}

			if ($sql)
			{
				$query->where($sql);
			}
		}
	}
}