<?php
use Boot\Abstracts\Controller;
use Boot\Routes;

class Boot_Controller {

	/**
	 * Префик namespace
	 */
	const POSTFIX = "Controller";

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
	 * @var array
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
		}
		return self::$_instance;
	}

	/**
	 * Обработка запроса, разбитие на: module/controller/action
	 * @throws Boot_Exception
	 */
	public function getQuery() {

		$path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $_SERVER['REQUEST_URI'];
		if( preg_match('/^(\/[^\/\.]+)\.+$/', $path_info, $match) && count($match) > 1 ) {
			$this->_redirect($match[1]);
		}

		//Получаем строку запроса
		if( $_SERVER['QUERY_STRING'] ) {
			parse_str($_SERVER['QUERY_STRING'], $this->_request);
		}

		//Сохраняем параметры запроса
		Routes::getInstance()->fetchQuery();
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
		$params = [];

		//Если есть в get запросе
		if( isset(Boot_Controller::getInstance()->_request) ) {
			$params = Boot_Controller::getInstance()->_request;
		}

		//Если есть в post запросе
		if( isset($_POST) ) {
			$params = array_merge($params, array_filter($_POST, function($k) {
				if( $k != '_method' ) {
					return true;
				}
				return false;
			}, ARRAY_FILTER_USE_KEY));
		}

		return $params;
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
		if( !class_exists($this->getClassName()) ) {
			throw new Exception($this->getClassName() . " not exists");
		}
	}

	/**
	 * Получение имени класса контроллера
	 * @return string
	 */
	private function getClassName() {
		return ucfirst(str_replace('/', '_', Routes::getInstance()->getController())) . self::POSTFIX;
	}

	/**
	 * Инициализиуем
	 * @throws Exception
	 * @return void
	 */
	public function initialize() {

		//Загружаем контроллер
		$this->includeController();

		$class_name = $this->getClassName();
		$action_name = $this->getAction() . "Action";

		//Debug
		if( Routes::getInstance()->isLogEnable() ) {
			Boot::getInstance()->debug("Processing by " . $class_name . "#" . $action_name);
			Boot::getInstance()->debug("  Parameters: " . json_encode($this->getParams(), JSON_UNESCAPED_UNICODE));
		}

		//Если найден такой класс
		if( class_exists($class_name) == false ) {
			throw new Exception('Controller "' . $class_name . '" not exist', 404);
		}

		/**
		 * Инициализируем
		 * @var Boot_Abstract_Controller $controller
		 */
		$controller = new $class_name();

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
		if( method_exists($controller, $action_name) == false ) {
			throw new Exception('Action "' . $action_name . '", controller "' . $class_name . '" not exist', 404);
		}

		//Запускаем обработку
		$this->run_before_actions($controller, $action_name);

		//Стартуем экшен
		$controller->$action_name();

		//Сохраняем данные для передачи во вьюху
		$this->view = &$controller->view;
	}

	/**
	 * Запускает обработку до выполнения экшена
	 * @param Controller $controller
	 * @param                          $action
	 * @internal param $before_action
	 */
	private function run_before_actions(Controller $controller, $action) {
		$action = preg_replace('/Action$/', '', $action);
		foreach( $controller->before_action as $func => $filter ) {
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
				if( method_exists($controller, $func) ) {
					$controller->$func();
				} else {
					$this->$func($action);
				}
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
	}

	/**
	 * Получить имя контроллера
	 * @static
	 * @return string
	 */
	static public function getController() {
		$path = explode('/', Routes::getInstance()->getController());
		return end($path);
	}

	/**
	 * Получить имя экшена
	 * @static
	 * @return string
	 */
	static public function getAction() {
		return Routes::getInstance()->getAction();
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
		Routes::getInstance()->setAction($name);
	}

	/**
	 * Выполнить экшен
	 * @param $action
	 * @param Controller $controller
	 * @throws Exception
	 * @return void
	 */
	public function _action($action, Controller $controller) {
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
	 * @deprecated 
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