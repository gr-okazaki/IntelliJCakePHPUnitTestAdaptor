<?php
/**
 * Web Access Frontend for TestSuite
 *
 * PHP 5
 *
 * CakePHP(tm) Tests <http://book.cakephp.org/2.0/en/development/testing.html>
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://book.cakephp.org/2.0/en/development/testing.html
 * @package       app.webroot
 * @since         CakePHP(tm) v 1.2.0.4433
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
set_time_limit(0);
ini_set('display_errors', 1);
define('FULL_BASE_URL',"http://localhost");
/**
 * Use the DS to separate the directories in other defines
 */
if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

/**
 * These defines should only be edited if you have cake installed in
 * a directory layout other than the way it is distributed.
 * When using custom settings be sure to use the DS and do not add a trailing DS.
 */

/**
 * The full path to the directory which holds "app", WITHOUT a trailing DS.
 *
 */
if (!defined('ROOT')) {
	define('ROOT', dirname(dirname(__FILE__)));
}

/**
 * The actual directory name for the "app".
 *
 */
if (!defined('APP_DIR')) {
	define('APP_DIR', basename(dirname($_SERVER['SCRIPT_FILENAME'])));
}

/**
 * Editing below this line should not be necessary.
 * Change at your own risk.
 *
 */
if (!defined('WEBROOT_DIR')) {
	define('WEBROOT_DIR', basename(dirname(__FILE__)));
}
if (!defined('WWW_ROOT')) {
	define('WWW_ROOT', dirname(__FILE__) . DS);
}
/**
 * The absolute path to the "Cake" directory, WITHOUT a trailing DS.
 *
 * For ease of development CakePHP uses PHP's include_path. If you
 * need to cannot modify your include_path, you can set this path.
 *
 * Leaving this constant undefined will result in it being defined in Cake/bootstrap.php
 *
 * The following line differs from its sibling
 * /lib/Cake/Console/Templates/skel/webroot/test.php
 */
define('CAKE_CORE_INCLUDE_PATH', WWW_ROOT . DS . 'Vendor' . DS . 'cakephp' . DS . 'cakephp' . DS . 'lib');


if (!defined('CAKE_CORE_INCLUDE_PATH')) {
	if (function_exists('ini_set')) {
		ini_set('include_path', WWW_ROOT . 'lib' . PATH_SEPARATOR . ini_get('include_path'));
	}
	if (!include ('Cake' . DS . 'bootstrap.php')) {
		$failed = true;
	}
} else {
	if (!include (CAKE_CORE_INCLUDE_PATH . DS . 'Cake' . DS . 'bootstrap.php')) {
		$failed = true;
	}
}
if (!empty($failed)) {
	trigger_error("CakePHP core could not be found. Check the value of CAKE_CORE_INCLUDE_PATH in APP/webroot/index.php. It should point to the directory containing your " . DS . "cake core directory and your " . DS . "vendors root directory.", E_USER_ERROR);
}

if (Configure::read('debug') < 1) {
	throw new NotFoundException(__d('cake_dev', 'Debug setting does not allow access to this url.'));
}
Configure::write('Error', array());
Configure::write('Exception', array());

require_once CAKE . 'TestSuite' . DS . 'CakeTestSuiteDispatcher.php';

$helper = $_SERVER['argv'][1];
unset($_SERVER['argv'][1]);

$helper_code = file_get_contents($helper);
$helper_code = preg_replace('/^<\?php(.*)(\?>)?$/s', '$1', $helper_code);
$helper_code = str_replace('IDE_PHPUnit_TextUI_Command::main();', '', $helper_code);

$ret = eval($helper_code);


class CakeIDE_PHPUnit_TextUI_ResultPrinterReporter extends IDE_PHPUnit_TextUI_ResultPrinter {
}

class MyCakeTestSuiteCommand extends CakeTestSuiteCommand
{
	protected function handleArguments(array $argv)
	{
		$argv[] = "--stderr";
		parent::handleArguments($argv);
		if (isset($this->arguments['printer'])) {
			$printer = $this->arguments['printer'];
		} else {
			$printer = null;
		}
		$printer = new IDE_PHPUnit_TextUI_ResultPrinter($printer);
		$this->arguments['printer'] = $printer;
		$this->arguments['listeners'][] = new IDE_PHPUnit_Framework_TestListener($printer);
	}
}

class MyCakeTestSuiteDispatcher extends CakeTestSuiteDispatcher {
	public static function main() {
		$dispatcher = new MyCakeTestSuiteDispatcher();
		$dispatcher->dispatch();
	}
	/**
	 * Runs the actions required by the URL parameters.
	 *
	 * @return void
	 */
	public function dispatch() {
		$this->_checkPHPUnit();
		$this->_parseParams();

		if ($this->params['case']) {
			$value = $this->_myrunTestCase();
		} else {
			$value = $this->_testCaseList();
		}

		$output = ob_get_clean();
		echo $output;
		return $value;
	}
	/**
	 * Runs a test case file.
	 *
	 * @return void
	 */
	protected function _myrunTestCase() {
		$commandArgs = array(
			'case' => $this->params['case'],
			'core' => $this->params['core'],
			'app' => $this->params['app'],
			'plugin' => $this->params['plugin'],
			'codeCoverage' => $this->params['codeCoverage'],
			'showPasses' => !empty($this->params['show_passes']),
			'baseUrl' => $this->_baseUrl,
			'baseDir' => $this->_baseDir,
		);

		$options = array(
			'--filter', $this->params['filter'],
			'--output', $this->params['output'],
			'--fixture', $this->params['fixture']
		);
		restore_error_handler();

		try {
			static::time();
			$command = new MyCakeTestSuiteCommand('CakeTestLoader', $commandArgs);
			$command->run($options);
		} catch (MissingConnectionException $exception) {
			ob_end_clean();
			$baseDir = $this->_baseDir;
			include CAKE . 'TestSuite' . DS . 'templates' . DS . 'missing_connection.php';
			exit();
		}
	}

}

App::uses("TestShell","Console/Command");

/**
 * Class MyTestShell
 */
class MyTestShell extends TestShell
{
	/**
	 * Main entry point to this shell
	 *
	 * @return void
	 */
	public function main() {
		$args = $this->_parseArgs();
		if (empty($args['case'])) {
			return $this->available();
		}

		$options = $this->_runnerOptions();
		restore_error_handler();
		restore_error_handler();

		$testCli = new MyCakeTestSuiteCommand('CakeTestLoader', $args);
		$testCli->run($options);
	}
}

$shell = new MyTestShell();
$shell->initialize();
array_shift($_SERVER['argv']);
$_SERVER['argc'] = count($_SERVER['argv']);
$shell->runCommand("app",$_SERVER['argv']);
