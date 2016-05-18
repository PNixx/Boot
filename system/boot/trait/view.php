<?php
/**
 * Date: 11.03.16
 * Time: 20:38
 * @author  Sergey Odintsov <nixx.dj@gmail.com>
 */
namespace Boot;

use Boot\Core\View;

trait ViewTrait {

	/**
	 * Получаем данные для вьюхи
	 * @param $name
	 * @return string|bool
	 */
	public function __get($name) {
		if( isset(\Boot_Controller::getInstance()->view->$name) ) {
			return \Boot_Controller::getInstance()->view->$name;
		} else {
			return false;
		}
	}

	/**
	 * Устанавливаем параметры
	 * @param $name
	 * @param $value
	 * @return void
	 */
	public function __set($name, $value) {
		$this->$name = $value;
		\Boot_Controller::getInstance()->view->$name = $value;
	}

	/**
	 * Рендерит шаблон, используя только полученные данные
	 * @param string     $name
	 * @param array|null $params
	 * @return string
	 * @throws \Boot_Exception
	 */
	public function partial($name, $params = []) {
		return (new View(View::include_path($name), $params))->html();
	}

	/**
	 * Рендерит шаблон, используя данные переданные из контроллера
	 * @param $name
	 * @return string
	 * @throws \Boot_Exception
	 */
	public function render($name) {
		return (new View(View::include_path($name), (array) \Boot_Controller::getInstance()->view))->html();
	}

	/**
	 * HTML minify
	 * @param $buffer
	 * @return mixed
	 */
	protected static function html_min($buffer) {

		$search = array(
			'/\>[^\S ]+/su',  // strip whitespaces after tags, except space
			'/[^\S ]+\</su',  // strip whitespaces before tags, except space
			'/(\s)+/su'       // shorten multiple whitespace sequences
		);

		$replace = array(
			'>',
			'<',
			'\\1'
		);

		return preg_replace($search, $replace, $buffer);
	}
}