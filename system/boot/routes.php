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

		//Проходим по списку роутов
		foreach( $this->_routes as $route ) {
			if( $route['method'] == strtolower($_SERVER['REQUEST_METHOD']) && preg_match('/^' . preg_replace('/\\\:(\w[\w\d]*)/', '(?<$1>[^\/]+)', preg_quote($route['request'], '/')) . '$/', $query, $match) ) {
				$this->_current = $route;

				//Добавляем параметры
				foreach( $match as $k => $v ) {
					if( !is_int($k) ) {
						Boot_Controller::getInstance()->setParam($k, $v);
					}
				}
				break;
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
		return preg_replace('/#(\w[\w\d]*)$/', '', $this->_current['path']);
	}

	/**
	 * Получение чистого имени контроллера
	 * @return string
	 */
	public function getControllerName() {
		return strtolower(preg_replace('/^(.*?)([^\\\]+)$/', '$2', $this->getController()));
	}

	/**
	 * Получение имени экшена
	 * @return string
	 */
	public function getAction() {
		return $this->_current['action'];
	}

	/**
	 * Возращает текущий путь
	 * @return string
	 */
	public function getCurrentPath() {
		return '/' . $this->_current['request'];
	}

	/**
	 * Устанавливаем новое значение экшена
	 * @param $name
	 */
	public function setAction($name) {
		$this->_current['action'] = $name;
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
			if( preg_match('/\/(\w[\w\d]*)$/', $route, $match) ) {
				$action = $match[1];
			} elseif( preg_match('/:(\w[\w\d]*)$/', $route, $match) ) {
				$action = 'show';
			}

			//Строим автоматически
			$route = [$route => preg_replace('/(\/:\w[\w\d]*|\/\w[\w\d]*$)/', '', $route) . '#' . $action];
		}

		//Добавляем роут
		$request = array_keys($route)[0];
		$this->_routes[] = [
			'request' => $request,
			'method'  => $method,
			'path'    => $route[$request],
			'args'    => $args,
			'action'  => $action,
		];
	}

	/**
	 * Добавление ресурса в маршруты
	 * @param       $name
	 * @param array $args
	 * @throws Boot_Exception
	 */
	private function addResource($name, $args) {

		if( is_array($name) ) {
			$controller = $name[array_keys($name)[0]];
			$name = array_keys($name)[0];
		} else {
			$controller = $name;
		}

		//Дефолтные экшены
		$actions = [['get', 'index'], ['get', 'show', 'member'], ['get', 'edit', 'member'], ['post', 'delete', 'member'], ['post', 'create', 'member'], ['post', 'update', 'member']];

		//Если указаны исключающие
		if( !empty($args['except']) ) {

			//Если указан не массив
			if( !is_array($args['except']) ) {
				$args['except'] = [$args['except']];
			}
			$actions = array_filter($actions, function($v) use ($args) {
				return !in_array($v[1], $args['except']);
			});
		}

		//Если указаны только включающие
		if( !empty($args['only']) ) {

			//Если указан не массив
			if( !is_array($args['only']) ) {
				$args['only'] = [$args['only']];
			}
			$actions = array_filter($actions, function($v) use ($args) {
				return in_array($v[1], $args['only']);
			});
		}

		//Если указаны дополнительные экшены
		if( !empty($args['actions']) ) {
			$actions = array_merge($actions, $args['actions']);
		}

		//Проходим по циклу экшеном
		foreach( $actions as $action ) {
			$method = $action[0];
			$type = empty($action[2]) ? 'collection' : $action[2];
			if( $action[1] == 'index' ) {
				self::$method([$name => $controller . '#' . $action[1]]);
			} else {
				self::$method([$name . ($type == 'member' ? '/:id/' : '/') . $action[1] => $controller . '#' . $action[1]]);
			}
		}
	}

	/**
	 * @param string|array $route
	 * @param array        ...$args
	 * @throws RouteException
	 */
	static public function get($route, ...$args) {
		self::getInstance()->addRoute('get', $route, $args);
	}

	/**
	 * @param string|array $route
	 * @param array        ...$args
	 * @throws RouteException
	 */
	static public function post($route, ...$args) {
		self::getInstance()->addRoute('post', $route, $args);
	}

	/**
	 * @param string|array $route
	 * @param array        $via
	 * @param array        ...$args
	 * @throws RouteException
	 */
	static public function match($route, array $via, ...$args) {
		foreach( $via as $v ) {
			self::getInstance()->addRoute($v, $route, $args);
		}
	}

	/**
	 * @param string|array $name
	 * @param array        $args
	 */
	static public function resource($name, $args = []) {
		self::getInstance()->addResource($name, $args);
	}

	/**
	 * @param string $route
	 * @throws RouteException
	 */
	static public function root($route) {
		if( !is_string($route) ) {
			throw new RouteException('Root может быть только строковым');
		}
		self::getInstance()->addRoute('get', ['' => $route]);
	}

	/**
	 * Генерирует путь из маршрутов
	 * @param string $name
	 * @param array  $args
	 * @return string
	 * @throws RouteException
	 */
	static public function make_path($name, $args = []) {

		//Строим параметры запроса
		$query = ($args ? '?' . http_build_query($args) : '');

		//Если рут
		if( $name == 'root' ) {
			return '/' . $query;
		}

		//Проходим по списку маршрутов
		foreach( self::getInstance()->_routes as $route) {
			$convert = str_replace('/', '_', $route['request']);
			if( $name == $convert ) {
				return '/' . $route['request'] . $query;
			}
		}

		//Если ничего не нашли, кидаем ошибку
		throw new RouteException($name . '_path not found');
	}
}
