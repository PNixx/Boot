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
	 * @throws Exception
	 */
	private function error($query = null) {
		//Debug
		Boot::getInstance()->debug("  SQL Error: " . $query);

		throw new Exception(pg_last_error() . ($query ? " query: " . $query : "") . "\n", 500);
	}

	/**
	 * Подключаемся к БД
	 * @return object
	 */
	public function connect() {

		//Запоминаем время начала
		$time = Boot::mktime();

		//Коннектимся к БД
		$this->_connect = pg_connect("host={$this->_host} port={$this->_port} dbname={$this->_dbase} user={$this->_user} password={$this->_pass} options='--client_encoding=UTF8'") or new DB_Exception("Could not connect");

		//Debug
		Boot::getInstance()->debug("  \x1b[35mPostgres (" . Boot::check_time($time) . "ms)\x1b[0m {$this->_host}:{$this->_port}");

		//Возвращаем коннект
		return $this->_connect;
	}

	public function escape_identifier($name) {
		return '"' . $name . '"';
	}

	public function begin_transaction() {
		$this->query("BEGIN");
	}

	public function commit() {
		$this->query("COMMIT");
	}

	public function rollback() {
		$this->query("ROLLBACK");
	}

	/**
	 * SQL query
	 * @param	$query
	 * @return postgres
	 */
	public function query($query) {

		//Запоминаем время начала
		$time = Boot::mktime();

		//Делаем запрос
		$this->result = @pg_query($this->_connect, $query) or $this->error($query);

		//Debug
		Boot::getInstance()->debug("  \x1b[36mSQL (" . Boot::check_time($time) . "ms)\x1b[0m " . $query);

		//Return
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
	 * @return postgres
	 */
	public function create_table($table, $column, $pkey = null, $ukey = null) {
		$sql = "";
		foreach($column as $col => $data) {
			$sql .= ($sql == "" ? "" : ",") . pg_escape_identifier($col) . " {$data}";
		}
		$sql_pkey = "";
		if( $pkey ) {
			$sql_pkey = ", PRIMARY KEY (" . pg_escape_identifier($pkey) . ")";
		}
		$sql_ukey = "";
		if( $ukey ) {
			if( !is_array($ukey) ) {
				$ukey = explode(",", $ukey);
			}
			foreach($ukey as $key) {
				$sql_ukey .= ($sql_ukey == "" ? "" : ",") . $this->separator . $key . $this->separator;
			}
			$sql_ukey = ", CONSTRAINT " . pg_escape_identifier("ukey_{$table}_" . implode("_", $ukey)) . " UNIQUE  ({$sql_ukey})";
		}
		$this->query("CREATE TABLE " . pg_escape_identifier($table) . " ({$sql}{$sql_pkey}{$sql_ukey});");
	}

	/**
	 * Create index
	 *
	 * @param string $table
	 * @param array  $columns
	 *
	 * @return postgres
	 */
	public function create_index($table, $columns) {
		$c = [];
		foreach( $columns as $column ) {
			$c[] = pg_escape_identifier($column);
		}
		return $this->query("CREATE INDEX " . pg_escape_identifier("idx_{$table}_" . implode("_", $columns)) . " ON " . pg_escape_identifier($table) . " USING btree(" . implode(',', $c) . ")");
	}

	/**
	 * Drop index
	 * @param $table
	 * @param $columns
	 * @return postgres
	 */
	public function drop_index($table, $columns) {
		return $this->query("DROP INDEX " . pg_escape_identifier("idx_{$table}_" . implode("_", $columns)));
	}

	/**
	 * Изменение имени колонки
	 * @param $table
	 * @param $column
	 * @param $new_name
	 */
	public function rename_column($table, $column, $new_name) {
		$this->query("ALTER TABLE " . pg_escape_identifier($table) . " RENAME " . pg_escape_identifier($column) . " TO " . pg_escape_identifier($new_name) . ";");
	}

	/**
	 * Добавление колонки
	 * @param $table
	 * @param $column
	 * @param $type
	 */
	public function add_column($table, $column, $type) {
		$this->query("ALTER TABLE " . pg_escape_identifier($table) . " ADD COLUMN " . pg_escape_identifier($column) . " {$type};");
	}

	/**
	 * Удаление колонки
	 * @param $table
	 * @param $column
	 */
	public function drop_column($table, $column) {
		$this->query("ALTER TABLE " . pg_escape_identifier($table) . " DROP COLUMN " . pg_escape_identifier($column) . ";");
	}

	/**
	 * Drop table
	 * @param $table
	 * @return \Model
	 */
	public function drop_table($table) {
		return $this->query("BEGIN;
		DROP TABLE IF EXISTS " . pg_escape_identifier($table) . ";
		DROP SEQUENCE IF EXISTS " . pg_escape_identifier($table . "_id_seq") . ";
		COMMIT;");
	}

	/**
	 * Выбор всех записей в таблице по запросу
	 * @param string $table Имя таблицы
	 * @param string $where
	 * @param null $column
	 * @param null $order
	 * @param null $limit
	 * @return postgres
	 */
	public function select($table, $where = null, $column = null, $order = null, $limit = null) {
		return $this->query('SELECT ' . ($column === null ? '*' : $column) . ' FROM ' . pg_escape_identifier($table) . ($where ? ' WHERE ' . $where : '') . ($order ? " ORDER BY " . $order : "") . ($limit ? " LIMIT " . $limit : ""));
	}

	/**
	 * Чтение 1 записи, возврат объекта
	 * @param null $i
	 * @return object
	 */
	public function row($i = null) {

		//Если значение больше, чем строк в запросе, выходим
		if( $i >= pg_num_rows($this->result) ) {
			return false;
		}

		//Результат возврата
		$return = pg_fetch_object($this->result, $i);
		if( $return == false ) {
			return $return;
		}

		//Прооходим по колонкам, собираем массив
		foreach( $return as $column => $value ) {

			//Получаем номер столбца
			$num = pg_field_num($this->result, $column);

			//Если значение нулевое, идём дальше
			if( pg_field_is_null($this->result, $i, $num) ) {
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
	 * Получить строку представления для запроса по типу данного value
	 * @param bool|array|null|int|string $value
	 * @return string
	 */
	public function getStringQueryByValue($value) {
		if( $value instanceof DB_Expr ) {
			return $value;
		}
		if( is_bool($value) ) {
			return $value ? "TRUE" : "FALSE";
		}
		if( is_array($value) ) {
			foreach($value as $k => $v) {
				$value[$k] = $this->getStringQueryByValue($v);
			}
			return implode(',', $value);
		}
		return is_int($value) || is_null($value) ? (is_null($value) ? 'NULL' : $value) : ("'" . pg_escape_string($value) . "'");
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
			$q .= ($q != '' ? ', ' : '') . pg_escape_identifier($key) . '=' . $this->getStringQueryByValue($v);
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
			$q .= ($q != '' ? ', ' : '') . $this->getStringQueryByValue($v);
		}
		return array('insert' => $q, 'values' => $i);

	}

	/**
	 * Обновить данные таблицы из массива
	 * @param string $table
	 * @param array $data
	 * @param string $where
	 * @return bool|int <type>
	 */
	public function update($table, $data, $where = null) {

		$q = $this->getUpdateStringByArray($data);
		//Если данных в массиве не было, выходим
		if( $q === false ) {
			return false;
		}

		$this->query("UPDATE " . pg_escape_identifier($table) . " SET $q" . ($where ? ' WHERE ' . $where : ''));
		return $this->affected_rows();

	}

	public function insert($table, array $data, $pkey = null) {

		$q = $this->getInsertStringByArray($data);
		//Если данных в массиве не было, выходим
		if( $q === false ) {
			return false;
		}

		$id = $this->query("INSERT INTO " . pg_escape_identifier($table) . " (" . $q['values'] . ")VALUES(" . $q['insert'] . ")" . ($pkey ? " RETURNING " . pg_escape_identifier($pkey) : "") . ";")->row();
		if( $id ) {
			return $id->$pkey;
		} else {
			return false;
		}
	}

	/**
	 * Удаление
	 * @param $table
	 * @param $column
	 * @param int $id
	 * @return void
	 */
	public function delete($table, $column, $id) {
		$this->query("DELETE FROM " . pg_escape_identifier($table) . " WHERE " . $this->escape_identifier($column) . " = " . pg_escape_literal($id) . ";");
	}
}