<?php
/**
 * @author: nixx
 * Date: 24.04.13
 * Time: 14:45
 * @version 1.0
 */
abstract class Boot_Abstract_Controller {

	/**
	 * @var Model_User_row|Model_row
	 */
	public $me;

	/**
	 * Переменная для передачи по вьюху
	 * @var Boot_View
	 */
	public $view = null;

	//Конструктор
	public function __construct() {
		$this->view = new stdClass();
	}

	//Инициализация
	public function init() {

	}

	/**
	 * Получить параметр запроса
	 * @param $name
	 * @return null|string
	 */
	public function getParam($name) {
		return Boot_Controller::getInstance()->getParam($name);
	}

	/**
	 * Получение декодированного параметра запроса
	 * @param $name
	 * @return string
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
		return Boot_Controller::getInstance()->hasParam($name);
	}

	/**
	 * Записать сообщение
	 * @param $name
	 * @param $value
	 */
	public function setFlash($name, $value) {
		Boot_Flash::getInstance()->set($name, $value);
	}

	/**
	 * Получить flash сообщение
	 * @param $name
	 * @return bool|string
	 */
	public function getFlash($name) {
		return Boot_Flash::get($name);
	}

	/**
	 * Переключение вьюхи
	 * @param $name
	 */
	public function render($name) {
		Boot_Controller::getInstance()->_render = $name;
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
	 * Редирект
	 * @param $url
	 * @return void
	 */
	public function _redirect($url) {
		Boot_Controller::getInstance()->_redirect($url);
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
	 * Получить имя контроллера
	 * @static
	 * @return
	 */
	public function getController() {
		return Boot_Controller::getController();
	}

	/**
	 * Получить имя экшена
	 * @static
	 * @return
	 */
	public function getAction() {
		return Boot_Controller::getAction();
	}

	/**
	 * @static
	 * @return null
	 */
	public function getViewName() {
		return Boot_Controller::getViewName();
	}

	/**
	 * Получить имя модуля
	 * @static
	 * @return string|bool
	 */
	public function getModule() {
		return Boot_Controller::getModule();
	}
}