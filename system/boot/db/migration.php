<?php
/**
 * User: Odintsov S.A.
 * Date: 03.09.12
 * Time: 20:56
 */

class Boot_Migration extends Model {

	//При миграции
	const TYPE_UP = 1;

	//При rollback
	const TYPE_DOWN = 2;

	//При совместном
	const TYPE_CHANGE = 3;

	/**
	 * Таблица migration
	 * @var string
	 */
	protected $table = "migration";

	/**
	 * Инстанс
	 * @var Boot_Migration
	 */
	static private $_instance = null;

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot_Migration
	 */
	static public function model() {

		if( !(self::$_instance instanceof Boot_Migration) ) {
			self::$_instance = new Boot_Migration();
			if( self::$_instance->show_tables() == false ) {
				self::$_instance->create_table(self::$_instance->table, array("id" => "varchar(100) NOT NULL"), "id");
			}
		}
		return self::$_instance;
	}

	/**
	 * Получение последней миграции
	 */
	public function getLatestMigration() {

		$row = $this->query($this->select(null, "id", "id DESC", 1))->row();
		return $row ? $row->id : null;
	}

	//----------------------------MIGRATE------------------------------->
	/**
	 * Выполняет миграцию
	 * @param $file
	 */
	public function migrate($file) {

		//Подключаем файл миграции
		require APPLICATION_ROOT . "/db/{$file}";

		if( isset($migration) ) {
			foreach($migration as $key => $migrate) {

				//Выбираем тип миграции
				switch( $key ) {

					case "change":
						$this->change_up($migrate);
						break;

					default:
						exit("Unknown migration type\r\n");
				}
			}
			if( preg_match("/^(\d+)/", $file, $match) ) {
				$this->insert(array("id" => $match[1]));
				echo "Migration `{$match[1]}` success.\r\n";
			}
		}
	}

	/**
	 * Миграция
	 * @param $migrate
	 */
	private function change_up(array $migrate) {

		//Проходим по типам миграции
		foreach($migrate as $type => $data) {
			switch( $type ) {

				//СОздание таблицы
				case "create_table":
					$this->create_tables($data);
					break;

				default:
					exit("Unknown type of migration");
			}
		}
	}

	/**
	 * Создание таблицы
	 * @param array $table
	 */
	private function create_tables(array $tables) {

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
				$ukey = explode(",", $key[":UKEY"]);
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
			$this->create_table($table, $column, $pkey, $ukey);
			echo "Create table `{$table}`\r\n";
		}
	}

	//---------------------------------ROLLBACK---------------------------------->
	/**
	 * Rollback миграции
	 * @param $file
	 */
	public function rollback($file) {

		//Подключаем файл миграции
		require APPLICATION_ROOT . "/db/{$file}";

		if( isset($migration) ) {
			foreach($migration as $key => $migrate) {

				//Выбираем тип миграции
				switch( $key ) {

					case "change":
						$this->change_down($migrate);
						break;

					default:
						exit("Unknown rollback type\r\n");
				}
			}
			if( preg_match("/^(\d+)/", $file, $match) ) {
				$this->delete($match[1]);
				echo "Rollback `{$match[1]}` success.\r\n";
			}
		}
	}

	/**
	 * Rollback
	 * @param $migrate
	 */
	private function change_down(array $migrate) {

		//Проходим по типам миграции
		foreach($migrate as $type => $data) {
			switch( $type ) {

				//СОздание таблицы
				case "create_table":
					foreach($data as $table => $column) {
						$this->drop_table($table);
						echo "DROP TABLE `{$table}`;\r\n";
					}
					break;

				default:
					exit("Unknown type of rollback");
			}
		}
	}
}
