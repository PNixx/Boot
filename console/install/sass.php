<?php
/**
 * Date: 24.08.15
 * Time: 17:11
 * @author  Sergey Odintsov <sergey.odintsov@mkechinov.ru>
 */
//Путь до структуры
define('APPLICATION_ROOT', realpath('.'));

//Подключаем абстрактный класс
require_once APPLICATION_ROOT . "/system/boot/trait/console.php";

error_reporting(E_ALL);
ini_set("display_errors", 1);

class Boot_SassInstaller {
	use Boot_Console;

	//Constructor
	public function __construct() {

		//Рабочая директория
		$dir = APPLICATION_ROOT . "/vendor/sensational/sassphp";

		//Если директория существует
		if( is_dir($dir) ) {

			//Обновляем код
			$this->exec("cd " . escapeshellarg($dir) . " && git pull && git submodule init && git submodule update");
		} else {
			//Проверяем существование директории
			$this->exec("mkdir -p -- " . escapeshellarg($dir));

			//Делаем клонирование директории
			$this->exec("cd " . escapeshellarg($dir) . " && git clone git://github.com/sensational/sassphp . && git submodule init && git submodule update");
		}

		//Запускаем установочник
		$this->exec("cd " . escapeshellarg($dir) . " && php install.php && make install");

		//Выводим сообщение
		$this->message("Add it to your php.ini:" . PHP_EOL . "extension=sass.so" . PHP_EOL);
	}
}
new Boot_SassInstaller();