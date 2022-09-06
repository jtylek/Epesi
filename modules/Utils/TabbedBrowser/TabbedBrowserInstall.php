<?php
/**
 * TabbedBrowserInstall class.
 * 
 * This class provides initialization data for TabbedBrowser module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage tabbed-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TabbedBrowserInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(Utils_TabbedBrowserInstall::module_name());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Utils_TabbedBrowserInstall::module_name());
		return true;
	}
	
	public function version() {
		return array('1.0');
	}
	public function requires($v) {
		return array(array('name'=>Base_ThemeInstall::module_name(),'version'=>0));
	}
}

?>
