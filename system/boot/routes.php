<?php
/**
 * User: Odintsov S.A.
 * Date: 30.08.12
 * Time: 14:52
 * todo Переделать
 */

class Boot_Routes {

	/**
	 * Список роутов
	 * @var array
	 */
	private $_routes = [];

	/**
	 * Текущий найденый роут
	 * @var string
	 */
	private $_current;

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
			require_once APPLICATION_PATH . '/config/routes.php';
		}
		return self::$_instance;
	}

	private function __construct() {
		if( !file_exists(APPLICATION_PATH . '/config/routes.php') ) {
			throw new Boot_Exception("Файл config/routes.php не найден");
		}
	}

	/**
	 * Определяет текущи роут по полученному адресу
	 * @param $query
	 * @throws Boot_Exception
	 */
	public function fetchQuery($query) {

		if( empty($query) ) {
			$this->_current = 'root';
		} else {

			//Проходим по списку роутов
			foreach( array_keys($this->_routes) as $key ) {
				if( $this->_routes[$key]['method'] == strtolower($_SERVER['REQUEST_METHOD']) && preg_match('/^' . preg_replace('/\\\:(\w[\w\d]*)/', '(?<$1>[^\/]+)', preg_quote($key, '/')) . '$/', $query, $match) ) {
					$this->_current = $key;

					//Добавляем параметры
					foreach( $match as $k => $v ) {
						if( !is_int($k) ) {
							Boot_Controller::getInstance()->setParam($k, $v);
						}
					}
					break;
				}
			}
		}

		//Если ничего не найдено, кидаем ошибку
		if( $this->_current == null ) {
			throw new Boot_Exception("Страница не найдена", 404);
		}
	}

	/**
	 * Получение имени класса контроллера
	 * @return string
	 */
	public function getController() {
		return preg_replace('/#(\w[\w\d]*)$/', '', $this->_routes[$this->_current]['path']);
	}

	/**
	 * Получение имени экшена
	 * @return string
	 */
	public function getAction() {
		return $this->_routes[$this->_current]['action'];
	}

	/**
	 * @param string       $method
	 * @param string|array $route
	 * @param array        ...$args
	 * @throws RouteException
	 */
	private function addRoute($method, $route, ...$args) {

		//Стандартный экшен
		$action = 'index';

		//Если передали массив
		if( is_array($route) ) {

			//Если ничего не указано
			if( count($route) == 0 ) {
				throw new RouteException('Не указан роут');
			}

			//Пробуем найти экшен
			if( preg_match('/#(\w[\w\d]*)$/', $route[array_keys($route)[0]], $match) ) {
				$action = $match[1];
			}

		} else {
			$path = null;

			//Достаем экшен из пути
			if( preg_match('/(\w[\w\d]*)$/', $route, $match) ) {
				$action = $match[1];
			} elseif( preg_match('/:(\w[\w\d]*)$/', $route, $match) ) {
				$action = 'show';
			}

			//Строим автоматически
			$route = [$route => preg_replace('/(\/:\w[\w\d]*|\/\w[\w\d]*$)/', '', $route) . '#' . $action];
		}

		//Добавляем роут
		$request = array_keys($route)[0];
		$this->_routes[$request] = [
			'method' => $method,
			'path'   => $route[$request],
			'args'   => $args,
			'action' => $action,
		];
	}

	/**
	 * @param       $route
	 * @param array ...$args
	 * @throws RouteException
	 */
	static public function get($route, ...$args) {
		self::getInstance()->addRoute('get', $route, $args);
	}

	/**
	 * @param       $route
	 * @param array ...$args
	 * @throws RouteException
	 */
	static public function post($route, ...$args) {
		self::getInstance()->addRoute('post', $route, $args);
	}

	/**
	 * @param string $route
	 * @throws RouteException
	 */
	static public function root($route) {
		if( !is_string($route) ) {
			throw new RouteException('Root может быть только строковым');
		}
		self::getInstance()->addRoute('get', ['root' => $route]);
	}
}
