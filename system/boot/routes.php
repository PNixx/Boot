<?php
namespace Boot;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Map;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
use GuzzleHttp\Psr7\ServerRequest;

/**
 * User: Odintsov S.A.
 * Date: 30.08.12
 * Time: 14:52
 */
class Routes {

	/**
	 * @var RouterContainer
	 */
	private $routes;

	/**
	 * @var Map
	 */
	private $map;

	/**
	 * @var Route
	 */
	private $current;

	/**
	 * Инстанс
	 * @var Routes
	 */
	static private $_instance = null;

	/**
	 * Получаем инстанс
	 * @static
	 * @return Routes
	 */
	static public function getInstance() {

		if( !(self::$_instance instanceof Routes) ) {
			self::$_instance = new Routes();
			require_once APPLICATION_PATH . '/config/routes.php';
		}
		return self::$_instance;
	}

	public function __construct() {
		if( !file_exists(APPLICATION_PATH . '/config/routes.php') ) {
			throw new \Boot_Exception("Файл config/routes.php не найден");
		}
		$this->routes = new RouterContainer();
		$this->map = $this->routes->getMap();

		//Добавляем роуты
		$this->addRoute('get', ['boot/mailer' => 'Boot\Controllers\Mailer#index'])->wildcard('file');
		$assets = $this->addRoute('get', ['assets/{path}' => 'Boot\Controllers\Assets#index'])->tokens(['path' => '.+\.([^/]+)']);
		$assets->handler(array_merge($assets->handler, ['log' => false]));
	}

	/**
	 * Определяет текущи роут по полученному адресу
	 * @throws \Boot_Exception
	 */
	public function fetchQuery() {
		$request = ServerRequest::fromGlobals();
		if( strtoupper($request->getMethod()) == 'POST' && !empty($request->getParsedBody()['_method']) ) {
			$request = $request->withMethod(strtoupper($request->getParsedBody()['_method']));
		}

		$this->current = $this->routes->getMatcher()->match($request);

		//Если ничего не найдено, кидаем ошибку
		if( $this->current == null ) {
			throw new \Boot_Exception('Страница не найдена<br><pre>' . implode('<br>', self::all_routes()) . '</pre>', 404);
		}

		foreach( $this->current->attributes as $key => $value ) {
			if( !is_int($key) ) {
				\Boot_Controller::getInstance()->setParam($key, $value);
			}
		}
	}

	/**
	 * Включен ли лог для контроллера
	 * @return bool
	 */
	public function isLogEnable() {
		return !isset($this->current->handler['log']) || $this->current->handler['log'];
	}

	/**
	 * Получение имени класса контроллера
	 * @return string
	 */
	public function getController() {
		return $this->current->handler['controller'];
	}

	/**
	 * Получение чистого имени контроллера
	 * @return string
	 */
	public function getControllerName() {
		$controller = $this->getController();
		$controller = stristr($controller, '\\') ? $controller : ucfirst(str_replace('/', '_', $controller)) . \Boot_Controller::POSTFIX;
		return preg_replace('/^(.*?)([^\\\]+)$/', '$2', $controller);
	}

	/**
	 * Получаем путь до контроллера
	 * @param $name
	 * @return string
	 */
	static public function getControllerViewsPath($name) {
		if( preg_match('/^Boot\\\(.+?)\\\Controller\\\(.+?)$/', $name, $match) ) {
			return strtolower(str_replace('\\', '/', $match[1])) . '/' . strtolower($match[2]);
		}
		return strtolower(str_replace('_', '/', preg_replace('/Controller$/', '', $name)));
	}

	/**
	 * Получение имени экшена
	 * @return string
	 */
	public function getAction() {
		return $this->current->handler['action'];
	}

	/**
	 * @param $name
	 */
	public function setAction($name) {
		$this->current->handler(array_merge($this->current->handler, ['action' => $name]));
	}

	/**
	 * @return string|null
	 */
	public function getCurrentPath() {
		if( $this->current ) {
			$this->current->path;
		}
		return null;
	}

	/**
	 * @param $path
	 * @return string
	 */
	private function clearPathAttributes($path) {
		return trim(preg_replace('/{[^}]+}/', '', $path), '/');
	}

	/**
	 * Получает строку пути инстанса из массива или строки
	 * @param string|array $path
	 * @return string
	 */
	private function getNamespace($path) {
		if( is_array($path) ) {
			$namespace = $path[current(array_keys($path))];
		} else {
			$namespace = $path;
		}
		return $this->clearPathAttributes($namespace);
	}

	/**
	 * @param string|array $path
	 * @return string
	 */
	private function getPathName($path) {
		return str_replace(['/', '#'], '_', $this->clearPathAttributes($this->getPath($path)));
	}

	/**
	 * @param $path
	 * @return string
	 */
	private function getPath($path) {
		if( is_array( $path) ) {
			return '/' . current(array_keys($path));
		}
		return '/' . preg_replace('/#/', '/', $path);
	}

