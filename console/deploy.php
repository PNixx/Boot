<?php
/**
 * User: nixx
 * Date: 04.03.14
 * Time: 19:07
 */
//Путь до структуры
define('APPLICATION_PATH', realpath('.') . '/application');
define('APPLICATION_ROOT', realpath('.'));
define('LIBRARY_PATH', realpath('.') . '/library');

error_reporting(E_ALL);
ini_set("display_errors", 1);

//Подключаем абстрактный класс
require_once APPLICATION_ROOT . "/system/boot/trait/console.php";
require_once APPLICATION_ROOT . "/system/boot/abstract/deploy.php";

//Получаем, какой инстанс мы деплоим
$application = isset($argv[1]) ? $argv[1] : null;
if( $application == null ) {
	exit("Write application deploy" . PHP_EOL);
}

//Проверяем наличие файла
if( !file_exists(APPLICATION_ROOT . "/deploy/" . $application . ".php") ) {
	echo "\e[31mDeploy file " . $application . ".php not found\e[0m\r\n";
	exit(127);
}

//Подключаем файл
require_once APPLICATION_ROOT . "/deploy/" . $application . ".php";

//Собираем класс деплоя
$class = "Boot_" . ucfirst($application) . "_Deploy";

/**
 * Инициализируем деплой
 * @var Boot_Deploy_Abstract $deploy
 */
$deploy = new $class();
if( isset($argv[2]) == false ) {
	$deploy->error("Unknown case");
}

//Запускаем
switch( $argv[2] ) {

	case "deploy":
		$deploy->deploy(strtolower($application));
		$deploy->migrate();
		break;

	case "deploy:migrate":
		$deploy->migrate();
		break;

	case "setup":
		$deploy->setup();
		break;

	default:
		$deploy->error("Unknown case");
}