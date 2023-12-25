<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides interface for module common.
 * @package epesi-base
 * @subpackage module
 */
class ModuleCommon extends ModulePrimitive {
	private static $singleton;
	
	/* backward compatibility code */
	public static final function acl_check() {
		return false;
	}
	
	/**
	 * Singleton.
	 *
	 * @return object
	 */
	public static function Instance($arg=null) {
		if(isset($arg)) {
			self::$singleton = $arg;
		}
		elseif(is_string(self::$singleton)) {			
			$cl = self::$singleton.'Common';
			self::$singleton = new $cl(self::$singleton);
		}
		return self::$singleton;
	}
}
