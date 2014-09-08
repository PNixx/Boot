<?php
/**
 * User: nixx
 * Date: 03.09.14
 * Time: 22:30
 */
//Путь до структуры
define('APPLICATION_PATH', realpath('.') . '/application');
define('APPLICATION_ROOT', realpath('.'));
define('LIBRARY_PATH', realpath('.') . '/library');

error_reporting(E_ALL);
ini_set("display_errors", 1);

//Подключаем файл асетов
require_once APPLICATION_ROOT . '/system/boot/assets.php';

//Выполняем поиск
$css = new Boot_Assets("css", true);
$css->read_all_assets();
$js = new Boot_Assets("js", true);
$js->read_all_assets();