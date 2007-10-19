<?php
/**
 * User_Settings class.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage user-settings
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_SettingsCommon extends ModuleCommon {
	public static function menu(){
		if (!Acl::is_user()) return array();
		$modules = array(); 
		foreach(ModuleManager::$modules as $name=>$obj) {
			if(method_exists($obj['name'].'Common', 'user_settings')) {
				$menu = call_user_func(array($obj['name'].'Common','user_settings'));
				if(!is_array($menu)) continue;
				foreach($menu as $k=>$v)
					if (!is_string($v)) $modules[$k] = array('settings_branch'=>$k);
					else $modules[$k] = array('box_main_module'=>$obj['name'],'box_main_function'=>$v);
			}
		}
		return array('My settings'=>array_merge(array('__weight__'=>10,'__submenu__'=>1,'Control panel'=>array('__weight__'=>-10,'settings_branch'=>'__NONE__'),'__split__'=>1),$modules));
	}

	/**
	 * Returns default setting.
	 * 
	 * @param string module name
	 * @param string variable name
	 * @return mixed variable value
	 */	
	public static function get_default($module,$name){
		$module = str_replace('/','_',$module);
		static $variables;
		if (isset($variables[$module.'__'.$name]))
			return $variables[$module.'__'.$name];
		$module = str_replace('/','_',$module);
		if(method_exists($module.'Common', 'user_settings')) {
			$menu = call_user_func(array($module.'Common','user_settings'));
			if(is_array($menu))
				foreach($menu as $v)
					foreach($v as $v2)
						if ($v2['type']!='static' && $v2['type']!='header' && $v2['name']==$name) {
							$variables[$module.'__'.$name] = $v2['default'];
							return $v2['default'];
						}
			return null;//trigger_error('There is no such variable as '.$name.' in module '.$module,E_USER_ERROR);
		} else {
			trigger_error('There is no such module as '.$module,E_USER_ERROR);
		}
	}

	/**
	 * Returns user setting.
	 * If user is logged in, returns user prefered setting,
	 * otherwise returns default value.
	 * 
	 * @param string module name
	 * @param string variable name
	 * @return mixed user value
	 */	
	public static function get($module,$name){
		if (!Acl::is_user()) return null;
		$module = str_replace('/','_',$module);
		static $variables;
		if (isset($variables[$module.'__'.$name]))
			return $variables[$module.'__'.$name];
		$val = null;
		$module = str_replace('/','_',$module);
		$val = DB::GetOne('SELECT value FROM base_user_settings WHERE user_login_id=%d AND module=%s AND variable=%s',array(Base_UserCommon::get_my_user_id(),$module,$name));
		if ($val===false) {
			$val = self::get_default($module,$name);
		}
		$variables[$module.'__'.$name] = $val;
		return $val;
	}

	/**
	 * Sets user setting to given value for currently logged in user.
	 * Returns false if no user is logged in.
	 * 
	 * @param string module name
	 * @param string variable name
	 * @param mixed value
	 * @return bool true on success, false otherwise
	 */	
	public static function save($module,$name,$value){
		if (!Acl::is_user()) return false;
		if ($value === null) $value = 0;
		$module = str_replace('/','_',$module);
		$def = self::get_default($module,$name);
		if (!isset($def)) return false;
		if ($value==$def) {
			DB::Execute('DELETE FROM base_user_settings WHERE user_login_id=%d AND module=%s AND variable=%s',array(Base_UserCommon::get_my_user_id(),$module,$name));
		} else {
			$val = DB::GetOne('SELECT value FROM base_user_settings WHERE user_login_id=%d AND module=%s AND variable=%s',array(Base_UserCommon::get_my_user_id(),$module,$name));
			if ($val === false)
				DB::Execute('INSERT INTO base_user_settings VALUES (%d,%s,%s,%s)',array(Base_UserCommon::get_my_user_id(),$module,$name,$value));
			else
				DB::Execute('UPDATE base_user_settings SET value=%s WHERE user_login_id=%d AND module=%s AND variable=%s',array($value,Base_UserCommon::get_my_user_id(),$module,$name));
		}
		return true;
	}
}

?>