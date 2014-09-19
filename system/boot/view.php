<?php
class Boot_View {

	/**
	 * Инстанс
	 * @var Boot_View
	 */
	static private $_instance = null;

	/**
	 * @var Model_User
	 */
	public $me = null;

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot_View
	 */
	static public function getInstance() {

		if( !(self::$_instance instanceof Boot_View) ) {
			self::$_instance = new Boot_View();
		}
		return self::$_instance;
	}

	private $file_dir_name = null;

	/**
	 * Хранимые данные для вьюхи
	 * @var null
	 */
	private $_view = null;

	/**
	 * Конструктор
	 */
	public function __construct() {

		//Инициализируем бибилиотеки
		foreach(Boot::getInstance()->library->getLibraries() as $library) {
			$library->init($this);
		}
	}

	/**
	 * Получаем данные для вьюхи
	 * @param $name
	 * @return mixed
	 */
	public function __get($name) {
		if( isset(Boot_Controller::getInstance()->view->$name) ) {
			return Boot_Controller::getInstance()->view->$name;
		} else {
			return false;
		}
	}

	/**
	 * Устанавливаем параметры
	 * @param $name
	 * @param $value
	 * @return void
	 */
	public function __set($name, $value) {
		$this->$name = $value;
		Boot_Controller::getInstance()->view->$name = $value;
	}

	/**
	 * Стартуем
	 * @throws Exception
	 * @return string
	 */
	public function run() {
		$mktime = Boot::mktime();

		if( Boot_Controller::getModule() ) {
			$this->file_dir_name = APPLICATION_PATH . "/views/" . Boot_Controller::getModule() . "/" . Boot_Controller::getController() . "/" . Boot_Controller::getViewName() . ".phtml";
		} else {
			$this->file_dir_name = APPLICATION_PATH . "/views/" . Boot_Controller::getController() . "/" . Boot_Controller::getViewName() . ".phtml";
		}

		//Проверяем наличие шаблона
		if( file_exists($this->file_dir_name) == false ) {
			throw new Exception('View "' . $this->file_dir_name . '" not exist');
		}

		ob_start();
		require_once($this->file_dir_name);
		$html = ob_get_contents();
		ob_end_clean();

		//Debug
		Boot::getInstance()->debug("  Rendered " . str_replace(APPLICATION_PATH . "/", "", $this->file_dir_name) . " (" . Boot::check_time($mktime) . "ms)");

		return $html;
	}

	/**
	 * @param $name
	 * @param null $params
	 * @throws Exception
	 * @return string
	 */
	public function view($name, $params = null) {
		$mktime = Boot::mktime();

		$file = APPLICATION_PATH . "/views/" . $name . ".phtml";

		if( $params ) {
			extract($params);
			unset($params);
		}

		if( file_exists($file) ) {
			ob_start();

			require($file);
			$html = ob_get_contents();
			ob_end_clean();

			//Debug
			Boot::getInstance()->debug("  Rendered views/{$name}.phtml (" . Boot::check_time($mktime) . "ms)");

			return $html;
		} else {
			throw new Exception("Views \"$name.phtml\" not found.");
		}
	}

	/**
	 * @param $name
	 * @return string
	 * @throws Exception
	 */
	public function render($name) {
		$mktime = Boot::mktime();

		$file = APPLICATION_PATH . "/views/" . $name . ".phtml";

		if( file_exists($file) ) {
			ob_start();
			require($file);
			$html = ob_get_contents();
			ob_end_clean();

			//Debug
			Boot::getInstance()->debug("  Rendered views/{$name}.phtml (" . Boot::check_time($mktime) . "ms)");

			return $html;
		} else {
			throw new Exception("Views \"$name.phtml\" not found.");
		}
	}

	/**
	 * Get flash value by name
	 * @param $name
	 * @return bool|string
	 */
	public function flash($name) {
		return Boot_Flash::get($name);
	}

	/**
	 * @param string|array $names
	 * @return string
	 */
	public function js($names) {
		$html = "";

		//Проходим по списку
		foreach((array)$names as $name) {
			$html .= "<script src=\"/js/{$name}.js" . (file_exists(APPLICATION_ROOT . '/public/js/' . $name . '.js') ? "?" . filemtime(APPLICATION_ROOT . '/public/js/' . $name . '.js') : "") . "\" type=\"text/javascript\"></script>";
		}
		return $html;
	}

	/**
	 * @param string|array $names
	 * @return string
	 */
	public function css($names) {
		$html = "";

		//Проходим по списку
		foreach((array)$names as $name) {
			$html .= "<link href=\"/css/{$name}.css" . (file_exists(APPLICATION_ROOT . '/public/css/' . $name . '.css') ? "?" . filemtime(APPLICATION_ROOT . '/public/css/' . $name . '.css') : "") . "\" media=\"screen\" rel=\"stylesheet\" type=\"text/css\">";
		}
		return $html;
	}

	/**
	 * Генерация JS
	 * @param $name
	 * @return string
	 */
	public function javascript_include_tag($name) {
		$js = new Boot_Assets("js", false);

		//Если в режиме разработчика
		if( APPLICATION_ENV == 'development' ) {
			$js->read_asset_file(APPLICATION_PATH . "/assets/" . $name);
			return $js->__toString();
		}
		return $js->readfile_production($name);
	}

	/**
	 * Генерация CSS
	 * @param $name
	 * @return string
	 */
	public function stylesheet_link_tag($name) {
		$css = new Boot_Assets("css", false);

		//Если в режиме разработчика
		if( APPLICATION_ENV == 'development' ) {
			$css->read_asset_file(APPLICATION_PATH . "/assets/" . $name);
			return $css->__toString();
		}
		return $css->readfile_production($name);
	}
}