<?php
class Boot_Controller {

	const PREFIX = "Controller";

	/**
	 * Параметры запроса
	 * [module | controller | action]
	 * @var null|stdClass
	 * @deprecated
	 */
	private $_param = null;

	/**
	 * Параметры запроса
	 * @var null
	 */
	private $_request = null;

	/**
	 * View render
	 * @var null
	 */
	public $_render = null;

	/**
	 * Инстанс
	 * @var Boot_Controller
	 */
	static private $_instance = null;

	/**
	 * Переменная для передачи по вьюху
	 * @var Boot_View
	 */
	public $view = null;

	//Конструктор
	public function __construct() {
		$this->view = new stdClass();
	}

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot_Controller
	 */
	static public function getInstance() {

		if( !(self::$_instance instanceof Boot_Controller) ) {
			self::$_instance = new Boot_Controller();
			self::$_instance->initizlize();
		}
		return self::$_instance;
	}

	/**
	 * Обработка запроса, разбитие на: module/controller/action
	 * @throws Boot_Exception
	 */
	private function getQuery() {

		$path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $_SERVER['REQUEST_URI'];
		if( preg_match("/^(\/[^\/\.]+)\.+$/", $path_info, $match) && count($match) > 1 ) {
			$this->_redirect($match[1]);
		}

		//Если страница пытается загрузкить из асетов файл
		//todo вынести в отдельный экшен
		if( APPLICATION_ENV == 'development' && preg_match("/^\/assets\/(css|js)\/.*?\.(css|js)$/", $path_info, $matches) ) {
			switch( $matches[1] ) {
				case "css":
					header("Content-Type: text/css");
					break;
				case "js":
					header("Content-Type: application/javascript");
					break;
				default:
					throw new Boot_Exception('Unknown file extension');
			}

			//Если расширение css и файл найден
			if( file_exists(APPLICATION_PATH . $path_info) || $matches[1] == 'js' ) {
				echo file_get_contents(APPLICATION_PATH . $path_info);
			} else {

				//Если файл не найден, пробуем найти scss
				$filename = pathinfo(APPLICATION_PATH . $path_info, PATHINFO_FILENAME);
				$scss = pathinfo(APPLICATION_PATH . $path_info, PATHINFO_DIRNAME) . '/' . $filename . '.scss';

				//Если файл существует
				if( file_exists($scss) ) {

					//Компилируем SASS файл
					$sass = new Sass();
					$sass->setStyle(Sass::STYLE_EXPANDED);
					$sass->setIncludePath(APPLICATION_ROOT);
					$sass->setComments(true);
					file_put_contents('/tmp/' . $filename . '.css', $sass->compileFile($scss));

					//Добавляем префиксы
					$result = system('postcss --use autoprefixer -o /tmp/' . $filename . '.out.css /tmp/' . $filename . '.css', $r);
					if( $result ) {
						throw new Boot_Exception($result);
					} else {
						echo file_get_contents('/tmp/' . $filename . '.out.css');
						unlink('/tmp/' . $filename . '.out.css');
						unlink('/tmp/' . $filename . '.css');
					}
//					$autoprefixer = new Autoprefixer(['ff > 2', '> 2%', 'ie 8']);
//					echo $autoprefixer->compile($css);

					//Ruby Sass
//					//Компилируем sass
//					$return = system('sass -l -t expanded --sourcemap=none ' . escapeshellarg($scss) . ' ' . escapeshellarg('/tmp/' . $filename . '.css') . ' 2>&1');
//					if( $return ) {
//						throw new Boot_Exception('sass error: ' . $return);
//					}
//
//					//Добавляем префиксы
//					$return = system('postcss --use autoprefixer /tmp/' . $filename . '.css -o /tmp/' . $filename . '_out.css 2>&1');
//					if( $return ) {
//						throw new Boot_Exception('autoprefixer error: ' . $return);
//					}
//
//					//Выводим данные
//					readfile('/tmp/' . $filename . '_out.css');
				} else {
					throw new Boot_Exception('File ' . $path_info . ' not found', 404);
				}
			}
			exit;
		}

		//Получаем строку запроса
		if( $_SERVER['QUERY_STRING'] ) {
			parse_str($_SERVER['QUERY_STRING'], $this->_request);
		}

		$query = $path_info;
		if( preg_match("/^(.*?)\\?/", $path_info, $match) ) {
			$query = $match[1];
		}

		//Сохраняем параметры запроса
		Boot_Routes::getInstance()->fetchQuery(substr($query, 1, strlen($query)));
	}

