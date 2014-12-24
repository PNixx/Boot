<?php
/**
 * User: Odintsov S.A.
 * Date: 21.08.11
 * Time: 11:36
 */

//Путь до структуры
define('APPLICATION_PATH', realpath('.') . '/application');
define('APPLICATION_ROOT', realpath('.'));
define('LIBRARY_PATH', realpath('.') . '/library');

//Устанавливаем загрузку библиотек
set_include_path(implode(PATH_SEPARATOR, array(realpath(APPLICATION_ROOT . '/system'), get_include_path())));

//Загружаем фреймворк
require_once 'boot.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);

//Запускаем
Boot::getInstance()->cron();

//Если файла нет, создаём
if( file_exists(Boot::getInstance()->config->translate->dir . "en.json") == false ) {
  file_put_contents(Boot::getInstance()->config->translate->dir . "en.json", "{}");
}

//Запускаем
$translate = new Boot_Translate_Lib(Boot::getInstance()->config->translate->dir, Boot::getInstance()->config->translate->lang);
if( file_exists(Boot::getInstance()->config->translate->dir . Boot::getInstance()->config->translate->lang . ".po") ) {
	$parse = $translate->parse(Boot::getInstance()->config->translate->lang);
} else {
	$parse = $translate->parseJSON(Boot::getInstance()->config->translate->lang);
}

function glob_recursive($pattern, $flags = 0) {
  $files = glob($pattern, $flags);

  foreach( glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir ) {
    $files = array_merge($files, glob_recursive($dir . '/' . basename($pattern), $flags));
  }

  return $files;
}

//Проходим по файлам, ищем ключи для перевода
$files = glob_recursive(APPLICATION_PATH . "/*.php", GLOB_NOSORT);
$files = array_merge($files, glob_recursive(APPLICATION_PATH . "/*.phtml", GLOB_NOSORT));

$keys = array();
foreach( $files as $file ) {
  //"/_\(\"(.+)\"(?:,\s?(?:$\w+|\"\w+\"))?\)/"
  if( preg_match_all("/->_\(\"([^\r\n]+?)\",?/", file_get_contents($file), $po) && isset($po[1]) ) {
//		print_r($po);
    foreach( $po[1] as $i => $key ) {
      $key = str_replace("\\\$", "$", $key);
      if( isset($parse[$key]) == false ) {
        echo $key . "\r\n";
        $parse[$key] = "";
      }
      $keys[] = $key;
    }
  }
}

//Получаем ключи, которых не нашли на сайте
$diff = array_diff(array_keys($parse), $keys);

//Проходим по ним и удаляем
foreach($diff as $d) {
  unset($parse[$d]);
}

//Если файл старой версии существует, удаляем
if( file_exists(Boot::getInstance()->config->translate->dir . Boot::getInstance()->config->translate->lang . ".po") ) {
	unlink(Boot::getInstance()->config->translate->dir . Boot::getInstance()->config->translate->lang . ".po");
}

//Записываем все значения в массив
file_put_contents(Boot::getInstance()->config->translate->dir . Boot::getInstance()->config->translate->lang . ".json", json_encode($parse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));