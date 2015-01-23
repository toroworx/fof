<?php
/**
 * @package     FOF
 * @copyright   2010-2015 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license     GNU GPL version 2 or later
 */

namespace FOF30\Container;

use FOF30\Autoloader\Autoloader;
use FOF30\Inflector\Inflector;
use FOF30\Platform\Joomla\Filesystem as JoomlaFilesystem;
use FOF30\Platform\Joomla\Platform as JoomlaPlatform;
use FOF30\Render\RenderInterface;
use FOF30\Template\Template;
use FOF30\TransparentAuthentication\TransparentAuthentication as TransparentAuth;
use JDatabaseDriver;
use JSession;

defined('_JEXEC') or die;

/**
 * Dependency injection container for FOF-powered components.
 *
 * The properties below (except componentName, bareComponentName and the ones marked with property-read) can be
 * configured in the fof.xml component configuration file.
 *
 * Sample fof.xml:
 *
 * <fof>
 *   <common>
 *      <container>
 *         <option name="componentNamespace"><![CDATA[MyCompany\MyApplication]]></option>
 *         <option name="frontEndPath"><![CDATA[%PUBLIC%\components\com_application]]></option>
 *         <option name="factoryClass">magic</option>
 *      </container>
 *   </common>
 * </fof>
 *
 * The paths can use the variables %ROOT%, %PUBLIC%, %ADMIN%, %TMP%, %LOG% i.e. all the path keys returned by Platform's
 * getPlatformBaseDirs() method in uppercase and surrounded by percent signs.
 *
 *
 * @property  string                                   $componentName      The name of the component (com_something)
 * @property  string                                   $bareComponentName  The name of the component without com_ (something)
 * @property  string                                   $componentNamespace The namespace of the component's classes (\Foobar)
 * @property  string                                   $frontEndPath       The absolute path to the front-end files
 * @property  string                                   $backEndPath        The absolute path to the front-end files
 * @property  string                                   $thisPath           The preferred path. Backend for Admin application, frontend otherwise
 * @property  string                                   $rendererClass      The fully qualified class name of the view renderer we'll be using. Must implement FOF30\Render\RenderInterface.
 * @property  string                                   $factoryClass       The fully qualified class name or slug (basic, switch) of the MVC Factory object, default is FOF30\Factory\BasicFactory.
 *
 * @property-read  \FOF30\Configuration\Configuration  $appConfig          The application configuration registry
 * @property-read  \JDatabaseDriver                    $db                 The database connection object
 * @property-read  \FOF30\Dispatcher\Dispatcher        $dispatcher         The component's dispatcher
 * @property-read  \FOF30\Factory\FactoryInterface     $factory            The MVC object factory
 * @property-read  \FOF30\Platform\FilesystemInterface $filesystem         The filesystem abstraction layer object
 * @property-read  \FOF30\Input\Input                  $input              The input object
 * @property-read  \FOF30\Platform\PlatformInterface   $platform           The platform abstraction layer object
 * @property-read  \FOF30\Render\RenderInterface       $renderer           The view renderer
 * @property-read  \JSession                           $session            Joomla! session storage
 * @property-read  \FOF30\Template\Template            $template           The template helper
 * @property-read  TransparentAuth                     $transparentAuth    Transparent authentication handler
 * @property-read  \FOF30\Toolbar\Toolbar              $toolbar            The component's toolbar
 */
