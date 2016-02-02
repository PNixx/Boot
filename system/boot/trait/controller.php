<?php

/**
 * Date: 22.07.15
 * Time: 16:58
 * @author  Sergey Odintsov <sergey.odintsov@mkechinov.ru>
 */
trait Boot_TraitController {

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
	 * Редирект
	 * @param $url
	 * @return void
	 */
	public function _redirect($url) {
		Boot_Controller::getInstance()->_redirect($url);
	}

	/**
	 * Переключение вьюхи
	 * @param $name
	 */
	public function render($name) {
		Boot_Controller::getInstance()->_render = $name;
	}

	/**
	 * Получить параметр запроса
	 * @param $name
	 * @return null|string|Boot_Params
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
	 * Получить имя контроллера
	 * @static
	 * @return string
	 */
	public function getController() {
		return Boot_Controller::getController();
	}

	/**
	 * Получить имя экшена
	 * @static
	 * @return string
	 */
	public function getAction() {
		return Boot_Controller::getAction();
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