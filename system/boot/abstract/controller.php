<?php
namespace Boot\Abstracts {

use Boot;
use Boot\Core\View;
use Boot\Routes;

abstract class Controller {

	use \Boot_TraitController, Boot\LibraryTrait, Boot\UrlTrait;

	/**
	 * Переменная для передачи по вьюху
	 * @var array
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
		$this->view = new \stdClass();

		//Добавляем пути для подключения вьюх
		$this->include_path(new \ReflectionObject($this));
	}

	//Добавлят пути
	private function include_path(\ReflectionClass $object, $top = true) {
		$views = realpath(explode('controller', $object->getFileName())[0]);

		if( $views ) {
			View::register_include_path($views . '/views', $top);
			View::register_include_path($views . '/views/' . Routes::getControllerViewsPath($object->getName()), $top);
		}

		//Если есть родительский класс
		$parent = $object->getParentClass();
		if( $parent && $parent->name != Controller::class ) {
			$this->include_path($parent, false);
		}
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
	 * @throws \Exception
	 * @return void
	 */
	public function _action($action) {
		\Boot_Controller::getInstance()->_action($action, $this);
	}

	/**
	 * Рендер вьюхи
	 * @param $name
	 * @return string
	 */
	public function _render($name) {
		return (new View(View::include_path($name)))->html();
	}

	/**
	 * @static
	 * @return null
	 */
	public function getViewName() {
		return \Boot_Controller::getViewName();
	}

	/**
	 * Установка текущего шаблона
	 * @param $layout
	 */
	public function layout($layout) {
		Boot::getInstance()->layout($layout);
	}

	/**
	 * Http авторизация
	 * @param $login
	 * @param $password
	 */
	public function httpAuth($login, $password) {
		if( isset($_SERVER['PHP_AUTH_USER']) == false || isset($_SERVER['PHP_AUTH_PW']) == false || $_SERVER['PHP_AUTH_USER'] != $login || $_SERVER['PHP_AUTH_PW'] != $password ) {
			$_SESSION['http_logged'] = 1;
			header('WWW-Authenticate: Basic realm="realm"');
			header('HTTP/1.0 401 Unauthorized');
			die("В доступе отказано.");
		}
	}
}

}

//todo удалить
namespace {

	/**
	 * @deprecated
	 * @see Boot\Abstracts\Controller
	 */
	abstract class Boot_Abstract_Controller extends Boot\Abstracts\Controller {}
}