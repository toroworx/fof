<?php
/**
 * @package     FOF
 * @copyright   2010-2015 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license     GNU GPL version 2 or later
 */

namespace FOF30\Factory\Magic;

use FOF30\Container\Container;
use FOF30\Controller\DataController;
use FOF30\Factory\Exception\ControllerNotFound;

defined('_JEXEC') or die;

class ControllerFactory extends BaseFactory
{
	/**
	 * @var   Container|null  The container where this factory belongs to
	 */
	protected $container = null;

	/**
	 * Public constructor
	 *
	 * @param   Container  $container  The container we belong to
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * Create a new object instance
	 *
	 * @param   string $name The name of the class we're making
	 *
	 * @return  DataController  A new DataController object
	 */
	public function make($name = null)
	{
		if (empty($name))
		{
			throw new ControllerNotFound;
		}

		$config = array(
			'name'           => $name,
			'default_task'   => $this->container->appConfig->get("views.$name.config.default_task"),
			'viewName'       => $this->container->appConfig->get("views.$name.config.viewName"),
			'modelName'      => $this->container->appConfig->get("views.$name.config.modelName"),
			'taskPrivileges' => $this->container->appConfig->get("views.$name.acl"),
			'cacheableTasks' =>  $this->container->appConfig->get("views.$name.config.cacheableTasks", array('browse', 'read')),
		);

		$controller = new DataController($this->container, $config);

		$taskMap = $this->container->appConfig->get("views.$name.taskmap");

		if (is_array($taskMap) && !empty($taskMap))
		{
			foreach ($taskMap as $virtualTask => $method)
			{
				$controller->registerTask($virtualTask, $method);
			}
		}

		return $controller;
	}
}