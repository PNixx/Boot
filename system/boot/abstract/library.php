<?php
use Boot\Core\View;

/**
 * @author: nixx
 * Date: 24.04.13
 * Time: 13:56
 * @deprecated 
 */
abstract class Boot_Abstract_Library {

	/**
	 * Ключ для доступа
	 * @var string
	 */
	public $key = 'interface';

	/**
	 * Нужно ли инициализировать библиотеку в системе?
	 * @var bool
	 */
	public static $is_init = true;

	/**
	 * Инициализация библиотеки во вьюхе и контроллере
	 * @param View|Boot_Abstract_Controller $class
	 * @return void
	 */
	public function init(&$class) {
		$class->{$this->key} = Boot::getInstance()->library->{$this->key};
	}
}