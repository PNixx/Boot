<?php
/**
 * Date: 19.02.16
 * Time: 20:45
 * @author  Sergey Odintsov <nixx.dj@gmail.com>
 */
namespace Boot;

/**
 * @method *_path()
 * @method *_url()
 */
trait UrlTrait {

	/**
	 * Вызываем функцию
	 * @param $name
	 * @param $arguments
	 * @return mixed
	 * @throws \RouteException
	 */
	public function __call($name, $arguments) {

		//Если запрашиваем путь
		if( preg_match('/^(.*?)_path$/', $name, $match) ) {
			return Routes::make_path($match[1], $arguments);
		}

		//Если запрашиваем ссылку
		if( preg_match('/^(.*?)_url$/', $name, $match) ) {
			return $_SERVER['REQUEST_SCHEME'] . '://' . \Boot::getInstance()->config->host . Routes::make_path($match[1], $arguments);
		}

		//Метод не найден
		throw new \BadMethodCallException('Method ' . $name . '() not found');
	}

	/**
	 * Возвращает путь корня
	 * @param array $arguments
	 * @return string
	 * @throws \RouteException
	 */
	public function root_path($arguments = []) {
		return Routes::make_path('root', $arguments);
	}

	/**
	 * Проверяет, равен ли текущий урл указанному
	 * @return string
	 */
	public function current_path() {
		return Routes::getInstance()->getCurrentPath();
	}

	/**
	 * @return string
	 */
	public function controller_name() {
		return Routes::getInstance()->getControllerName();
	}
}