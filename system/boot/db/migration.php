<?php
/**
 * User: Odintsov S.A.
 * Date: 03.09.12
 * Time: 20:56
 */

class Boot_Migration extends ActiveRecord {

	//При миграции
	const TYPE_UP = 1;

	//При rollback
	const TYPE_DOWN = 2;

	//При совместном
	const TYPE_CHANGE = 3;

	/**
	 * Получение имени таблицы
	 * @return string
	 */
	static protected function getTable() {
		return "migration";
	}

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot_Migration
	 */
	static public function model() {

		if( self::show_tables() == false ) {
			self::create_table(self::getTable(), array("id" => "varchar(100) NOT NULL"), "id");
		}
		return new static;
	}

	/**
	 * Получение последней миграции
	 * @param int $limit
	 * @return array
	 */
	static public function getLatestMigration($limit = 1) {
		return self::column("id")->order("id DESC")->limit($limit)->read_cols();
	}

	//----------------------------MIGRATE------------------------------->
	/**
	 * Выполняет миграцию
	 * @param $file
	 * @throws Exception
	 */
	static public function migrate($file) {

		//Подключаем файл миграции
		require APPLICATION_ROOT . "/db/{$file}";
		try {
			if( isset($migration) ) {
				self::begin_transaction();
				foreach($migration as $key => $migrate) {

					//Выбираем тип миграции
					switch( $key ) {

						case "change":
						case "up":
							self::change_up($migrate);
							break;

						//Пропускаем кейс
						case "down":
							break;

						default:
							exit("Unknown migration type\r\n");
					}
				}
				if( preg_match("/^(\\d+)/", $file, $match) ) {
					self::insert(["id" => $match[1]]);
					self::commit();
					echo "Migration `{$match[1]}` success.\r\n";
				}
			}
		} catch( Exception $e ) {
			echo "!!! Migration error, start rollback\r\n" . $e->getMessage() . PHP_EOL;
			try {
				self::rollback();
			} catch( Exception $ee ) {
			}
			echo "!!! Rollback end\r\n\r\n";
			throw $e;
		}
	}

	/**
	 * Миграция
	 * @param $migrate
	 */
	static private function change_up(array $migrate) {

		//Проходим по типам миграции
		foreach($migrate as $type => $data) {
			switch( $type ) {

				//СОздание таблицы
				case "create_table":
					self::create_tables($data);
					break;
				case "alter_table":
					self::alter_table($data);
					break;

				//Удаление таблицы
				case "drop_table":
					foreach($data as $table) {
						self::drop_table($table);
						echo "DROP TABLE `{$table}`;\r\n";
					}
					break;

				//Создание индекса
				case "create_index":
					self::create_indexes($data);
					break;

				case "sql":
					self::model()->query($data)->all();
					break;

				default:
					exit("Unknown type of migration" . PHP_EOL);
			}
		}
	}

	/**
	 * Создание таблицы
	 * @param array $tables
	 * @return void
	 */
	static private function create_tables(array $tables) {

		foreach($tables as $table => $key) {

			//Обнуляем
			$column = array();
			$ukey = array();
			$pkey = null;

			//Если были указаны первичные ключи
			if( array_key_exists(":PKEY", $key) ) {
				if( stristr($key[":PKEY"], ",") ) {
					exit("Incorrect PKEY value");
				}
				$pkey = $key[":PKEY"];
				unset($key[":PKEY"]);
			}

			//Если были указаны уникальные ключи
			if( array_key_exists(":UKEY", $key) ) {
				$ukey = $key[":UKEY"];
				unset($key[":UKEY"]);
			}

			//Создаём колонку id
			if( $pkey == null && array_key_exists("id", $key) == false ) {

				//Создаём стандартный ключ
				$pkey = "id";

				//Строим колонки
				switch( Boot::getInstance()->config->db->adapter ) {

					case "postgres":
						$column = array(
							"id" => "serial"
						);
						break;
					case "mysql":
						$column = array(
							"id" => "int NOT NULL AUTO_INCREMENT"
						);
						break;

					default:
						exit("Unknown type db adapter");
				}
			}

			//Делаем миграцию колонок
			$column = array_merge($column, $key);

			//Строим колонки
			if( array_key_exists("date", $column) == false ) {
				switch( Boot::getInstance()->config->db->adapter ) {
					case "postgres":
						$column["date"] = "timestamp DEFAULT now() NOT NULL";
						break;
					case "mysql":
						$column["date"] = "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
						break;
				}
			}

			//Отправляем запрос на создание таблицы
			self::create_table($table, $column, $pkey, $ukey);
			echo "Create table `{$table}`\r\n";
		}
	}

