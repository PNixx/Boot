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

	/**
	 * Файл вьюхи
	 * @var null|string
	 */
	private $file_dir_name = null;

	/**
	 * Конструктор
	 */
	public function __construct() {

		//Инициализируем бибилиотеки
		foreach( Boot::getInstance()->library->getLibraries() as $library ) {
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

		if( Boot_Controller::getModule() ) {
			$file = Boot_Controller::getModule() . "/" . Boot_Controller::getController() . "/" . Boot_Controller::getViewName();
		} else {
			$file = Boot_Controller::getController() . "/" . Boot_Controller::getViewName();
		}
		$this->file_dir_name = APPLICATION_PATH . "/views/" . $file . ".phtml";

		//Выполняем файл
		return $this->_render($file, (array)Boot_Controller::getInstance()->view);
	}

	/**
	 * Рендерит шаблон, используя только полученные данные
	 * @param string     $name
	 * @param array|null $params
	 * @return string
	 * @throws Boot_Exception
	 * @throws Exception
	 */
	public function view($name, $params = []) {
		return $this->_render($name, $params);
	}

	/**
	 * Рендерит шаблон, используя данные переданные из контроллера
	 * @param string $name
	 * @return string
	 * @throws Exception
	 */
	public function render($name) {
		return $this->_render($name, (array)Boot_Controller::getInstance()->view);
	}

	/**
	 * Рендирит шаблон
	 * @param string $file Путь к файлу от каталога /application/views без расширения
	 * @param array  $params
	 * @throws Boot_Exception
	 */
	private function _render($file, $params = []) {

		//Строим полный путь
		$__path = APPLICATION_PATH . "/views/" . $file . ".phtml";

		//Проверяем наличие шаблона
		if( file_exists($__path) == false ) {
			throw new Boot_Exception('View "' . $file . '.phtml" not exist');
		}

		//Счетчик времени
		$time = Boot::mktime();

		//Оборачиваем все в функцию
		$view = function ($params) use ($__path) {

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
		$html = $view($params);

		//Debug
		Boot::getInstance()->debug("  Rendered " . str_replace(APPLICATION_PATH . "/", "", $__path) . " (" . Boot::check_time($time) . "ms)");

		//Возвращаем результат
		return $html;
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
		foreach( (array)$names as $name ) {
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
		foreach( (array)$names as $name ) {
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