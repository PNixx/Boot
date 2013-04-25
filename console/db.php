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
set_include_path(implode(PATH_SEPARATOR, array(realpath(APPLICATION_ROOT . '/system'), get_include_path(),)));

//Загружаем фреймворк
require_once 'boot.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);

//Запускаем
Boot::getInstance()->cron();
require_once APPLICATION_ROOT . '/system/boot/db/migration.php';

if( isset($argv[1]) && trim($argv[1]) ) {
	$type = $argv[1];
} else {
	echo "You have to write the type";
	exit;
}

switch( $type ) {

	case "migrate":
		//Получаем последнюю миграцию
		$latest_migration = Boot_Migration::model()->getLatestMigration();
		if( $latest_migration ) {
			$latest_migration = $latest_migration[0];
		} else {
			$latest_migration = 0;
		}

		//Получаем файлы
		$files = array();
		foreach(glob(APPLICATION_ROOT . "/db/*.php") as $file) {
			$file = pathinfo($file, PATHINFO_BASENAME);
			if( preg_match("/^(\d+)_/", $file, $match) && $match[1] > $latest_migration ) {
				$files[] = $file;
			}
		}
		sort($files);

		//Проходим по файла, делаем миграцию
		foreach($files as $file) {
			Boot_Migration::model()->migrate($file);
		}
		break;

	case "rollback":

		//Если указано удаление всех rollback или определённое кол-во
		$limit = 1;
		if( isset($argv[2]) && $argv[2] == "all" ) {
			$limit = null;
		} elseif( isset($argv[2]) ) {
			$limit = (int)$argv[2];
		}

		//Получаем последнюю миграцию
		$latest_migration = Boot_Migration::model()->getLatestMigration($limit);

		if( $latest_migration ) {
			//Получаем файлы
			$files = glob(APPLICATION_ROOT . "/db/*.php");
			foreach( $latest_migration as $migration ) {
				foreach($files as $file) {
					$file = pathinfo($file, PATHINFO_BASENAME);
					if( preg_match("/^{$migration}_/", $file) ) {
						Boot_Migration::model()->rollback($file);
						break;
					}
				}
			}
		}
		break;

	//Поиск в таблице по ID
	case "find":
		if( isset($argv[2]) == false ) {
			exit("You must write table" . PHP_EOL);
		}
		$class = "Model_" . $argv[2];
		if( class_exists($class) == false ) {
			exit("Unknown class: " . $class . PHP_EOL);
		}
		/**
		 * @var $model Model
		 */
		$model = new $class();
		if( isset($argv[3]) && (int)$argv[3] > 0 ) {
			$row = $model->find_array($argv[3]);
			if( $row ) {
				foreach($row as $column => $value) {
					if (mb_strlen($value, 'utf-8') > 100 ) {
						$row[$column] = mb_substr($value, 0, 97, 'utf-8') . "...";
					}
				}
			}
			print_r($row);
		} else {
			$rows = $model->where()->read_all();
			foreach($rows as $i => $row) {
				foreach($row as $column => $value) {
					if (mb_strlen($value, 'utf-8') > 100 ) {
						$rows[$i][$column] = mb_substr($value, 0, 97, 'utf-8') . "...";
					}
				}
			}
			print_r($rows);
		}
		break;

	//Удаление записи
	case "destroy":
		if( isset($argv[2]) == false ) {
			exit("You must write table" . PHP_EOL);
		}
		$class = "Model_" . $argv[2];
		if( class_exists($class) == false ) {
			exit("Unknown class: " . $class . PHP_EOL);
		}
		if( isset($argv[3]) == false && (int)$argv[3] < 1 ) {
			exit("You must write destroyed ID" . PHP_EOL);
		}
		/**
		 * @var $model Model
		 */
		$model = new $class();

		//Ищем запись
		$row = $model->find($argv[3]);
		if( $row ) {
			$row->destroy();
		} else {
			exit($argv[3] . " is not found" . PHP_EOL);
		}
		break;

	default:
		break;
}