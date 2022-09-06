<?php
/**
 * Flash clock
 * (clock taken from http://www.kirupa.com/developer/actionscript/clock.htm)
 *
 * @author pbukowski@telaxus.com
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage clock
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_ClockInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this -> get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Base_DashboardInstall::module_name(),'version'=>0),
			array('name'=>Base_RegionalSettingsInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Flash clock',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
        return array('package'=>__('EPESI Core'), 'option'=>__('Additional applets'));
	}
	
}

?>