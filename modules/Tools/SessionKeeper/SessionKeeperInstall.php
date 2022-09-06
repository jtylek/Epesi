<?php
/**
 * Keeps epesi user logged in.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-tools
 * @subpackage SessionKeeper
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_SessionKeeperInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("0.9");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Base_User_SettingsInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Keep epesi logged in.',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return __('EPESI Core');
	}
	
}

?>