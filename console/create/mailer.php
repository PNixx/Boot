<?php
/**
 * Date: 29.11.15
 * Time: 12:55
 * @author  Sergey Odintsov <sergey.odintsov@mkechinov.ru>
 */
//Путь до структуры
define('APPLICATION_PATH', realpath('.') . '/application');
define('APPLICATION_ROOT', realpath('.'));
define('LIBRARY_PATH', realpath('.') . '/library');

error_reporting(E_ALL);
ini_set("display_errors", 1);

require APPLICATION_ROOT . '/system/boot/helper/console.php';

//Получаем имя
if( empty($argv[1]) ) {
	echo 'Write mailer name' . PHP_EOL;
	exit(127);
}
$mailer = ucfirst($argv[1]);

//Проверяем директории
$directories = [
	"/application/mailers",
	"/application/views/mailer",
	"/test/mailers",
];

//Создаём необходимые директории
Boot_Console_Helper::mkdir($directories);

//Создаем класс
$class = <<<PHP
<?
class {$mailer}Mailer extends Boot_Mailer_Abstract {

}
PHP;
Boot_Console_Helper::create_file('/application/mailers/' . strtolower($mailer) . '.php', $class);

//Создаем класс
$class = <<<PHP
<?
class {$mailer}MailerPreview {

}
PHP;
Boot_Console_Helper::create_file('/test/mailers/' . strtolower($mailer) . '.php', $class);
