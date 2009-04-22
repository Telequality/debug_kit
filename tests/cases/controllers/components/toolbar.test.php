<?php
/* SVN FILE: $Id$ */
/**
 * DebugToolbar Test
 *
 * 
 *
 * PHP versions 4 and 5
 *
 * CakePHP :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright 2006-2008, Cake Software Foundation, Inc.
 *								1785 E. Sahara Avenue, Suite 490-204
 *								Las Vegas, Nevada 89104
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright 2006-2008, Cake Software Foundation, Inc.
 * @link          http://www.cakefoundation.org/projects/info/cakephp CakePHP Project
 * @package       debug_kit
 * @subpackage    debug_kit.tests
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
App::import('Component', 'DebugKit.Toolbar');

class TestToolbarComponent extends ToolbarComponent {
	function loadPanels($panels, $settings = array()) {
		$this->_loadPanels($panels, $settings);
	}
}

Mock::generate('DebugPanel');

if (!class_exists('AppController')) {
	class AppController extends Controller {
		
	}
}

/**
* DebugToolbar Test case
*/
class DebugToolbarTestCase extends CakeTestCase {
	
	function startTest() {
		Router::connect('/', array('controller' => 'pages', 'action' => 'display', 'home'));
		$this->Controller =& new Controller();
		$this->Controller->params = Router::parse('/');
		$this->Controller->params['url']['url'] = '/';
		$this->Controller->uses = array();
		$this->Controller->components = array('TestToolBar');
		$this->Controller->constructClasses();
		$this->Controller->Toolbar =& $this->Controller->TestToolBar;

		$this->_server = $_SERVER;
		$this->_paths = array();
		$this->_paths['plugin'] = Configure::read('pluginPaths');
		$this->_paths['view'] = Configure::read('viewPaths');
		$this->_paths['vendor'] = Configure::read('vendorPaths');
		$this->_paths['controller'] = Configure::read('controllerPaths');
	}
/**
 * endTest
 *
 * @return void
 **/
	function endTest() {
		$_SERVER = $this->_server;
		Configure::write('pluginPaths', $this->_paths['plugin']);
		Configure::write('viewPaths', $this->_paths['view']);
		Configure::write('vendorPaths', $this->_paths['vendor']);
		Configure::write('controllerPaths', $this->_paths['controller']);

		unset($this->Controller);
		if (class_exists('DebugKitDebugger')) {
			DebugKitDebugger::clearTimers();
		}
	}
/**
 * test Loading of panel classes
 *
 * @return void
 **/
	function testLoadPanels() {
		$this->Controller->Toolbar->loadPanels(array('session', 'request'));
		$this->assertTrue(is_a($this->Controller->Toolbar->panels['session'], 'SessionPanel'));
		$this->assertTrue(is_a($this->Controller->Toolbar->panels['request'], 'RequestPanel'));

		$this->expectError();
		$this->Controller->Toolbar->loadPanels(array('randomNonExisting', 'request'));
	}
	
/**
 * test loading of vendor panels from test_app folder
 *
 * @access public
 * @return void
 */
	function testVendorPanels() {
	    $f = Configure::read('pluginPaths');
		Configure::write('vendorPaths', array($f[1] . 'debug_kit' . DS . 'tests' . DS . 'test_app' . DS . 'vendors' . DS));
		$this->Controller->components = array(
			'DebugKit.Toolbar' => array(
				'panels' => array('test'),
			)
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		$this->assertTrue(isset($this->Controller->Toolbar->panels['test']));
		$this->assertTrue(is_a($this->Controller->Toolbar->panels['test'], 'TestPanel'));
	}

/**
 * test initialize
 *
 * @return void
 * @access public
 **/
	function testInitialize() {
		$this->Controller->components = array('DebugKit.Toolbar');
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);

		$this->assertFalse(empty($this->Controller->Toolbar->panels));

		$timers = DebugKitDebugger::getTimers();
		$this->assertTrue(isset($timers['componentInit']));
	}
	
/**
 * test startup
 *
 * @return void
 **/
	function testStartup() {
		$this->Controller->components = array(
			'DebugKit.Toolbar' => array(
				'panels' => array('MockDebug')
			)
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Toolbar->panels['MockDebug']->expectOnce('startup');
		$this->Controller->Toolbar->startup($this->Controller);

		$this->assertEqual(count($this->Controller->Toolbar->panels), 1);
		$this->assertTrue(isset($this->Controller->helpers['DebugKit.Toolbar']));

		$this->assertEqual($this->Controller->helpers['DebugKit.Toolbar']['output'], 'DebugKit.HtmlToolbar');
		$this->assertEqual($this->Controller->helpers['DebugKit.Toolbar']['cacheConfig'], 'debug_kit');
		$this->assertTrue(isset($this->Controller->helpers['DebugKit.Toolbar']['cacheKey']));

		$timers = DebugKitDebugger::getTimers();
		$this->assertTrue(isset($timers['controllerAction']));
	}
/**
 * Test that cache config generation works.
 *
 * @return void
 **/
	function testCacheConfigGeneration() {
		$this->Controller->components = array('DebugKit.Toolbar');
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		
		$results = Cache::config('debug_kit');
		$this->assertTrue(is_array($results));
	}
/**
 * test state saving of toolbar
 *
 * @return void
 **/
	function testStateSaving() {
		$this->Controller->components = array('DebugKit.Toolbar');
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$configName = 'debug_kit';
		$this->Controller->Toolbar->cacheKey = 'toolbar_history';

		$this->Controller->Component->startup($this->Controller);
		$this->Controller->set('test', 'testing');
		$this->Controller->Component->beforeRender($this->Controller);
		
		$result = Cache::read('toolbar_history', $configName);
		$this->assertEqual($result[0]['variables']['content']['test'], 'testing');
		Cache::delete('toolbar_history', $configName);
	}
/**
 * Test Before Render callback
 *
 * @return void
 **/
	function testBeforeRender() {
		$this->Controller->components = array(
			'DebugKit.Toolbar' => array(
				'panels' => array('MockDebug', 'session')
			)
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Toolbar->panels['MockDebug']->expectOnce('beforeRender');
		$this->Controller->Toolbar->beforeRender($this->Controller);
		
		$this->assertTrue(isset($this->Controller->viewVars['debugToolbarPanels']));
		$vars = $this->Controller->viewVars['debugToolbarPanels'];

		$expected = array(
			'plugin' => 'debug_kit',
			'elementName' => 'session_panel',
			'content' => $this->Controller->Session->read(),
			'disableTimer' => true,
		);
		$this->assertEqual($expected, $vars['session']);
	}

/**
 * test alternate javascript library use
 *
 * @return void
 **/
	function testAlternateJavascript() {
		$this->Controller->components = array(
			'DebugKit.Toolbar'
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		$this->Controller->Component->beforeRender($this->Controller);
		$this->assertTrue(isset($this->Controller->viewVars['debugToolbarJavascript']));
		$expected = array(
			'behavior' => '/debug_kit/js/js_debug_toolbar',
		);
		$this->assertEqual($this->Controller->viewVars['debugToolbarJavascript'], $expected);
		
		$this->Controller->components = array(
			'DebugKit.Toolbar' => array(
				'javascript' => 'jquery',
			),
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		$this->Controller->Component->beforeRender($this->Controller);
		$this->assertTrue(isset($this->Controller->viewVars['debugToolbarJavascript']));
		$expected = array(
			'behavior' => '/debug_kit/js/jquery_debug_toolbar.js',
		);
		$this->assertEqual($this->Controller->viewVars['debugToolbarJavascript'], $expected);


		$this->Controller->components = array(
			'DebugKit.Toolbar' => array(
				'javascript' => false
			)
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		$this->Controller->Component->beforeRender($this->Controller);
		$this->assertTrue(isset($this->Controller->viewVars['debugToolbarJavascript']));
		$expected = array();
		$this->assertEqual($this->Controller->viewVars['debugToolbarJavascript'], $expected);
		

		$this->Controller->components = array(
			'DebugKit.Toolbar' => array(
				'javascript' => array('my_library'),
			),
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		$this->Controller->Component->beforeRender($this->Controller);
		$this->assertTrue(isset($this->Controller->viewVars['debugToolbarJavascript']));
		$expected = array(
			'behavior' => 'my_library_debug_toolbar'
		);
		$this->assertEqual($this->Controller->viewVars['debugToolbarJavascript'], $expected);

		$this->Controller->components = array(
			'DebugKit.Toolbar' => array(
				'javascript' => array('/my/path/to/file')
			),
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		$this->Controller->Component->beforeRender($this->Controller);
		$this->assertTrue(isset($this->Controller->viewVars['debugToolbarJavascript']));
		$expected = array(
			'behavior' => '/my/path/to/file',
		);
		$this->assertEqual($this->Controller->viewVars['debugToolbarJavascript'], $expected);

		$this->Controller->components = array(
			'DebugKit.Toolbar' => array(
				'javascript' => '/js/custom_behavior',
			),
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		$this->Controller->Component->beforeRender($this->Controller);
		$this->assertTrue(isset($this->Controller->viewVars['debugToolbarJavascript']));
		$expected = array(
			'behavior' => '/js/custom_behavior',
		);
		$this->assertEqual($this->Controller->viewVars['debugToolbarJavascript'], $expected);
	}
/**
 * Test alternate javascript existing in the plugin.
 *
 * @return void
 **/
	function testExistingAlterateJavascript() {
		$filename = APP . 'plugins' . DS . 'debug_kit' . DS . 'vendors' . DS . 'js' . DS . 'test_alternate_debug_toolbar.js';
		$this->skipIf(!is_writable(dirname($filename)), 'Skipping existing javascript test, debug_kit/vendors/js must be writable');
		
		@touch($filename);
		$this->Controller->components = array(
			'DebugKit.Toolbar' => array(
				'javascript' => 'test_alternate',
			),
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		$this->Controller->Component->beforeRender($this->Controller);
		$this->assertTrue(isset($this->Controller->viewVars['debugToolbarJavascript']));
		$expected = array(
			'behavior' => '/debug_kit/js/test_alternate_debug_toolbar.js',
		);
		$this->assertEqual($this->Controller->viewVars['debugToolbarJavascript'], $expected);
		@unlink($filename);
	}
/**
 * test the Log panel log reading.
 *
 * @return void
 **/
	function testLogPanel() {
		usleep(20);
		$this->Controller->log('This is a log I made this request');
		$this->Controller->log('This is the second  log I made this request');
		$this->Controller->log('This time in the debug log!', LOG_DEBUG);
		
		$this->Controller->components = array(
			'DebugKit.Toolbar' => array(
				'panels' => array('log', 'session')
			)
		);
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		$this->Controller->Component->beforeRender($this->Controller);
		$result = $this->Controller->viewVars['debugToolbarPanels']['log'];
		
		$this->assertEqual(count($result['content']), 2);
		$this->assertEqual(count($result['content']['error.log']), 4);
		$this->assertEqual(count($result['content']['debug.log']), 2);
		
		$this->assertEqual(trim($result['content']['debug.log'][1]), 'Debug: This time in the debug log!');
		$this->assertEqual(trim($result['content']['error.log'][1]), 'Error: This is a log I made this request');
	}
/**
 * Test that history state urls set prefix = null and admin = null so generated urls do not 
 * adopt these params.
 *
 * @return void
 **/
	function testHistoryUrlGenerationWithPrefixes() {
		$configName = 'debug_kit';
		$this->Controller->params = array(
			'controller' => 'posts',
			'action' => 'edit',
			'admin' => 1,
			'prefix' => 'admin',
			'plugin' => 'cms',
			'url' => array(
				'url' => '/admin/cms/posts/edit/'
			)
		);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Toolbar->cacheKey = 'url_test';
		$this->Controller->Component->beforeRender($this->Controller);
		
		$result = $this->Controller->Toolbar->panels['history']->beforeRender($this->Controller);
		$expected = array(
			'plugin' => 'debug_kit', 'controller' => 'toolbar_access', 'action' => 'history_state',
			0 => 1, 'admin' => false
		);
		$this->assertEqual($result[0]['url'], $expected);
		Cache::delete('url_test', $configName);
	}
/**
 * Test that the FireCake toolbar is used on AJAX requests
 *
 * @return void
 **/
	function testAjaxToolbar() {
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
		$this->Controller->components = array('DebugKit.Toolbar');
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
		$this->Controller->Component->startup($this->Controller);
		$this->assertEqual($this->Controller->helpers['DebugKit.Toolbar']['output'], 'DebugKit.FirePhpToolbar');
	}
/**
 * Test that the toolbar does not interfere with requestAction
 *
 * @return void
 **/
	function testNoRequestActionInterference() {
		$f = Configure::read('pluginPaths');
		$testapp = $f[1] . 'debug_kit' . DS . 'tests' . DS . 'test_app' . DS . 'controllers' . DS;
		array_unshift($f, $testapp);
		Configure::write('controllerPaths', $f);

		$plugins = Configure::read('pluginPaths');
		$views = Configure::read('viewPaths');
		$testapp = $plugins[1] . 'debug_kit' . DS . 'tests' . DS . 'test_app' . DS . 'views' . DS;
		array_unshift($views, $testapp);
		Configure::write('viewPaths', $views);

		$result = $this->Controller->requestAction('/debug_kit_test/request_action_return', array('return'));
		$this->assertEqual($result, 'I am some value from requestAction.');

		$result = $this->Controller->requestAction('/debug_kit_test/request_action_render', array('return'));
		$this->assertEqual($result, 'I have been rendered.');
	}

}
?>