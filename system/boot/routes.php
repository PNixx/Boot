<?php
/**
 * User: Odintsov S.A.
 * Date: 30.08.12
 * Time: 14:52
 */

class Boot_Routes {

	/**
	 * Путь казывает на все подключаемые ресурсы модуля
	 */
	const ROUTE_RESOURCE = 1;

	/**
	 * Использует только controller/action
	 */
	const ROUTE_CONTROLLER = 2;

	private $_routes = null;

	/**
	 * Инстанс
	 * @var Boot_Routes
	 */
	static private $_instance = null;

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot_Routes
	 */
	static public function getInstance() {

		if( !(self::$_instance instanceof Boot_Routes) ) {
			self::$_instance = new Boot_Routes();
		}
		return self::$_instance;
	}

	public function __construct() {
		if( file_exists(APPLICATION_PATH . '/config/routes.php') ) {
			require_once APPLICATION_PATH . '/config/routes.php';
			if( isset($routes) ) {
				$this->_routes = $routes;
			} else {
				throw new Boot_Exception("Не корректная структура файла config/routes.php");
			}
		} else {
			throw new Boot_Exception("Файл config/routes.php не найден");
		}
	}

	/**
	 * Разбиваем строку на: module/controller/action
	 * @static
	 * @param $query
	 * @throws Exception
	 * @return object
	 */
	public function getParam($query) {

		//Разбиваем строку на: module/controller/action
		$param = explode('/', $query);
		$return = null;

		if( $query == false || count($param) == 0 ) {
			if( Boot::getInstance()->config->default->module ) {
				return self::getResourceRoute();
			} else {
				return self::getControllerRoute();
			}
		}

		//Если просмотр mailer
		if( APPLICATION_ENV == 'development' && $param[0] == 'boot' && isset($param[1]) && $param[1] == 'mailer' ) {
			Boot_Mail::preview($param);
			exit;
		}

		foreach($this->_routes as $key => $route) {
			if( strtolower($param[0]) == strtolower($key) ) {
				if( $route === self::ROUTE_RESOURCE ) {
					return self::getResourceRoute($param);
				} elseif( $route === self::ROUTE_CONTROLLER ) {
					return self::getControllerRoute($param);
				} elseif( is_array($route) ) {

					//Проходим для поиска модульности module/controller/action
					foreach($route as $k => $r) {
						if( $r === self::ROUTE_RESOURCE ) {
							throw new Boot_Exception("Неправильное направление роутинга, модуль маршрута не может использоваться в модуле");
						} elseif( $r === self::ROUTE_CONTROLLER || isset($param[2]) && $param[2] == $this->_routes[$key][$k] ) {
							return self::getResourceRoute($param);
						} elseif( isset($param[2]) == false && $this->_routes[$key][$k] == "index" ) {
							return self::getControllerRoute($param);
						}
					}
				} else {
					if( isset($param[1]) && $param[1] == $this->_routes[$key] ) {
						return self::getControllerRoute($param);
					}
				}
			}
		}

		//Если по роутингу ничего на нашлось
		throw new Boot_Exception("Страница не найдена", 404);
	}

	static private function getResourceRoute($param = null) {
		return (object)array(
			"module" => isset($param[0]) ? $param[0] : Boot::getInstance()->config->default->page,
			"controller" => isset($param[1]) && $param[1] ? str_replace("-", "", $param[1]) : "index",
			"action" => isset($param[2]) && $param[2] ? $param[2] : "index"
		);
	}

	static private function getControllerRoute($param = null) {
		return (object)array(
			"controller" => isset($param[0]) ? $param[0] : Boot::getInstance()->config->default->page,
			"action" => isset($param[1]) && $param[1] ? $param[1] : "index"
		);
	}
}
