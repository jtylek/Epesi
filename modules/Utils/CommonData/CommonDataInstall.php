<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage CommonData
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommonDataInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('utils_commondata_tree','
			id I4 AUTO KEY,
			parent_id I4 DEFAULT -1,
			akey C(64) NOTNULL,
			value X,
			readonly I1 DEFAULT 0,
			position I4',
			array('constraints'=>', UNIQUE(parent_id,akey)'));
		if(!$ret){
			print('Unable to create table utils_commondata_tree.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme($this->get_type());
		return $ret;
	}
	
	public function uninstall() {
		global $database;
		$ret = true;
		$ret &= DB::DropTable('utils_commondata_tree');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return $ret;
	}
	public function requires($v) {
		return array(
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Base_ActionBarInstall::module_name(),'version'=>0),
			array('name'=>Base_AdminInstall::module_name(),'version'=>0),
			array('name'=>Utils_ShortcutInstall::module_name(),'version'=>0),
			array('name'=>Utils_GenericBrowserInstall::module_name(),'version'=>0));
	}
    public static function simple_setup() {
		return __('EPESI Core');
    }
}

?>