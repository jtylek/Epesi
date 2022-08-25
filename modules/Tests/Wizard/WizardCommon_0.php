<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2022 Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage wizard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_WizardCommon extends ModuleCommon {
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'Wizard'=>array()));
	}
}

?>
