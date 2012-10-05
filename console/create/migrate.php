<?php
/**
 * User: Odintsov S.A.
 * Date: 03.09.12
 * Time: 11:22
 */
//Путь до структуры
define('APPLICATION_PATH', realpath('.') . '/application');
define('APPLICATION_ROOT', realpath('.'));
define('LIBRARY_PATH', realpath('.') . '/library');

//Устанавливаем загрузку библиотек
set_include_path(implode(PATH_SEPARATOR, array(
	realpath(APPLICATION_ROOT . '/system'), get_include_path(),
)));

//Загружаем фреймворк
require_once 'boot.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);

//Запускаем
Boot::getInstance()->cron();
require_once APPLICATION_ROOT . '/system/boot/db/migration.php';

if( isset($argv[1]) && trim($argv[1]) ) {
	$name = $argv[1];
} else {
	echo "You have to write the name of the migration";
	exit;
}

if( preg_match("/^(create_table)_?(.*)$/i", $name, $match) ) {
	switch( $match[1] ) {

		//Создание таблицы
		case "create_table":

			//Создаваемая таблица
			$table = $match[2];

			if( file_exists(APPLICATION_PATH . "/models/{$table}.php") ) {
				exit("Model_{$table} is exist");
			}

			//Строим параметры
			$schema = "array(\r\n";
			if( count($argv) > 1 ) {
				for($i = 2; $i < count($argv); $i++) {
					list($column, $type) = explode(":", $argv[$i]);
					$schema .= ($schema == "array(\r\n" ? "\t\t\t\t" : ",\r\n\t\t\t\t") . "\"{$column}\" => \"{$type}\"";
				}
			}
			$schema .= "\r\n\t\t\t)";

			//Указываем тип
			$migrate_type = Boot_Migration::TYPE_CHANGE;

			//Получаем вставленный шаблон
			$model = str_replace(array("[{MODEL_NAME}]", "[{TABLE}]"), array("Model_" . ucfirst($table), $table), file_get_contents(APPLICATION_ROOT . "/system/boot/db/model.template"));

			//Получаем вставленный шаблон
			$model_row = str_replace("[{MODEL_NAME}]", "Model_" . ucfirst($table), file_get_contents(APPLICATION_ROOT . "/system/boot/db/model.row.template"));

			break;

		default:
			$migrate_type = null;
			break;
	}

	//Создаём миграцию по типу
	switch( $migrate_type ) {

		case Boot_Migration::TYPE_CHANGE:
			$insert = "\$migration = array(
	\"change\" => array(
		\"{$match[1]}\" => array(
			\"{$table}\" => {$schema}
		)
	)
);";

			break;

		default:
			exit("Incorrect type of the migration");
			break;
	}

	//Добавляем в файл
	file_put_contents(
		APPLICATION_ROOT . "/db/" . date("YmdHis") . "_" . $name . ".php",
		"<?\r\n/**\r\n * Boot framework\r\n * Author: P.Nixx\r\n * Site: pnixx.ru\r\n*/\r\n\r\n" . $insert
	);
	echo "create migration: " . APPLICATION_ROOT . "/db/" . date("YmdHis") . "_" . $name . ".php\r\n";
	if( isset($model) ) {
		file_put_contents(
			APPLICATION_PATH . "/models/" . $table . ".php",
			$model
		);
		echo "create model: " . APPLICATION_PATH . "/models/" . $table . ".php\r\n";
	}
	if( isset($model_row) ) {
		if( is_dir(APPLICATION_PATH . "/models/row/") == false ) {
			mkdir(APPLICATION_PATH . "/models/row/");
			chmod(APPLICATION_PATH . "/models/row/", 0777);
		}
		file_put_contents(
			APPLICATION_PATH . "/models/row/" . $table . "_row.php",
			$model_row
		);
		echo "create row model: " . APPLICATION_PATH . "/models/row/" . $table . "_row.php\r\n";
	}
}