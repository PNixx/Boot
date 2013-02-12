<?
/**
 * postgres
 */
class postgres {

	private $_host = null;

	private $_port = null;

	private $_user = null;

	private $_pass = null;

	private $_dbase = null;

	public $separator = '"';
	public $int_separator = "'";
	private $_connect = null;

	/**
	 * Результат запроса
	 * @var $result object
	 */
	private $result = null;

	public function __construct($host, $port, $user, $pass, $dbase) {

		$this->_host = $host;
		$this->_port = $port;
		$this->_user = $user;
		$this->_pass = $pass;
		$this->_dbase = $dbase;
	}

	/**
	 * @param null $query
	 * @throws DB_Exception
	 */
	private function error($query = null) {
		throw new DB_Exception(pg_last_error() . ($query ? " query: " . $query : "") . "\n", 500);
	}

	/**
	 * Подключаемся к БД
	 * @return object
	 */
	public function connect() {
		$this->_connect = pg_connect("host={$this->_host} port={$this->_port} dbname={$this->_dbase} user={$this->_user} password={$this->_pass} options='--client_encoding=UTF8'") or new DB_Exception("Could not connect");
		return $this->_connect;
	}

	/**
	 * SQL query
	 * @param	$query
	 * @return Model
	 */
	public function query($query) {
		$this->result = @pg_query($this->_connect, $query) or $this->error($query);
		return $this;
	}

	public function show_tables($table) {
		return $this->query("SELECT * FROM pg_tables WHERE schemaname='public' AND tablename = '{$table}'")->read();
	}

	/**
	 * Create table
	 * @param $table
	 * @param $column
	 * @param null $pkey
	 * @param null $ukey
	 * @return \Model
	 */
	public function create_table($table, $column, $pkey = null, $ukey = null) {
		$sql = "";
		foreach($column as $col => $data) {
			$sql .= ($sql == "" ? "" : ",") . $this->separator . $col . $this->separator . " {$data}";
		}
		$sql_pkey = "";
		if( $pkey ) {
			$sql_pkey = ", PRIMARY KEY ({$this->separator}{$pkey}{$this->separator})";
		}
		$sql_ukey = "";
		if( $ukey ) {
			if( !is_array($ukey) ) {
				$ukey = explode(",", $ukey);
			}
			foreach($ukey as $key) {
				$sql_ukey .= ($sql_ukey == "" ? "" : ",") . $this->separator . $key . $this->separator;
			}
			$sql_ukey = ", CONSTRAINT {$this->separator}ukey_{$table}_" . implode("_", $ukey) . "{$this->separator} UNIQUE  ({$sql_ukey})";
		}
		return $this->query("CREATE TABLE {$this->separator}{$table}{$this->separator} ({$sql}{$sql_pkey}{$sql_ukey});");
	}

	/**
	 * Изменение имени колонки
	 * @param $table
	 * @param $column
	 * @param $new_name
	 */
	public function rename_column($table, $column, $new_name) {
		$this->query("ALTER TABLE {$this->separator}{$table}{$this->separator} RENAME {$this->separator}{$column}{$this->separator} TO {$this->separator}{$new_name}{$this->separator};");
	}

	/**
	 * Добавление колонки
	 * @param $table
	 * @param $column
	 * @param $type
	 */
	public function add_column($table, $column, $type) {
		$this->query("ALTER TABLE {$this->separator}{$table}{$this->separator} ADD COLUMN {$this->separator}{$column}{$this->separator} {$type};");
	}

	/**
	 * Удаление колонки
	 * @param $table
	 * @param $column
	 */
	public function drop_column($table, $column) {
		$this->query("ALTER TABLE {$this->separator}{$table}{$this->separator} DROP COLUMN {$this->separator}{$column}{$this->separator};");
	}

