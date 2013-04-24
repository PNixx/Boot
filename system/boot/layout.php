<?php
class Boot_Layout {

	/**
	 * @var null
	 */
	public $me = null;

	/**
	 * Инстанс
	 * @var Boot_Layout
	 */
	static private $_instance = null;

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot_Layout
	 */
	static public function getInstance() {

		if( !(self::$_instance instanceof Boot_Layout) ) {
			self::$_instance = new Boot_Layout();
		}
		return self::$_instance;
	}

	private $file = null;

	/**
	 * Конструктор
	 */
	public function __construct() {

		$this->file = APPLICATION_PATH ."/layouts/" . Boot::getInstance()->layout() . ".phtml";

		//Проверяем наличие шаблона
		if( file_exists($this->file) == false ) {
			throw new Exception('Layout "' . $this->file . '" not exist');
		}

		//Инициализируем бибилиотеки
		foreach(Boot::getInstance()->library->getLibraries() as $library) {
			$library->init($this);
		}
	}

	/**
	 * Получаем данные для вьюхи
	 * @param $name
	 * @return string|bool
	 */
	public function __get($name) {
		if( isset(Boot_Controller::getInstance()->view->$name) ) {
			return Boot_Controller::getInstance()->view->$name;
		} else {
			return false;
		}
	}

	/**
	 * Стартуем
	 * @return void
	 */
	public function run(&$content) {

		require_once($this->file);
	}

	/**
	 * Вставить шаблон вьюхи
	 * @param $name
	 * @param null $params
	 * @return string
	 */
	public function view($name, $params = null) {
		return Boot_View::getInstance()->view($name, $params);
	}

	/**
	 * Вставить шаблон вьюхи
	 * @param $name
	 * @return string
	 */
	public function render($name) {
		return Boot_View::getInstance()->render($name);
	}

}