	/**
	 * Получить параметр запроса
	 * @param $name
	 * @return null|string|Boot_Params
	 */
	public function getParam($name) {

		//Если есть в get запросе
		if( isset($this->_request[$name]) ) {
			return $this->_request[$name];
		}

		//Если есть в post запросе
		if( isset($_POST[$name]) ) {
			if( is_array($_POST[$name]) ) {
				return new Boot_Params($_POST[$name]);
			}
			return $_POST[$name];
		}

		return false;
	}

	/**
	 * Добавляет параметр в общий массив
	 * @param $key
	 * @param $value
	 */
	public function setParam($key, $value) {
		$this->_request[$key] = $value;
	}

	/**
	 * Получить параметры запроса
	 * @return null|array
	 */
	public function getParams() {

		//Если есть в get запросе
		if( isset(Boot_Controller::getInstance()->_request) ) {
			return Boot_Controller::getInstance()->_request;
		}

		//Если есть в post запросе
		if( isset($_POST) ) {
			return $_POST;
		}

		return false;
	}

	/**
	 * Получение декодированного параметра запроса
	 * @param $name
	 * @return string
	 * @deprecated
	 */
	public function getParamDecode($name) {
		return trim(urldecode($this->getParam($name)));
	}

	/**
	 * Получить параметр запроса
	 * @param $name
	 * @return boolean
	 */
	public function hasParam($name) {

		//Если есть в get запросе
		if( Boot_Controller::getInstance()->_request && array_key_exists($name, Boot_Controller::getInstance()->_request) ) {
			return true;
		}

		//Если есть в post запросе
		if( $_POST && array_key_exists($name, $_POST) ) {
			return true;
		}

		return false;
	}

	/**
	 * Загружем контроллер
	 * @throws Exception
	 * @return void
	 */
	private function includeController() {

		//Проверяем существование класса с его автоподгрузкой
		if( class_exists($this->getControllerName()) == false ) {
			throw new Exception($this->getControllerName() . " not exists");
		}
	}

	/**
	 * Получение имени класса контроллера
	 * @return string
	 */
	private function getControllerName() {
		return implode('_', array_map('ucfirst', explode('/', Boot_Routes::getInstance()->getController()))) . self::PREFIX;
	}

	/**
	 * Получение имени экшена
	 * @return string
	 */
	private function getActionName() {
		return Boot_Routes::getInstance()->getAction() . "Action";
	}

