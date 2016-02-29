<?php
class Boot_Controller {

	/**
	 * @deprecated
	 */
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

	/**
	 * Зарегистрированные функции
	 * @var array
	 */
	private static $call_functions = [];

	//Конструктор
	public function __construct() {
		$this->view = new stdClass();
	}

	/**
	 * Пробует вызвать зарегистрированную функцию
	 * @param $name
	 * @param $arguments
	 * @return mixed
	 */
	public function __call($name, $arguments) {
		if( array_key_exists($name, self::$call_functions) ) {
			$f = self::$call_functions[$name];
			return $f::$name($arguments);
		}

		throw new BadMethodCallException('Call to undefined function ' . $name . '()');
	}

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot_Controller
	 */
	static public function getInstance() {

		if( !(self::$_instance instanceof Boot_Controller) ) {
			self::$_instance = new Boot_Controller();
			self::$_instance->initialize();
		}
		return self::$_instance;
	}

	/**
	 * Обработка запроса, разбитие на: module/controller/action
	 * @throws Boot_Exception
	 */
	private function getQuery() {

		$path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $_SERVER['REQUEST_URI'];
		if( preg_match('/^(\/[^\/\.]+)\.+$/', $path_info, $match) && count($match) > 1 ) {
			$this->_redirect($match[1]);
		}

		//Если страница пытается загрузкить из асетов файл
		//todo вынести в отдельный экшен
		if( APPLICATION_ENV == 'development' && preg_match('/^\/assets\/(css|js)\/.*?\.(css|js)$/', $path_info, $matches) ) {
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
			$path_info = preg_replace('/^\/assets\/(css|js)\/?/', '', $path_info);

			$assets = new Boot_Assets($matches[1], true, false);
			$assets->setCompress(false);

			//Если расширение css и файл найден
			if( $assets->full_path($path_info) ) {
				echo $assets->readfile($path_info);
			} elseif( $matches[1] == 'css' ) {

				//Если файл не найден, пробуем найти scss
				$filename = pathinfo($path_info, PATHINFO_FILENAME);
				$scss = $assets->normalizePath(pathinfo($path_info, PATHINFO_DIRNAME) . '/' . $filename . '.scss');

				//Если файл существует
				if( $assets->full_path($scss) ) {
					echo $assets->readfile($scss);
				} else {
					throw new Boot_Exception('File ' . $path_info . ' not found', 404);
				}
			} else {
				throw new Boot_Exception('File ' . $path_info . ' not found', 404);
			}
			exit;
		}

		//Для шрифтов
		if( APPLICATION_ENV == 'development' && preg_match('/^\/assets\/.*?\.(eot|svg|ttf|woff|woff2)$/', $path_info, $matches) ) {
			$path_info = preg_replace('/^\/assets\//', '', $path_info);
			header("Content-Type: font/" . pathinfo($path_info, PATHINFO_EXTENSION));

			//Если файл не найден, устанавливаем шрифты
			$file = Boot_Assets::find_font_path($path_info);
			if( $file ) {
				echo readfile($file);
			} else {
				throw new Boot_Exception('Font ' . $path_info . ' not found', 404);
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

		//Если пост пустой, но передается файл
		if( isset($_FILES[$name]) ) {
			return new Boot_Params([]);
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
	 * Фильтрует параметры
	 * @param array $params
	 */
	private function filterParams(array &$params) {
		foreach( $params as $key => &$param ) {
			if( is_array($param) ) {
				$this->filterParams($param);
			} elseif( stristr($key, 'password') ) {
				$param = '[FILTERED]';
			}
		}
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

		//todo для поддержки новых версий
		if( preg_match('/^Boot\\\([^\\\]+)\\\Controller\\\/', Boot_Routes::getInstance()->getController()) && class_exists(Boot_Routes::getInstance()->getController()) ) {
			return Boot_Routes::getInstance()->getController();
		}

		//todo для поддержки старых версий
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
	protected function initialize() {

		//Получаем данные запроса
		$this->getQuery();

		//Загружаем контроллер
		$this->includeController();

		$Cname = $this->getControllerName();
		$Aname = $this->getActionName();

		//Debug
		Boot::getInstance()->debug("Processing by " . $Cname . "#" . self::getAction());
		$params = $this->getParams();
		$this->filterParams($params);
		Boot::getInstance()->debug("  Parameters: " . json_encode($params));

		//Если найден такой класс
		if( class_exists($Cname) == false ) {
			throw new Exception('Controller "' . $Cname . '" not exist', 404);
		}

		/**
		 * Инициализируем
		 * @var Boot_Abstract_Controller $controller
		 */
		$controller = new $Cname();

		//Инициализируем бибилиотеки
		foreach(Boot::getInstance()->library->getLibraries() as $library) {
			$library->init($controller);
		}

		if( $this->hasParam("lang") ) {
			$lang = $this->getParam("lang");

			Boot\Library\Translate::getInstance()->setLocale($lang);

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

		//Запускаем обработку
		$this->run_before_actions($controller->before_action, $Aname);

		//Стартуем экшен
		$controller->$Aname();

		//Сохраняем данные для передачи во вьюху
		if( array_key_exists('view', $controller) ) {
			$this->view = &$controller->view;
		}
	}

	/**
	 * Запускает обработку до выполнения экшена
	 * @param $before_action
	 * @param $action
	 */
	private function run_before_actions($before_action, $action) {
		foreach( $before_action as $func => $filter ) {
			if( !empty($filter['except']) ) {
				$except = is_array($filter['except']) ? $filter['except'] : [$filter['except']];
			} else {
				$except = [];
			}
			if( !empty($filter['only']) ) {
				$only = is_array($filter['only']) ? $filter['only'] : [$filter['only']];
			} else {
				$only = [];
			}
			$only = array_diff($only, $except);
			if( (!$only || in_array($action, $only)) && (!$except || !in_array($action, $except)) ) {
				$this->$func($action);
			}
		}
	}

	/**
	 * @param string $name     имя функции для быстрого вызова
	 * @param string $class    имя исполняемого класса
	 */
	static public function register_call($name, $class) {
		self::$call_functions[$name] = $class;
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
	 * Выполнить экшен
	 * @param $action
	 * @param Boot_Abstract_Controller $controller
	 * @throws Exception
	 * @return void
	 */
	public function _action($action, Boot_Abstract_Controller $controller) {
		Boot_Routes::getInstance()->setAction($action);
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