	/**
	 * @param string|array $method
	 * @param string       $path
	 * @param array        ...$args
	 * @return Route
	 */
	private function addRoute($method, $path, ...$args) {

		/** @var Route $route */
		$route = $this->map->$method($this->getPathName($path) ?: 'root', preg_replace('/\/?{([^}]+)\*}/', '', $this->getPath($path)));
		$route->extras($args);

		//Parse route wildcard, example /photos/{path*}
		if( preg_match('/{([^}]+)\*}/', $this->getPath($path), $match) ) {
			$route->wildcard($match[1]);
		}

		$namespace = $this->getNamespace($path);

		if( preg_match('/^[^#]*#(.+?)$/', $namespace, $match) ) {
			$action = $match[1];
		} else {
			$action = 'index';
		}
		$route->handler([
			'controller' => preg_replace('/^([^#]+)#.*?$/', '$1', $namespace),
			'action'     => $action,
		]);

		return $route;
	}

	/**
	 * Добавление ресурса в маршруты
	 * @param       $path
	 * @param array $args
	 * @throws \Boot_Exception
	 */
	private function addResource($path, $args) {

		//Дефолтные экшены
		$actions = [['get', 'index'], ['get', 'new'], ['post', 'create'], ['get', 'show', 'member'], ['get', 'edit', 'member'], ['delete', 'destroy', 'member'], ['post', 'save', 'member']];

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

		//Добавляем маршруты
		$this->map->attach($this->getPathName($path), $this->getPath($path), function($map) use ($actions, $path) {
			/** @var Map $map */

			//Проходим по циклу экшеном
			foreach( $actions as $action ) {
				$method = $action[0];
				$type = empty($action[2]) ? 'collection' : $action[2];

				if( $action[1] == 'index' ) {
					$route = $map->get('', '');
				} else {
					$route = $map->$method('_' . $this->clearPathAttributes($action[1]), ($type == 'member' ? '/{id}/' : '/') . $action[1]);
				}
				$route->handler([
					'controller' => $path,
					'action' => $this->clearPathAttributes($action[1]),
				]);
			}
		});
	}

	/**
	 * @param string|array $route
	 * @param array        ...$args
	 */
	static public function get($route, ...$args) {
		self::getInstance()->addRoute('get', $route, $args);
	}

	/**
	 * @param string|array $route
	 * @param array        ...$args
	 */
	static public function post($route, ...$args) {
		self::getInstance()->addRoute('post', $route, $args);
	}

	/**
	 * @param       $route
	 * @param array ...$args
	 */
	static public function delete($route, ...$args) {
		self::getInstance()->addRoute('delete', $route, $args);
	}

	/**
	 * @param string|array $route
	 * @param array        $via
	 * @param array        ...$args
	 */
	static public function match($route, array $via, ...$args) {
		self::getInstance()->addRoute([$via], $route, $args);
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
	 * @throws \RouteException
	 */
	static public function root($route) {
		if( !is_string($route) ) {
			throw new \RouteException('Root может быть только строковым');
		}
		self::getInstance()->addRoute('get', ['' => $route]);
	}

	/**
	 * Генерирует путь из маршрутов
	 * @param string $name
	 * @param array  $args
	 * @return string
	 * @throws \RouteException
	 */
	static public function make_path($name, $args = []) {

		$params = [];
		$link_params = [];
		foreach( $args as $v ) {
			if( is_array($v) ) {
				$params = array_merge($params, $v);
			} else {
				$link_params[] = $v;
			}
		}

		//Строим параметры запроса
		$params = http_build_query($params);
		$query = ($params ? '?' . $params : '');

		//Если рут
		if( $name == 'root' ) {
			return '/' . $query;
		}

		try {
			$route = self::$_instance->map->getRoute($name);
			if( $route ) {

				if( preg_match_all('/{([^}]+)}/', $route->path, $matches) ) {
					//Если не указаны обязательные параметры
					if( count($link_params) != count($matches[1]) ) {
						throw new \RouteException('Please set required params: ' . implode(', ', $matches[1]));
					}
					$link_params = array_combine($matches[1], $link_params);
				}

				return self::$_instance->routes->getGenerator()->generate($name, $link_params) . $query;
			}

			//Если ничего не нашли, кидаем ошибку
			throw new \RouteException($name . '_path not found.' . PHP_EOL . print_r(self::all_routes(), true));
		} catch (RouteNotFound $e) {
			throw new \RouteException($name . '_path not found.<br><pre>' . implode('<br>', self::all_routes()) . '</pre>');
		}
	}

	static private function all_routes() {
		if( \Boot::getInstance()->isDevelopment() ) {
			return array_map(function(Route $r) {
				return '[' . implode(', ', $r->allows) . '] ' . $r->name . ' -> ' . implode('#', $r->handler);
			}, self::$_instance->map->getRoutes());
		}
		return [];
	}
}
