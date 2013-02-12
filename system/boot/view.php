<?php
class Boot_View {

	/**
	 * Переводчик
	 * @var translate
	 */
	public $translate = null;

	/**
	 * Инстанс
	 * @var Boot_View
	 */
	static private $_instance = null;

	/**
	 * @var Model_User_Row
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
			self::$_instance->translate = &Boot::getInstance()->translate;
			self::$_instance->me = &Boot_Auth::getInstance()->getAuth();

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
		Boot_Controller::getInstance()->view->$name = $value;
	}

	/**
	 * Стартуем
	 * @throws Exception
	 * @return string
	 */
	public function run() {

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

		return $html;
	}

	/**
	 * @param $name
	 * @param null $params
	 * @throws Exception
	 * @return string
	 */
	public function view($name, $params = null) {

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

		$file = APPLICATION_PATH . "/views/" . $name . ".phtml";

		if( file_exists($file) ) {
			ob_start();
			require($file);
			$html = ob_get_contents();
			ob_end_clean();

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
}