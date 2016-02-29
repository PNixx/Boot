<?php
/**
 * Date: 19.02.16
 * Time: 20:45
 * @author  Sergey Odintsov <nixx.dj@gmail.com>
 */
namespace Boot;

/**
 * Class UrlTrait
 * @package Boot
 * @type \Boot_View
 *
 * @method *_path()
 * @method *_url()
 */
trait UrlTrait {

	/**
	 * Вызываем функцию
	 * @param $name
	 * @param $arguments
	 * @return mixed
	 */
	public function __call($name, ...$arguments) {

		//Если запрашиваем путь
		if( preg_match('/^(.*?)_path$/', $name, $match) ) {
			return \Boot_Routes::make_path($match[1], ...$arguments);
		}

		//Если запрашиваем ссылку
		if( preg_match('/^(.*?)_url$/', $name, $match) ) {
			return $_SERVER['REQUEST_SCHEME'] . '://' . \Boot::getInstance()->config->host . \Boot_Routes::make_path($match[1], ...$arguments);
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
		return \Boot_Routes::make_path('root', $arguments);
	}

	/**
	 * Проверяет, равен ли текущий урл указанному
	 * @return string
	 */
	public function current_path() {
		return \Boot_Routes::getInstance()->getCurrentPath();
	}

	/**
	 * @return string
	 */
	public function controller_name() {
		return \Boot_Routes::getInstance()->getControllerName();
	}
}