	/**
	 * Drop table
	 * @param $table
	 * @return \Model
	 */
	public function drop_table($table) {
		return $this->query("BEGIN;
		DROP TABLE {$this->separator}{$table}{$this->separator};
		DROP SEQUENCE IF EXISTS {$this->separator}{$table}_id_seq{$this->separator};
		COMMIT;");
	}

	/**
	 * Выбор всех записей в таблице по запросу
	 * @param string $table Имя таблицы
	 * @param string $where
	 * @return Model
	 */
	public function select($table, $where = null, $colum = null, $order = null, $limit = null) {
		return $this->query('SELECT ' . ($colum === null ? '*' : $colum) . ' FROM ' . $this->separator . $table . $this->separator . ($where ? ' WHERE ' . $where : '') . ($order ? " ORDER BY " . $order : "") . ($limit ? " LIMIT " . $limit : ""));
	}

	/**
	 * Чтение 1 записи, возврат объекта
	 * @return object
	 */
	public function row() {

		//Сохраняем результат
		$result = $this->result;

		//Результат возврата
		$return = pg_fetch_object($this->result);
		if( $return == false ) {
			return $return;
		}

		//Прооходим по колонкам, собираем массив
		foreach( $return as $column => $value ) {

			//Получаем номер столбца
			$num = pg_field_num($result, $column);

			//Если значение нулевое, идём дальше
			if( pg_field_is_null($result, null, $num) ) {
				$return->$column = null;
				continue;
			}

			//В зависимости от типа присваиваем значение
			switch( pg_field_type($this->result, $num) ) {

				case "bool":
					$return->$column = $value == "t" ? true : false;
					break;
				case "int4":
				case "int8":
				$return->$column = (int)$value;
					break;
			}
		}
		return $return;
	}

	/**
	 * Чтение 1 записи, возврат массива
	 * @return array
	 */
	public function read() {
		return @pg_fetch_assoc($this->result);
	}

	/**
	 * Считывает из запроса первую колонку таблицы
	 * @return array
	 */
	public function read_cols() {
		$r = array();
		while( $line = pg_fetch_array($this->result) ) {
			$r[] = $line[0];
		}
		return $r;
	}

	/**
	 * Чтение кол-ва записай в запросе
	 * @return integer
	 */
	public function num_rows() {
		return pg_num_rows($this->result);
	}

	/**
	 * Кол-во затронутых сток в запросе
	 * @return integer
	 */
	public function affected_rows() {
		return @pg_affected_rows($this->result);
	}

	/**
	 * Преобразовывает массив в строку для запроса UPDATE
	 * @param array $data
	 * @return string
	 */
	public function getUpdateStringByArray(array $data) {
		//Если ни каких данных не передали, выходим
		if( count($data) == 0 ) {
			return false;
		}

		$q = '';
		foreach( $data as $key => $v ) {

			if( is_null($v) == false && is_int($v) == false ) {
				$v = addslashes($v);
			}

			$q .= ($q != '' ? ', ' : '') . $this->separator . $key . $this->separator . '=' . (is_int($v) || is_null($v) ? (is_null($v) ? 'NULL' : $v) : "'$v'");
		}
		return $q;

	}

	/**
	 * Преобразовывает массив, в строку для INPUT, возвращает ключи и параметры в массиве
	 * @param array $data
	 * @return array ('insert','values')
	 */
	public function getInsertStringByArray(array $data) {
		//Если ни каких данных не передали, выходим
		if( count($data) == 0 ) {
			return false;
		}

		$q = '';
		$i = '';
		foreach( $data as $key => $v ) {
			//Ключи VALUES
			$i .= ($i != '' ? ',' : '') . $this->separator . $key . $this->separator;
			//Ключи INSERT
			$q .= ($q != '' ? ', ' : '') . (is_int($v) || is_null($v) ? (is_null($v) ? 'NULL' : addslashes($v)) : "'" . addslashes($v) . "'");
		}
		return array('insert' => $q, 'values' => $i);

	}

	/**
	 * Обновить данные таблицы из массива
	 * @param string $table
	 * @param array $data
	 * @param string $where
	 * @return <type>
	 */
	public function update($table, $data, $where = null) {

		$q = $this->getUpdateStringByArray($data);
		//Если данных в массиве не было, выходим
		if( $q === false ) {
			return false;
		}

		$this->query("UPDATE {$this->separator}{$table}{$this->separator} SET $q" . ($where ? ' WHERE ' . $where : ''));
		return $this->affected_rows();

	}

	public function insert($table, array $data, $pkey = null) {

		$q = $this->getInsertStringByArray($data);
		//Если данных в массиве не было, выходим
		if( $q === false ) {
			return false;
		}

		$id = $this->query("INSERT INTO {$this->separator}{$table}{$this->separator} (" . $q['values'] . ")VALUES(" . $q['insert'] . ")" . ($pkey ? " RETURNING " . $this->separator . $pkey . $this->separator : "") . ";")->row();
		if( $id ) {
			return $id->$pkey;
		} else {
			return false;
		}
	}

	/**
	 * Удаление
	 * @param $table
	 * @param $id
	 * @return void
	 */
	public function delete($table, $id) {
		$this->query("DELETE FROM {$this->separator}{$table}{$this->separator} WHERE id = '{$id}';");
	}
}