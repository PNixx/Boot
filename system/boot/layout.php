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
	 * @param $content
	 * @return void
	 */
	public function run(&$content) {

		//Путь к файлу
		$__path = $this->file;

		//Счетчик времени
		$time = Boot::mktime();

		//Оборачиваем все в функцию
		$view = function ($params, $content) use ($__path) {

			//Извлекаем переменные
			if( !empty($params) ) {
				extract((array)$params);
			}

			//Запускаем отладчик
			ob_start();

			//Подключаем файл
			require $__path;

			//Выполняем сценарий
			$html = ob_get_contents();
			ob_end_clean();

			//Возвращаем данные
			return $html;
		};

		//Выполняем функцию
		$html = $view((array)Boot_Controller::getInstance()->view, $content);

		//Debug
		Boot::getInstance()->debug("  Rendered " . str_replace(APPLICATION_PATH . "/", "", $__path) . " (" . Boot::check_time($time) . "ms)");
		echo $html;
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

	/**
	 * @param string|array $names
	 * @return string
	 */
	public function js($names) {
		return Boot_View::getInstance()->js($names);
	}

	/**
	 * @param string|array $names
	 * @return string
	 */
	public function css($names) {
		return Boot_View::getInstance()->css($names);
	}

	/**
	 * Генерация JS
	 * @param $name
	 * @return string
	 */
	public function javascript_include_tag($name) {
		return Boot_View::getInstance()->javascript_include_tag($name);
	}

	/**
	 * Генерация CSS
	 * @param $name
	 * @return string
	 */
	public function stylesheet_link_tag($name) {
		return Boot_View::getInstance()->stylesheet_link_tag($name);
	}
}