	/**
	 * Инициализиуем
	 * @throws Exception
	 * @return void
	 */
	protected function initizlize() {

		//Получаем данные запроса
		$this->getQuery();

		//Загружаем контроллер
		$this->includeController();

		$Cname = $this->getControllerName();
		$Aname = $this->getActionName();

		//Debug
		Boot::getInstance()->debug("Processing by " . $Cname . "#" . self::getAction());
		Boot::getInstance()->debug("  Parameters: " . json_encode($this->getParams()));

		//Если найден такой класс
		if( class_exists($Cname) == false ) {
			throw new Exception('Controller "' . $Cname . '" not exist', 404);
		}

		//Добавляем пути для подключения файлов вьюх
		set_include_path(implode(PATH_SEPARATOR, [
			APPLICATION_PATH . '/views/' . strtolower(Boot_Routes::getInstance()->getController()),
			APPLICATION_PATH . '/views',
			get_include_path(),
		]));

		/**
		 * Инициализируем
		 * @var Boot_Abstract_Controller $controller
		 */
		$controller = new $Cname();

		//Инициализируем бибилиотеки
		foreach(Boot::getInstance()->library->getLibraries() as $library) {
			$library->init($controller);
		}

		if( class_exists("Boot_Translate_Lib", false) && $this->hasParam("lang") ) {
			$lang = $this->getParam("lang");

			Boot::getInstance()->library->translate->setLocale($lang);

			//Сохраняем в куку
			Boot_Cookie::set("lang", $lang);
		}

		//Если есть функция init
		if( method_exists($controller, 'init') ) {
			$controller->init();
		}

		//Проверяем существование экшена
		if( method_exists($controller, $Aname) == false ) {
			throw new Exception('Action "' . $Aname . '", controller "' . $Cname . '" not exist', 404);
		}

		//Стартуем экшен
		$controller->$Aname();

		//Сохраняем данные для передачи во вьюху
		if( array_key_exists('view', $controller) ) {
			$this->view = &$controller->view;
		}

	}

	/**
	 * Получить имя модуля
	 * @static
	 * @return bool|string
	 * @throws Boot_Exception
	 * @deprecated
	 */
	static public function getModule() {
		throw new Boot_Exception('Method getModule() was deprecated');
		if( isset(self::getInstance()->_param->module) ) {
			return self::getInstance()->_param->module;
		}
		return false;
	}

	/**
	 * Получить имя контроллера
	 * @static
	 * @return string
	 */
	static public function getController() {
		$path = explode('/', Boot_Routes::getInstance()->getController());
		return end($path);
	}

	/**
	 * Получить имя экшена
	 * @static
	 * @return string
	 */
	static public function getAction() {
		return Boot_Routes::getInstance()->getAction();
	}

	/**
	 * @static
	 * @return null
	 */
	static public function getViewName() {
		return self::getInstance()->_render !== null ? self::getInstance()->_render : self::getAction();
	}

	/**
	 * Получить имя экшена
	 * @static
	 * @param $name
	 * @return void
	 */
	static private function setAction($name) {
		self::getInstance()->_param->action = $name;
	}

	/**
	 * Выполнить экшен
	 * @param $action
	 * @param Boot_Abstract_Controller $controller
	 * @throws Exception
	 * @return void
	 */
	public function _action($action, Boot_Abstract_Controller $controller) {
		self::setAction($action);
		$name = $action . "Action";
		if( method_exists($controller, $name) ) {
			$controller->$name();
		} else {
			throw new Exception('Action "' . $action . '", controller "' . self::getController() . '" not exist', 404);
		}
	}

	/**
	 * Редирект
	 * @param $url
	 * @return void
	 */
	public function _redirect($url) {

		//Debug
		Boot::getInstance()->debug("  \x1b[33mRedirect to: " . $url . "\x1b[0m");
		Boot::getInstance()->end();

		Boot_Flash::getInstance()->set("referer", "http://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
		header("Location: " . $url);
		exit;
	}

	public function _render($name) {
		return Boot_View::getInstance()->render($name);
	}

	/**
	 * Был аяксовый запрос или нет
	 * @return bool
	 * @deprecated
	 */
	public function isAjax() {
		if( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Переключение вьюхи
	 * @param $name
	 */
	public function render($name) {
		self::getInstance()->_render = $name;
	}

	/**
	 * Записать сообщение
	 * @param $name
	 * @param $value
	 * @deprecated
	 */
	public function setFlash($name, $value) {
		Boot_Flash::getInstance()->set($name, $value);
	}

	/**
	 * Получить flash сообщение
	 * @param $name
	 * @return bool|string
	 * @deprecated
	 */
	public function getFlash($name) {
		return Boot_Flash::get($name);
	}
}