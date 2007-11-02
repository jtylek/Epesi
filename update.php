<?php
/**
 * Epesi core updater.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

include_once('include/misc.php');

function themeup(){
	$data_dir = 'data/Base_Theme/templates/default/';
	$content = scandir($data_dir);
	foreach ($content as $name){
		if ($name == '.' || $name == '..') continue;
		if (!is_dir($data_dir.'/'.$name)){
			unlink($data_dir.'/'.$name);
		}
	}	
	$ret = DB::Execute('SELECT * FROM modules');
	while($row = $ret->FetchRow()) {
		$directory = 'modules/'.str_replace('_','/',$row[0]).'/theme_'.$row['version'];
		if (!is_dir($directory)) $directory = 'modules/'.str_replace('_','/',$row[0]).'/theme';
		$mod_name = $row[0];
		$data_dir = 'data/Base_Theme/templates/default/';
		if (!is_dir($directory)) continue;
		$content = scandir($directory);
		foreach ($content as $name){
			if($name == '.' || $name == '..' || strpos('.php',$name)!==false) continue;
			if (!is_dir($directory.'/'.$name)){
				copy($directory.'/'.$name,$data_dir.$mod_name.'__'.$name);
			}
		}
	}
}

$versions = array('0.8.5','0.8.6','0.8.7','0.8.8','0.8.9','0.8.10','0.8.11','0.9.0');

/******************* 0.8.11 to 0.9.0 **********************/
function mod_cmp($a, $b){
	return strlen($b['name']) - strlen($a['name']);
}
function update_from_0_9_0_to_0_9_1() {

}
function update_from_0_8_11_to_0_9_0() {
	DB::DropTable('session');
	DB::CreateTable('session',"name C(32) NOTNULL," .
			"expires I NOTNULL DEFAULT 0, data X",array('constraints'=>', PRIMARY KEY(name)'));
	DB::CreateTable('session_client',"session_name C(32) NOTNULL, client_id I2," .
			"data X2",array('constraints'=>', FOREIGN KEY(session_name) REFERENCES session(name)'));
	DB::CreateTable('history',"session_name C(32) NOTNULL, page_id I, client_id I2," .
			"data X2",array('constraints'=>', FOREIGN KEY(session_name) REFERENCES session(name)'));

	// Reducing Base_Admin version number
	DB::Execute('UPDATE modules SET version=0 WHERE name=\'Base_Admin\'');
	
	// flat datadir structure
	$tmp_dir = rtrim(sys_get_temp_dir(),'\\/').'/';
	recursive_copy('data',$tmp_dir.'data_old');
	unlink($tmp_dir.'data_old/config.php');
	recursive_copy('data',$tmp_dir.'data_tmp');
	unlink($tmp_dir.'data_tmp/config.php');
	$content = scandir('data/');
	foreach($content as $name) {
		if($name == '.' || $name == '..' || $name == 'config.php') continue;
		recursive_rmdir('data/'.$name);
	}
	$mod = DB::GetAll('SELECT name FROM modules');
	usort($mod,'mod_cmp');
	foreach($mod as $row) {
		$name = str_replace('_','/',$row['name']);
		recursive_copy($tmp_dir.'data_tmp/'.$name,'data/'.$row['name']);
		recursive_rmdir($tmp_dir.'data_tmp/'.$name);
	}
	recursive_rmdir($tmp_dir.'data_tmp');
	// adjusting quickaccess
	$qa = DB::GetOne('SELECT name FROM modules WHERE name=\'Base_Menu_QuickAccess\'');
	if ($qa=='Base_Menu_QuickAccess') {
		$ret = DB::Execute('SELECT * FROM quick_access');
		while ($row = $ret->FetchRow()) {
			$entry = md5(str_replace(array('href=','&menu_click=1'),array('box_main_module=',''),$row['link']).'#qa_sep#'.str_replace(' ','_',$row['label']));
			DB::Execute('INSERT INTO base_user_settings VALUES (%s,%s,%s,%d)',array($row['user_login_id'], 'Base_Menu_QuickAccess', $entry, 1));
		}
	}
	DB::DropTable('quick_access');
	// installing Tooltip
	if(!DB::GetOne('SELECT name FROM modules WHERE name=\'Utils_Tooltip\''))
		DB::Execute('INSERT INTO modules VALUES(%s,%d,%d)',array('Utils_Tooltip',0,0));
	// installing About
	if(!DB::GetOne('SELECT name FROM modules WHERE name=\'Base_About\''))
		DB::Execute('INSERT INTO modules VALUES(%s,%d,%d)',array('Base_About',0,0));
	// installing Dashboard
	if(!DB::GetOne('SELECT name FROM modules WHERE name=\'Base_Dashboard\''))
		DB::Execute('INSERT INTO modules VALUES(%s,%d,%d)',array('Base_Dashboard',0,0));
	DB::CreateTable('base_dashboard_tabs','
		id I4 AUTO KEY,
		user_login_id I4,
		name C(64) NOTNULL,
		pos I2',
		array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(ID)'));
	DB::CreateTable('base_dashboard_default_tabs','
		id I4 AUTO KEY,
		name C(64) NOTNULL,
		pos I2');
	DB::Execute('INSERT INTO base_dashboard_default_tabs(name,pos) VALUES(\'Default\',0)');
	DB::CreateTable('base_dashboard_applets','
		id I4 AUTO KEY,
		user_login_id I4,
		module_name C(128),
		col I2 DEFAULT 0,
		pos I2 DEFAULT 0,
		tab I4',
		array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(ID), FOREIGN KEY (tab) REFERENCES base_dashboard_tabs(ID)'));
	DB::CreateTable('base_dashboard_settings','
		applet_id I4,
		name C(32) NOTNULL,
		value X NOTNULL',
		array('constraints'=>', FOREIGN KEY (applet_id) REFERENCES base_dashboard_applets(ID), PRIMARY KEY(applet_id,name)'));
	DB::CreateTable('base_dashboard_default_applets','
		id I4 AUTO KEY,
		module_name C(128),
		col I2 DEFAULT 0,
		pos I2 DEFAULT 0,
		tab I4',
		array('constraints'=>', FOREIGN KEY (tab) REFERENCES base_dashboard_default_tabs(ID)'));
	DB::CreateTable('base_dashboard_default_settings','
		applet_id I4,
		name C(32) NOTNULL,
		value X NOTNULL',
		array('constraints'=>', FOREIGN KEY (applet_id) REFERENCES base_dashboard_default_applets(ID), PRIMARY KEY(applet_id,name)'));
	$this->add_aco('set default dashboard','Super administrator');
	
	DB::CreateTable('base_user_settings_admin_defaults','
		module C(128) NOTNULL,
		variable C(32) NOTNULL,
		value X NOTNULL',
		array('constraints'=>', PRIMARY KEY(module,variable), FOREIGN KEY (module) REFERENCES modules(name)'));
	Acl::add_aco('Base_User_Settings','set defaults','Super administrator');

	Variable::set('preload_image_cache_default',true);
	Variable::set('preload_image_cache_selected',true);

	$ret = DB::dict()->AddColumnSQL('user_password','autologin_id C(32)');
	DB::Execute($ret[0]);
	
	// flush
	themeup();
}
/****************** 0.8.6 to 0.8.7 **********************/
function update_from_0_8_6_to_0_8_7() {
}
/****************** 0.8.5 to 0.8.6 **********************/
function update_from_0_8_5_to_0_8_6() {
}

//=========================================================================

try {
$cur_ver = Variable::get('version');
} catch(Exception $s) {
$cur_ver = '0.8.5';
}
$go=false;
$last_ver = '';
foreach($versions as $v) {
	$x = str_replace('.','_',$v);
	if($go) {
		if(is_callable('update_from_'.$last_ver.'_to_'.$x)) {
//			print('Update from '.$last_ver.' to '.$x.'<br>');
			call_user_func('update_from_'.$last_ver.'_to_'.$x);
		}
	}
	if($v==$cur_ver) $go=true;
	if($v==EPESI_VERSION) $go=false;
	$last_ver = $x;
}
Variable::set('version',EPESI_VERSION);
?>
