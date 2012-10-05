<?php
session_start();

//Путь до структуры
define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));
define('APPLICATION_ROOT', realpath(dirname(__FILE__) . '/..'));
define('LIBRARY_PATH', realpath(dirname(__FILE__) . '/../library'));

//Устанавливаем загрузку библиотек
set_include_path(implode(PATH_SEPARATOR, array(
																							realpath(realpath(dirname(__FILE__)) . '/../system'),
																							get_include_path(),
																				 )));

date_default_timezone_set("Europe/Moscow");

//Загружаем фреймворк
require_once 'boot.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);

//Запускаем
Boot::getInstance()->run();