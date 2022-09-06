<?php
/**
 * @author abisaga@telaxus.com
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage monthview
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_MonthViewInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>CRM_CalendarInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Applet showing monthly calendar',
			'Author'=>'abisaga@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
        return array('package'=>__('EPESI Core'), 'option'=>__('Additional applets'));
	}
	
}

?>