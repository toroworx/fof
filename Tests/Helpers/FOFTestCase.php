<?php
/**
 * @package     FOF
 * @copyright   2010-2015 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license     GNU GPL version 2 or later
 */

namespace FOF30\Tests\Helpers;


class FOFTestCase extends ApplicationTestCase
{
	/**
	 * @var			array	The JFactory pointers saved before the execution of the test
	 */
	protected $savedFactoryState = array();

	/**
	 * @var         array  The list of errors expected to be encountered during the test.
	 */
	protected $expectedErrors;

	/**
	 * Saves the Factory pointers
	 *
	 * @return void
	 */
	protected function saveFactoryState()
	{
		$this->savedFactoryState['application']	 = \JFactory::$application;
		$this->savedFactoryState['config']		 = \JFactory::$config;
		$this->savedFactoryState['dates']		 = \JFactory::$dates;
		$this->savedFactoryState['session']		 = \JFactory::$session;
		$this->savedFactoryState['language']	 = \JFactory::$language;
		$this->savedFactoryState['document']	 = \JFactory::$document;
		$this->savedFactoryState['acl']			 = \JFactory::$acl;
		$this->savedFactoryState['database']	 = \JFactory::$database;
		$this->savedFactoryState['mailer']		 = \JFactory::$mailer;
	}

	/**
	 * Sets the Factory pointers
	 *
	 * @return  void
	 */
	protected function restoreFactoryState()
	{
		\JFactory::$application	= $this->savedFactoryState['application'];
		\JFactory::$config		= $this->savedFactoryState['config'];
		\JFactory::$dates		= $this->savedFactoryState['dates'];
		\JFactory::$session		= $this->savedFactoryState['session'];
		\JFactory::$language	= $this->savedFactoryState['language'];
		\JFactory::$document	= $this->savedFactoryState['document'];
		\JFactory::$acl			= $this->savedFactoryState['acl'];
		\JFactory::$database	= $this->savedFactoryState['database'];
		\JFactory::$mailer		= $this->savedFactoryState['mailer'];
	}

	/**
	 * Assigns mock callbacks to methods.
	 * This method assumes that the mock callback is named {mock}{method name}.
	 *
	 * @param   object  $mockObject  The mock object that the callbacks are being assigned to.
	 * @param   array   $array       An array of methods names to mock with callbacks.
	 *
	 * @return  void
	 */
	public function assignMockCallbacks($mockObject, $array)
	{
		foreach ($array as $index => $method)
		{
			if (is_array($method))
			{
				$methodName	 = $index;
				$callback	 = $method;
			}
			else
			{
				$methodName	 = $method;
				$callback	 = array(get_called_class(), 'mock' . $method);
			}

			$mockObject->expects($this->any())
				->method($methodName)
				->will($this->returnCallback($callback));
		}
	}

	/**
	 * Assigns mock values to methods.
	 *
	 * @param   object  $mockObject  The mock object.
	 * @param   array   $array       An associative array of methods to mock with return values:<br />
	 * string (method name) => mixed (return value)
	 *
	 * @return  void
	 */
	public function assignMockReturns($mockObject, $array)
	{
		foreach ($array as $method => $return)
		{
			$mockObject->expects($this->any())
				->method($method)
				->will($this->returnValue($return));
		}
	}

	/**
	 * Tells the unit tests that a method or action you are about to attempt
	 * is expected to result in JError::raiseSomething being called.
	 *
	 * If you don't call this method first, the test will fail.
	 * If you call this method during your test and the error does not occur, then your test
	 * will also fail because we assume you were testing to see that an error did occur when it was
	 * supposed to.
	 *
	 * If passed without argument, the array is initialized if it hsn't been already
	 *
	 * @param   mixed  $error  The JException object to expect.
	 *
	 * @return  void
	 */
	public function setExpectedError($error = null)
	{
		if (!is_array($this->expectedErrors))
		{
			$this->expectedErrors = array();
		}

		if (!is_null($error))
		{
			$this->expectedErrors[] = $error;
		}
	}

	/**
	 * Overrides the parent setup method.
	 *
	 * @return  void
	 *
	 * @see     PHPUnit_Framework_TestCase::setUp()
	 */
	protected function setUp()
	{
		$this->setExpectedError();

		parent::setUp();
	}

	/**
	 * Overrides the parent tearDown method.
	 *
	 * @return  void
	 */
	protected function tearDown()
	{
		if (is_array($this->expectedErrors) && !empty($this->expectedErrors))
		{
			$this->fail('An expected error was not raised.');
		}

		parent::tearDown();
	}

	/**
	 * Gets a mock session object.
	 *
	 * @param   array  $options  An array of key-value options for the JSession mock.
	 * getId : the value to be returned by the mock getId method
	 * get.user.id : the value to assign to the user object id returned by get('user')
	 * get.user.name : the value to assign to the user object name returned by get('user')
	 * get.user.username : the value to assign to the user object username returned by get('user')
	 *
	 * @return  \JSession
	 */
	public function getMockSession($options = array())
	{
		// Attempt to load the real class first.
		class_exists('JSession');

		return MockSession::create($this, $options);
	}
}