class Container extends ContainerBase
{
	/**
	 * Returns a container instance for a specific component. This method goes through fof.xml to read the default
	 * configuration values for the container. You are advised to use this unless you have a specific reason for
	 * instantiating a Container without going through the fof.xml file.
	 *
	 * @param   string  $component  The component you want to get a container for, e.g. com_foobar.
	 * @param   array   $values     Container configuration overrides you want to apply. Optional.
	 * @param   string  $section    The application section (site, admin) you want to fetch. Any other value results in auto-detection.
	 *
	 * @return \FOF30\Container\Container
	 */
	public static function &getInstance($component, array $values = array(), $section = 'auto')
	{
		// Try to auto-detect some defaults
		$tmpConfig = array_merge($section, array('componentName' => $component));
		$tmpContainer = new Container($tmpConfig);

		if (!in_array($section, array('site', 'admin')))
		{
			$section = $tmpContainer->platform->isBackend() ? 'admin' : 'site';
		}

		$appConfig = $tmpContainer->appConfig;

		// Get the namespace from fof.xml
		$namespace = $appConfig->get('container.componentNamespace', null);

		// $values always overrides $namespace and fof.xml
		if (isset($values['componentNamespace']))
		{
			$namespace = $values['componentNamespace'];
		}

		// If there is no namespace set, try to guess it.
		if (empty($namespace))
		{
			$bareComponent = $component;

			if (substr($component, 0, 4) == 'com_')
			{
				$bareComponent = substr($component, 4);
			}

			$namespace = ucfirst($bareComponent);
		}

		// Get the default front-end/back-end paths
		$frontEndPath = $appConfig->get('container.frontEndPath', JPATH_SITE . '/components/' . $component);
		$backEndPath = $appConfig->get('container.backEndPath', JPATH_ADMINISTRATOR . '/components/' . $component);

		// Parse path variables if necessary
		$frontEndPath = $tmpContainer->parsePathVariables($frontEndPath);
		$backEndPath = $tmpContainer->parsePathVariables($backEndPath);

		// Apply path overrides
		if (isset($values['frontEndPath']))
		{
			$frontEndPath = $values['frontEndPath'];
		}

		if (isset($values['backEndPath']))
		{
			$backEndPath = $values['backEndPath'];
		}

		$thisPath = ($section == 'admin') ? $backEndPath : $frontEndPath;

		// Get the namespaces for the front-end and back-end parts of the component
		$frontEndNamespace = '\\' . $namespace . '\\Site\\';
		$backEndNamespace = '\\' . $namespace . '\\Admin\\';

		// Special case: if the frontend and backend paths are identical, we don't use the Site and Admin namespace
		// suffixes after $this->componentNamespace (so you may use FOF with JApplicationWeb apps)
		if ($frontEndPath == $backEndPath)
		{
			$frontEndNamespace = '\\' . $namespace . '\\';
			$backEndNamespace = '\\' . $namespace . '\\';
		}

		// Do we have to register the component's namespaces with the autoloader?
		$autoloader = Autoloader::getInstance();

		if (!$autoloader->hasMap($frontEndNamespace))
		{
			$autoloader->addMap($frontEndNamespace, $frontEndPath);
		}

		if (!$autoloader->hasMap($backEndNamespace))
		{
			$autoloader->addMap($backEndNamespace, $backEndPath);
		}

		// Get the Container class name
		$classNamespace = ($section == 'admin') ? $backEndNamespace : $frontEndNamespace;
		$class = $classNamespace . 'Container';

		// Get the values overrides from fof.xml
		$values = array_merge($values, array(
			'componentName' => $component,
			'componentNamespace' => $namespace,
			'frontEndPath' => $frontEndPath,
			'backEndPath' => $backEndPath,
			'thisPath' => $thisPath,
			'rendererClass' => $appConfig->get('container.rendererClass', null),
			'factoryClass' => $appConfig->get('container.factoryClass', '\\FOF30\\Factory\\BasicFactory'),
		));

		unset($appConfig);
		unset($tmpConfig);
		unset($tmpContainer);

		if (class_exists($class, true))
		{
			return new $class($values);
		}
		else
		{
			return new Container($values);
		}
	}

