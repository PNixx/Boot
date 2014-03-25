<?php
class Boot_Controller {

	const PREFIX = "Controller";

	/**
	 * Параметры запроса
	 * [module | controller | action]
	 * @var null
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
	 * @return void
	 */
	private function getQuery() {

		$path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $_SERVER['REQUEST_URI'];
		if( preg_match("/^(\/[^\/\.]+)\.+$/", $path_info, $match) && count($match) > 1 ) {
			$this->_redirect($match[1]);
		}

		//Получаем строку запроса
		if( $_SERVER['QUERY_STRING'] ) {
			$req = $_SERVER['QUERY_STRING'];

			//Разибиваем данные
			$req = explode('&', $req);
			if( $req ) {
				foreach($req as $r) {

					//Если не определена, идём дальше
					if( !$r ) {
						continue;
					}

					//Разбиваем
					$key = explode('=', $r);

					//Записыываем
					$this->_request[$key[0]] = isset($key[1]) ? $key[1] : null;
				}
			}
		}
		$query = $path_info;
		if( preg_match("/^(.*?)\?/", $path_info, $match) ) {
			$query = $match[1];
		}

		//Созраняем параметры запроса
		$this->_param = Boot_Routes::getInstance()->getParam(substr($query, 1, strlen($query)));
	}

	/**
	 * Получить параметр запроса
	 * @param $name
	 * @return null|string
	 */
	public function getParam($name) {

		//Если есть в get запросе
		if( isset(Boot_Controller::getInstance()->_request[$name]) ) {
			return Boot_Controller::getInstance()->_request[$name];
		}

		//Если есть в post запросе
		if( isset($_POST[$name]) ) {
			return $_POST[$name];
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

		if( isset($this->_param->module) ) {
			$file = APPLICATION_PATH . "/controllers/" . $this->_param->module . "/" . $this->_param->controller . ".php";
		} else {
			$file = APPLICATION_PATH . "/controllers/" . $this->_param->controller . ".php";
		}

		//Проверяем существование файла
		if( is_file($file) == false ) {
			throw new Exception("File {$file} not found", 404);
		}

		//Загружаем файл
		require_once $file;

		if( class_exists((isset($this->_param->module) ? $this->_param->module . "_" : "") . $this->_param->controller . "Controller", false) == false ) {
			throw new Exception($this->_param->controller . "Controller not exists");
		}
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

		$Cname = (isset($this->_param->module) ? $this->_param->module . "_" : "") . $this->_param->controller . self::PREFIX;
		$Aname = $this->_param->action . "Action";

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
	 * @return string|bool
	 */
	static public function getModule() {
		if( isset(self::getInstance()->_param->module) ) {
			return self::getInstance()->_param->module;
		}
		return false;
	}

	/**
	 * Получить имя контроллера
	 * @static
	 * @return
	 */
	static public function getController() {
		return self::getInstance()->_param->controller;
	}

	/**
	 * Получить имя экшена
	 * @static
	 * @return
	 */
	static public function getAction() {
		return self::getInstance()->_param->action;
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