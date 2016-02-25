<?php

class Boot_View {

	use \Boot\TagTrait, \Boot\UrlTrait, \Boot\LibraryTrait;

	/**
	 * Инстанс
	 * @var Boot_View
	 */
	static private $_instance = null;

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
		return $this->_render(Boot_Controller::getViewName(), (array)Boot_Controller::getInstance()->view);
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
		$__path = null;

		//Проверяем наличие шаблона
		$paths = explode(PATH_SEPARATOR, get_include_path());
		foreach( $paths as $p ) {
			if( file_exists(realpath($p) . '/' . $file . '.phtml') ) {
				$__path = realpath($p) . '/' . $file . '.phtml';
				break;
			}
		}

		//Если не нашли шаблон
		if( $__path == null ) {
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
	 * @param      $name
	 * @param bool $async
	 * @return string
	 * @throws Exception
	 */
	public function javascript_include_tag($name, $async = false) {
		$js = new Boot_Assets("js", false);

		//Если в режиме разработчика
		if( APPLICATION_ENV == 'development' ) {
			$js->read_asset_file(APPLICATION_PATH . "/assets/" . $name);

			return $js->__toString();
		}

		return $js->readfile_production($name, $async);
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

	/**
	 * Регистрация пути для подключения вьюх
	 * @param $path
	 */
	static public function register_include_path($path) {
		if( !in_array($path, explode(PATH_SEPARATOR, get_include_path())) ) {
			set_include_path($path . PATH_SEPARATOR . get_include_path());
		}
	}
}