	/**
	 * Public constructor. This does NOT go through the fof.xml file. You are advised to use getInstance() instead.
	 *
	 * @param   array  $values  Overrides for the container configuration and services
	 *
	 * @throws  \FOF30\Container\Exception\NoComponent  If no component name is specified
	 */
	public function __construct(array $values = array())
	{
		// Initialise
		$this->bareComponentName = '';
		$this->componentName = '';
		$this->componentNamespace = '';
		$this->frontEndPath = '';
		$this->backEndPath = '';
		$this->thisPath = '';
		$this->factoryClass = 'FOF30\\Factory\\BasicFactory';

		// Try to construct this container object
		parent::__construct($values);

		// Make sure we have a component name
		if (empty($this['componentName']))
		{
			throw new Exception\NoComponent;
		}

		$bareComponent = substr($this->componentName, 4);

		$this['bareComponentName'] = $bareComponent;

		// Try to guess the component's namespace
		if (empty($this['componentNamespace']))
		{
			$this->componentNamespace = ucfirst($bareComponent);
		}
		else
		{
			$this->componentNamespace = trim($this->componentNamespace, '\\');
		}

		// Make sure we have front-end and back-end paths
		if (empty($this['frontEndPath']))
		{
			$this->frontEndPath = JPATH_SITE . '/components/' . $this->componentName;
		}

		if (empty($this['backEndPath']))
		{
			$this->backEndPath = JPATH_ADMINISTRATOR . '/components/' . $this->componentName;
		}

		// Get the namespaces for the front-end and back-end parts of the component
		$frontEndNamespace = '\\' . $this->componentNamespace . '\\Site\\';
		$backEndNamespace = '\\' . $this->componentNamespace . '\\Admin\\';

		// Special case: if the frontend and backend paths are identical, we don't use the Site and Admin namespace
		// suffixes after $this->componentNamespace (so you may use FOF with JApplicationWeb apps)
		if ($this->frontEndPath == $this->backEndPath)
		{
			$frontEndNamespace = '\\' . $this->componentNamespace . '\\';
			$backEndNamespace = '\\' . $this->componentNamespace . '\\';
		}

		// Do we have to register the component's namespaces with the autoloader?
		$autoloader = Autoloader::getInstance();

		if (!$autoloader->hasMap($frontEndNamespace))
		{
			$autoloader->addMap($frontEndNamespace, $this->frontEndPath);
		}

		if (!$autoloader->hasMap($backEndNamespace))
		{
			$autoloader->addMap($backEndNamespace, $this->backEndPath);
		}

		// Filesystem abstraction service
		if (!isset($this['filesystem']))
		{
			$this['filesystem'] = function (Container $c)
			{
				return new JoomlaFilesystem($c);
			};
		}

		// Platform abstraction service
		if (!isset($this['platform']))
		{
			$this['platform'] = function (Container $c)
			{
				return new JoomlaPlatform($c);
			};
		}

		if (empty($this['thisPath']))
		{
			$this['thisPath'] = $this['frontEndPath'];

			if ($this->platform->isBackend())
			{
				$this['thisPath'] = $this['backEndPath'];
			}
		}

		// MVC Factory service
		if (!isset($this['factory']))
		{
			$this['factory'] = function (Container $c)
			{
				if (empty($c['factoryClass']))
				{
					$c['factoryClass'] = 'FOF30\\Factory\\BasicFactory';
				}

				if (strpos($c['factoryClass'], '\\') === false)
				{
					$class = $c->getNamespacePrefix() . 'Factory\\' . $c['factoryClass'];

					if (class_exists($class))
					{
						$c['factoryClass'] = $class;
					}
					else
					{
						$c['factoryClass'] = '\\FOF30\\Factory\\' . ucfirst($c['factoryClass']) . 'Factory';
 					}
				}

				if (!class_exists($c['factoryClass'], true))
				{
					$c['factoryClass'] = 'FOF30\\Factory\\BasicFactory';
				}

				$factoryClass = $c['factoryClass'];

				return new $factoryClass($c);
			};
		}

		// Component Configuration service
		if (!isset($this['appConfig']))
		{
			$this['appConfig'] = function (Container $c)
			{
				$class = $c->getNamespacePrefix() . 'Configuration\\Configuration';

				if (!class_exists($class, true))
				{
					$class = '\\FOF30\\Configuration\\Configuration';
				}

				return new $class($c);
			};
		}

		// Database Driver service
		if (!isset($this['db']))
		{
			$this['db'] = function (Container $c)
			{
				return $c->platform->getDbo();
			};
		}

		// Request Dispatcher service
		if (!isset($this['dispatcher']))
		{
			$this['dispatcher'] = function (Container $c)
			{
				return $c->factory->dispatcher();
			};
		}

		// Component toolbar provider
		if (!isset($this['toolbar']))
		{
			$this['toolbar'] = function (Container $c)
			{
				return $c->factory->toolbar();
			};
		}

		// Component toolbar provider
		if (!isset($this['transparentAuth']))
		{
			$this['transparentAuth'] = function (Container $c)
			{
				return $c->factory->transparentAuthentication();
			};
		}

		// View renderer
		if (!isset($this['renderer']))
		{
			$this['renderer'] = function (Container $c)
			{
				if (isset($c['rendererClass']) && class_exists($c['rendererClass']))
				{
					$class = $c['rendererClass'];
					$renderer = new $class($c);

					if ($renderer instanceof RenderInterface)
					{
						return $renderer;
					}
				}

				$filesystem     = $c->filesystem;

				// Try loading the stock renderers shipped with F0F
				$path = dirname(__FILE__) . '/../Render/';
				$renderFiles = $filesystem->folderFiles($path, '.php');
				$renderer = null;
				$priority = 0;

				if (!empty($renderFiles))
				{
					foreach ($renderFiles as $filename)
					{
						if ($filename == 'Base.php')
						{
							continue;
						}

						if ($filename == 'RenderInterface.php')
						{
							continue;
						}

						$camel = Inflector::camelize($filename);
						$className = 'FOF30\\Render\\' . ucfirst(Inflector::getPart($camel, 0));

						if (!class_exists($className, true))
						{
							continue;
						}

						/** @var RenderInterface $renderer */
						$renderer = new $className($c);

						$info = $renderer->getInformation();

						if (!$info->enabled)
						{
							continue;
						}

						if ($info->priority > $priority)
						{
							$priority = $info->priority;
						}
					}
				}

				return $renderer;
			};
		}

		// Input Access service
		if (!isset($this['input']))
		{
			$this['input'] = function ()
			{
				return new \FOF30\Input\Input();
			};
		}

		// Session service
		if (!isset($this['session']))
		{
			$this['session'] = function ()
			{
				return \JFactory::getSession();
			};
		}

		// Template service
		if (!isset($this['template']))
		{
			$this['template'] = function (Container $c)
			{
				return new Template($c);
			};
		}
	}

