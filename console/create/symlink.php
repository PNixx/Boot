<?php
/**
 * Date: 24.08.15
 * Time: 17:11
 * @author  Sergey Odintsov <sergey.odintsov@mkechinov.ru>
 */
define('APPLICATION_ROOT', realpath('.'));

//Создаем симлинки
if( !file_exists(APPLICATION_ROOT . "/console") ) {
	symlink(APPLICATION_ROOT . "/vendor/pnixx/boot/console", APPLICATION_ROOT . "/console");
}
if( !file_exists(APPLICATION_ROOT . "/system") ) {
	symlink(APPLICATION_ROOT . "/vendor/pnixx/boot/system", APPLICATION_ROOT . "/system");
}

//Запускаем файл конфига
system("php " . APPLICATION_ROOT . "/vendor/pnixx/boot/console/create/config.php");