	/**
	 * @param array $data
	 */
	static private function alter_table(array $data) {

		//Проходим по таблицам
		foreach($data as $table => $actions) {

			$model = "Model_" . $table;
			/**
			 * @var $model ActiveRecord
			 */
			$model = new $model();

			//Проходим по действиям
			foreach($actions as $action => $values) {

				//Проходим по значениям
				foreach($values as $key => $value) {
					switch( $action ) {

						case "rename":
							$model->rename_column($key, $value);
							echo $table . ": rename column `{$key}` => `{$value}` \r\n";
							break;

						case "add":
							$model->add_column($key, $value);
							echo $table . ": add column `{$key}` {$value} \r\n";
							break;

						default:
							exit("Unknown action");
					}
				}
			}
		}
	}

	/**
	 * Создание индексов
	 * @param array $data
	 */
	static private function create_indexes(array $data) {
		foreach($data as $table => $row) {
			foreach( $row as $columns ) {
				self::create_index($table, $columns);
				echo $table . ": create index idx_{$table}_" . implode("_", $columns) . PHP_EOL;
			}
		}
	}

	/**
	 * Удаление индексов
	 * @param array $data
	 */
	static private function drop_indexes(array $data) {
		foreach( $data as $table => $row ) {
			foreach( $row as $columns ) {
				self::drop_index($table, $columns);
				echo $table . ": drop index idx_{$table}_" . implode("_", $columns) . PHP_EOL;
			}
		}
	}

	/**
	 * @param array $data
	 */
	static private function rollback_alter_table(array $data) {

		//Проходим по таблицам
		foreach($data as $table => $actions) {

			$model = "Model_" . $table;
			/**
			 * @var $model ActiveRecord
			 */
			$model = new $model();

			//Проходим по действиям
			foreach($actions as $action => $values) {

				//Проходим по значениям
				foreach($values as $key => $value) {
					switch( $action ) {

						case "rename":
							$model->rename_column($value, $key);
							echo $table . ": rename column `{$value}` => `{$key}` \r\n";
							break;

						case "add":
							$model->drop_column($key);
							echo $table . ": drop column `{$key}` {$value} \r\n";
							break;

						default:
							exit("Unknown action");
					}
				}
			}
		}
	}

	//---------------------------------ROLLBACK---------------------------------->
	/**
	 * Rollback миграции
	 * @param $file
	 */
	static public function rollback_migration($file) {

		//Подключаем файл миграции
		require APPLICATION_ROOT . "/db/{$file}";

		if( isset($migration) ) {
			self::begin_transaction();
			foreach($migration as $key => $migrate) {

				//Выбираем тип миграции
				switch( $key ) {

					case "change":
						self::change_down($migrate);
						break;
					case "down":
						self::down($migrate);
						break;

					//Пропускаем кейс
					case "up":
						break;

					default:
						exit("Unknown rollback type\r\n");
				}
			}
			if( preg_match("/^(\\d+)/", $file, $match) ) {
				self::find((string)$match[1])->destroy();
				self::commit();
				echo "Rollback `{$match[1]}` success.\r\n";
			}
		}
	}

	/**
	 * Rollback
	 * @param $migrate
	 */
	static private function change_down(array $migrate) {

		//Проходим по типам миграции
		foreach($migrate as $type => $data) {
			switch( $type ) {

				//СОздание таблицы
				case "create_table":
					foreach($data as $table => $column) {
						if( self::drop_table($table) ) {
							echo "DROP TABLE `{$table}`;\r\n";
						}
					}
					break;

				case "alter_table":
					self::rollback_alter_table($data);
					break;

				case "create_index":
					self::drop_indexes($data);
					break;

				case "sql":
					self::model()->query($data);
					break;

				default:
					exit("Unknown type of rollback" . PHP_EOL);
			}
		}
	}

	/**
	 * Down function of migration
	 * @param $migrate
	 */
	static private function down(array $migrate) {

		//Проходим по типам миграции
		foreach($migrate as $type => $data) {
			switch( $type ) {

				//Создание таблицы
				case "create_table":
					self::create_tables($data);
					break;

				case "alter_table":
					self::rollback_alter_table($data);
					break;

				case "sql":
					self::model()->query($data)->all();
					break;

				default:
					exit("Unknown type of rollback" . PHP_EOL);
			}
		}
	}
}