	/**
	 * Get the applicable namespace prefix for a component section. Possible sections:
	 * auto			Auto-detect which is the current component section
	 * inverse      The inverse area than auto
	 * site			Frontend
	 * admin		Backend
	 *
	 * @param   string  $section  The section you want to get information for
	 *
	 * @return  string  The namespace prefix for the component's classes, e.g. \Foobar\Example\Site\
	 */
	public function getNamespacePrefix($section = 'auto')
	{
		// Get the namespaces for the front-end and back-end parts of the component
		$frontEndNamespace = '\\' . $this->componentNamespace . '\\Site\\';
		$backEndNamespace = '\\' . $this->componentNamespace . '\\Admin\\';

		// Special case: if the frontend and backend paths are identical, we don't use the Site and Admin namespace
		// suffixes after $this->componentNamespace (so you may use FOF with JApplicationWeb apps)
		if ($this->frontEndPath == $this->backEndPath)
		{
			$frontEndNamespace = '\\' . $this->componentNamespace . '\\';
			$backEndNamespace = '\\' . $this->componentNamespace . '\\';
		}

		switch ($section)
		{
			default:
			case 'auto':
				if ($this->platform->isBackend())
				{
					return $backEndNamespace;
				}
				else
				{
					return $frontEndNamespace;
				}
				break;

			case 'inverse':
				if ($this->platform->isBackend())
				{
					return $frontEndNamespace;
				}
				else
				{
					return $backEndNamespace;
				}
				break;

			case 'site':
				return $frontEndNamespace;
				break;

			case 'admin':
				return $backEndNamespace;
				break;
		}
	}

	public function parsePathVariables($path)
	{
		$platformDirs = $this->platform->getPlatformBaseDirs();
		// root public admin tmp log

		$search = array_map(function ($x){
			return '%' . strtoupper($x) . '%';
		}, array_keys($platformDirs));
		$replace = array_values($platformDirs);

		return str_replace($search, $replace, $path);
	}
}