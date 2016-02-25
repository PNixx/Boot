<?php
/**
 * @author: nixx
 * Date: 24.04.13
 * Time: 14:45
 * @version 1.0
 */
abstract class Boot_Abstract_Controller {
	use Boot_TraitController, \Boot\LibraryTrait;

	/**
	 * Переменная для передачи по вьюху
	 * @var Boot_View
	 */
	public $view = null;

	/**
	 * Обработчик событий до выполнения экшена
	 * @var array
	 * [
	 *   function => [
	 *     only   => [actions],
	 *     except => [actions]
	 *   ]
	 * ]
	 */
	public $before_action = [];

	//Конструктор
	public function __construct() {
		$this->view = new stdClass();

		//Добавляем пути для подключения вьюх
		$object = new ReflectionObject($this);
		$views = realpath(pathinfo($object->getFileName(), PATHINFO_DIRNAME) . '/../views');
		Boot_View::register_include_path(APPLICATION_PATH . '/views');
		Boot_View::register_include_path($views);
		Boot_View::register_include_path($views . '/' . Boot_Routes::getInstance()->getControllerName());
	}

	//Инициализация
	public function init() {

	}

	/**
	 * Был аяксовый запрос или нет
	 * @return bool
	 */
	public function isAjax() {
		if( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Выполнить экшен
	 * @param $action
	 * @throws Exception
	 * @return void
	 */
	public function _action($action) {
		Boot_Controller::getInstance()->_action($action, $this);
	}

	/**
	 * Рендер вьюхи
	 * @param $name
	 * @return string
	 */
	public function _render($name) {
		return Boot_View::getInstance()->render($name);
	}

	/**
	 * @static
	 * @return null
	 */
	public function getViewName() {
		return Boot_Controller::getViewName();
	}

	/**
	 * Http авторизация
	 * @param $login
	 * @param $passw
	 */
	public function httpAuth($login, $passw) {
		if( isset($_SERVER['PHP_AUTH_USER']) == false || isset($_SERVER['PHP_AUTH_PW']) == false || $_SERVER['PHP_AUTH_USER'] != $login || $_SERVER['PHP_AUTH_PW'] != $passw ) {
			$_SESSION['http_logged'] = 1;
			header('WWW-Authenticate: Basic realm="realm"');
			header('HTTP/1.0 401 Unauthorized');
			die("В доступе отказано.");
		}
	}
}