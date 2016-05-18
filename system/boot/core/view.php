<?php
namespace Boot\Core;

use Boot;
use Boot\Assets;

class View {
	use Boot\TagTrait, Boot\UrlTrait, Boot\LibraryTrait, Boot\ViewTrait;

	/**
	 * Список путей подключения вьюх
	 * @var array
	 */
	static public $include_path = [
		APPLICATION_PATH . '/views',
		APPLICATION_PATH,
	];

	/**
	 * Параметры
	 * @var array
	 */
	private $__params = [];

	/**
	 * Путь к файлу
	 * @var string
	 */
	private $__path;

	/**
	 * @param string $path
	 * @param array  $params
	 * @throws \Boot_Exception
	 */
	public function __construct($path, $params = []) {
		$this->__path = $path;
		$this->__params = $params;

		if( !file_exists($path) ) {
			throw new \Boot_Exception('View "' . str_replace(APPLICATION_PATH . '/', '', $path) . '" not exist');
		}
	}

	/**
	 * Регистрация пути для подключения вьюх
	 * @param      $path
	 * @param bool $top  Добавление вверх
	 */
	static public function register_include_path($path, $top = true) {
		if( (!in_array($path, self::$include_path) || $top) && realpath($path) ) {
			if( $top ) {
				array_unshift(self::$include_path, $path);
				self::$include_path = array_unique(self::$include_path);
			} else {
				array_push(self::$include_path, $path);
			}
		}
	}

	/**
	 * @param $name
	 * @return string
	 * @throws \Boot_Exception
	 */
	static public function include_path($name) {

		//Строим полный путь
		$path = null;

		//Проверяем наличие шаблона
		foreach( self::$include_path as $p ) {
			if( realpath($p . '/' . $name . '.phtml') ) {
				return realpath($p) . '/' . $name . '.phtml';
			}
		}

		//Если не нашли шаблон
		throw new \Boot_Exception('View "' . $name . '.phtml" not exist');
	}

	/**
	 * Get flash value by name
	 * @param $name
	 * @return bool|string
	 */
	public function flash($name) {
		return \Boot_Flash::get($name);
	}

	/**
	 * Генерация JS
	 * @param      $name
	 * @param bool $async
	 * @return string
	 * @throws \Exception
	 */
	public function javascript_include_tag($name, $async = false) {
		$js = new Assets("js", false);

		//Если в режиме разработчика
		if( APPLICATION_ENV == 'development' ) {
			$js->read_asset_file(APPLICATION_PATH . "/assets/" . $name);

			return $js->__toString();
		}

		return $js->readfile_production($name, $async);
	}

	/**
	 * Генерация CSS
	 * @param $name
	 * @return string
	 */
	public function stylesheet_link_tag($name) {
		$css = new Assets("css", false);

		//Если в режиме разработчика
		if( APPLICATION_ENV == 'development' ) {
			$css->read_asset_file(APPLICATION_PATH . "/assets/" . $name);

			return $css->__toString();
		}

		return $css->readfile_production($name);
	}

	/**
	 * @return string
	 */
	public function html() {

		//Счетчик времени
		$time = Boot::mktime();

		//Оборачиваем все в функцию
		$view = function () {

			//Извлекаем переменные
			if( !empty($this->__params) ) {
				extract($this->__params);
			}

			//Запускаем отладчик
			ob_start();

			//Подключаем файл
			require $this->__path;

			//Выполняем сценарий
			$html = ob_get_contents();
			ob_end_clean();

			//Возвращаем данные
			return $html;
		};

		//Выполняем функцию
		$html = $view();

		//Debug
		Boot::getInstance()->debug("  Rendered " . str_replace(APPLICATION_PATH . "/", "", $this->__path) . " (" . Boot::check_time($time) . "ms)");

		return $html;
	}
}