<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(Utils_CalendarInstall::module_name());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Utils_CalendarInstall::module_name());
		return true;
	}

	public function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a>, <a href="mailto:pbukowski@telaxus.com">Paul Bukowski</a> (<a href="http://www.telaxus.com">Janusz Tylek</a>)', 'License'=>'MIT', 'Description'=>'Abstract calendar.');
	}

	public function simple_setup() {
		return false;
	}
	
	public function requires($v) {
		return array(
			array('name'=>Utils_TabbedBrowserInstall::module_name(), 'version'=>0),
			array('name'=>Base_RegionalSettingsInstall::module_name(), 'version'=>0),
			array('name'=>Base_ThemeInstall::module_name(), 'version'=>0),
			array('name'=>Base_ActionBarInstall::module_name(), 'version'=>0),
			array('name'=>Base_BoxInstall::module_name(), 'version'=>0),
			array('name'=>Utils_PopupCalendarInstall::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0)
		);
	}
	public function version() {
		return array('1.0');
	}
}

?>
