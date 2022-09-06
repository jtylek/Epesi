<?php
/**
 * Theme class.
 *
 * Provides module templating.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage theme
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Provides module templating.
 */
class Base_Theme extends Module {
	private static $theme;
	private static $loaded_csses;
	public $links = array();
	private $smarty = null;
	private $lang;

	/**
	 * For internal use only.
	 */
	public function construct() {
		$this->set_inline_display();

		if(!isset(self::$theme))
			self::$theme = Base_ThemeCommon::get_default_template();

		$this->smarty = Base_ThemeCommon::init_smarty();
	}

	public function body() {
	}

	/**
	 * Displays gathered information using a .tpl file and .css file.
	 *
	 * @param string name of theme file to use (without extension)
	 * @param bool if set to true, module name will not be added to the filename and you should then pass a result of get_module_file_name() function as filename
	 */
	public function display($user_template=null,$fullname=false) {
		$this->smarty->assign('__link', $this->links);

		$module_name = $this->parent->get_type();

		Base_ThemeCommon::display_smarty($this->smarty,$module_name,$user_template,$fullname);
	}
	
	public function get_html($user_template=null,$fullname=false) {
		ob_start();
		$this->display($user_template,$fullname);
		return ob_get_clean();
	}

	/**
	 * Returns instance of smarty object which is assigned to this Theme instance.
	 *
	 * @return mixed smarty object
	 */
	public function & get_smarty() {
		return $this->smarty;
	}

	/**
	 * Assigns text to a smarty variable.
	 * Also parses the text looking for a link tag and if one is found,
	 * creates additinal smarty variables holding open, label and close for found tag.
	 *
	 * @param string name for smarty variable
	 * @param string variable contents
	 */
	public function assign($name, $val) {
		$new_links = Base_ThemeCommon::parse_links($name, $val);
		$this->links[$name] = $new_links;
		$this->smarty->assign($name, $val);
	}

	/**
	 * Returns list of available themes.
	 *
	 * @param array list of available themes
	 */
	public static function list_themes() {
		$themes = array();
		$inc = dir(DATA_DIR.'/Base_Theme/templates/');
		while (false != ($entry = $inc->read())) {
			if (is_dir(DATA_DIR.'/Base_Theme/templates/'.$entry) && $entry!='.' && $entry!='..')
				$themes[$entry] = $entry;
		}
		asort($themes);
		return $themes;
	}
}
?>
