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
	exit(127);
}

if( preg_match("/^(create_table|alter_table|drop_table|create_index|sql)_?(.*)$/i", $name, $match) ) {
	switch( $match[1] ) {

		//Создание таблицы
		case "create_table":

			//Создаваемая таблица
			$table = $match[2];
			if( $table == null ) {
				echo "You must write table name, example: create_table_user" . PHP_EOL;
				exit(127);
			}

			if( file_exists(APPLICATION_PATH . "/models/{$table}.php") ) {
				echo "Model_{$table} is exist" . PHP_EOL;
				exit(127);
			}

			//Строим параметры
			$schema = array();
			if( count($argv) > 1 ) {

				//Обозначаем первичные ключи
				$pkey = null;
				$ukey = null;

				for($i = 2; $i < count($argv); $i++) {
					if( preg_match("/^:PKEY=(.+?)$/", $argv[$i], $m) ) {
						if( stristr($m[1], ",") ) {
							exit("Incorrect PKEY value");
						} else {
							$pkey = $m[1];
						}
					} elseif(preg_match("/^:UKEY=(.+?)$/", $argv[$i], $m)) {
						$ukey = explode(",", $m[1]);
					} else {
						$column_args = explode(":", $argv[$i]);

						//Подменяем тип столбца для модели
						switch( $column_args[1] ) {
							case "string":
								$type = "varchar(255)";
								break;
							default:
								$type = $column_args[1];
						}

						//Если указано значение для NULL
						if( !empty($column_args[2]) && $column_args[2] == "false" ) {
							$type .= ' NOT NULL';
						}

						//Если указано дефолтное значение
						if( !empty($column_args[3]) ) {
							$type .= " DEFAULT '{$column_args[3]}'";
						}

						$schema[$column_args[0]] = $type;
					}
				}

				//Если были указаны ключи, добавляем их
				if( $pkey ) {
					$schema[":PKEY"] = $pkey;
				}
				//Если были указаны ключи, добавляем их
				if( $ukey ) {
					$schema[":UKEY"] = $ukey;
				}
			}

			//Указываем тип
			$migrate_type = Boot_Migration::TYPE_CHANGE;

			//Получаем вставленный шаблон
			$model = str_replace("[{MODEL_NAME}]", "Model_" . ucfirst($table), file_get_contents(APPLICATION_ROOT . "/system/boot/db/model.template"));

			if( isset($pkey) && $pkey ) {
				$model = str_replace("[{PKEY}]", "\r\n\tprotected static \$pkey = \"{$pkey}\";\r\n", $model);
			} else {
				$model = str_replace("[{PKEY}]", "", $model);
			}

			//Строим схему переменных
			$schema_string = "";
			$schema_column = array_keys($schema);
			foreach( $schema_column as $column ) {
				if( !in_array($column, array(":UKEY", ":PKEY")) ) {
					$schema_string .= "\r\n * @property \${$column}";
				}
			}

			//Заменяем в шаблоне
			$model = str_replace("[{MODEL_SCHEME}]", $schema_string, $model);

			break;

		//Удаление таблицы
		case "drop_table":

			//Удаляемая таблица
			$table = $match[2];
			if( $table == null ) {
				echo "You must write table name, example: drop_table_user";
				exit(127);
			}

			//Указываем тип
			$migrate_type = Boot_Migration::TYPE_UP;

			//Указываем sql
			$argv[2] = "DROP TABLE \"{$table}\";";

			break;

		//Изменение колонок в таблице
		case "alter_table":

			//Редактируемая таблица таблица
			$table = $match[2];

			//Проверяем данные
			if( $table == null ) {
				echo "You must write table name, example: create_table_user";
				exit(127);
			}
			if( file_exists(APPLICATION_PATH . "/models/{$table}.php") == false ) {
				echo "Model_{$table} is not exist";
				exit(127);
			}

			//Строим параметры
			$schema = array();

			if( count($argv) > 1 ) {

				//Действия
				$rename = array();
				$add = array();
				$remove = [];

				for($i = 2; $i < count($argv); $i++) {
					//Если было указано изменение имени колонки
					if( preg_match("/^(.+?)=(.+?)$/", $argv[$i], $m) ) {
						$rename[$m[1]] = $m[2];
					}
					//Если было указано изменение добавление колонки
					if( preg_match('/^\+([^\+\-\s]+):(.+?)$/', $argv[$i], $m) ) {
						$add[$m[1]] = $m[2] == "string" ? "varchar(255)" : $m[2];
					}
				}
				if( $rename ) {
					$schema["rename"] = $rename;
				}
				if( $add ) {
					$schema["add"] = $add;
				}
			}

			//Указываем тип
			$migrate_type = Boot_Migration::TYPE_CHANGE;

			break;

		//Создание индекса таблицы
		case "create_index":

			//Проверяем таблицу
			$table = $match[2];
			if( $table == null ) {
				exit("You must write table name, example: create_index_user" . PHP_EOL);
			}

			//Строим параметры
			$schema = [];
			for( $i = 2; $i < count($argv); $i++ ) {
				$schema[] = explode(',', $argv[$i]);
			}

			//Указываем тип
			$migrate_type = Boot_Migration::TYPE_CHANGE;

			break;
		//Выполнение sql кода
		case "sql":

			//Указываем тип
			$migrate_type = Boot_Migration::TYPE_UP;
			break;

		default:
			$migrate_type = null;
			break;
	}

	//Создаём миграцию по типу
	switch( $migrate_type ) {

		case Boot_Migration::TYPE_CHANGE:
			$insert = "\$migration = " . var_export(array(
				"change" => array(
					$match[1] => array(
						$table => $schema
					)
				)
			), true) . ";";
			break;

		case Boot_Migration::TYPE_UP:
			$insert = "\$migration = " . var_export(array(
				"up" => array("sql" => isset($argv[2]) ? $argv[2] : ""),
				"down" => array("sql" => isset($argv[3]) ? $argv[3] : "")
			), true) . ";";
			break;

		default:
			echo "Incorrect type of the migration";
			exit(127);
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
//	if( isset($model_row) ) {
//		if( is_dir(APPLICATION_PATH . "/models/row/") == false ) {
//			mkdir(APPLICATION_PATH . "/models/row/");
//			chmod(APPLICATION_PATH . "/models/row/", 0777);
//		}
//		file_put_contents(
//			APPLICATION_PATH . "/models/row/" . $table . "_row.php",
//			$model_row
//		);
//		echo "create row model: " . APPLICATION_PATH . "/models/row/" . $table . "_row.php\r\n";
//	}
}

Boot::getInstance()->end();