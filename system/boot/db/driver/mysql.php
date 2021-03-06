<?
/**
 * MySQL
 */
class mysql {

	private $_host = null;

	private $_port = null;

	private $_user = null;

	private $_pass = null;

	private $_dbase = null;

	public $separator = "`";
	public $int_separator = "";

	/**
	 * Ссылка на подключение
	 * @var mysqli
	 */
	private $_connect = null;

	/**
	 * Результат запроса
	 * @var null
	 */
	private $result = null;

	/**
	 * Вложенность транзакций
	 * @var int
	 */
	private $_transaction = 0;

	/**
	 * @param $host
	 * @param $port
	 * @param $user
	 * @param $pass
	 * @param $dbase
	 */
	public function __construct($host, $port, $user, $pass, $dbase) {

		$this->_host = $host;
		$this->_port = $port;
		$this->_user = $user;
		$this->_pass = $pass;
		$this->_dbase = $dbase;
	}

	private function error($query = null) {
		throw new Exception(mysqli_error($this->_connect) . ($query ? ", query: " . $query : "") . "\n", 500);
	}

	/**
	 * Подключаемся к БД
	 * @return mysqli|null
	 * @throws Exception
	 */
	public function connect() {

		//Запоминаем время начала
		$time = Boot::mktime();

		//Коннектимся к БД
		$this->_connect = mysqli_connect($this->_host, $this->_user, $this->_pass, $this->_dbase, $this->_port) or $this->error();
		mysqli_query($this->_connect, "SET NAMES utf8") or $this->error();

		//Debug
		Boot::getInstance()->debug("  \x1b[35mMySQL (" . Boot::check_time($time) . "ms)\x1b[0m {$this->_host}:{$this->_port}");

		//Возвращаем коннект
		return $this->_connect;
	}

	public function escape_identifier($name) {
		return "{$this->separator}{$name}{$this->separator}";
	}

	/**
	 * Запускает транзакцию
	 * @return bool
	 */
	public function begin_transaction() {
		if( $this->_transaction == 0 ) {
			$this->query("BEGIN");
		}
		$this->_transaction++;
	}

	public function commit() {
		$this->_transaction--;
		if( $this->_transaction == 0 ) {
			$this->query("COMMIT");
		}
	}

	/**
	 * Отмена транзакции
	 */
	public function rollback() {
		$this->query("ROLLBACK");
		$this->_transaction = 0;
	}

	/**
	 * SQL query
	 * @param	$query
	 * @return mysql
	 */
	public function query($query) {

		//Запоминаем время начала
		$time = Boot::mktime();

		//Делаем запрос
		$this->result = mysqli_query($this->_connect, $query) or $this->error($query);

		//Debug
		Boot::getInstance()->debug("  \x1b[36mSQL (" . Boot::check_time($time) . "ms)\x1b[0m " . $query);

		//Return
		return $this;
	}

	public function show_tables($table) {
		return $this->query("SHOW TABLES LIKE '" . $table . "'")->read();
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
			$sql .= ($sql == "" ? "" : ",") . "`{$col}` {$data}";
		}
		$sql_pkey = "";
		if( $pkey ) {
			$sql_pkey = ", PRIMARY KEY ({$this->separator}{$pkey}{$this->separator})";
		}
		$sql_ukey = "";
		if( $ukey ) {
			foreach($ukey as $key) {
				$sql_ukey .= ($sql_ukey == "" ? "" : ",") . $this->separator . $key . $this->separator;
			}
			$sql_ukey = ", UNIQUE INDEX {$this->separator}ukey_{$table}_" . implode("_", $ukey) . "{$this->separator} ({$sql_ukey})";
		}
		$this->query("CREATE TABLE {$this->separator}{$table}{$this->separator} ({$sql}{$sql_pkey}{$sql_ukey});");
	}

	/**
	 * Create index
	 *
	 * @param string $table
	 * @param array  $columns
	 *
	 * @return \Model
	 */
	public function create_index($table, $columns) {
		$c = [];
		foreach( $columns as $column ) {
			$c[] = $this->separator . $column . $this->separator;
		}
		return $this->query("ALTER TABLE " . $this->separator . $table . $this->separator . " ADD INDEX " . $this->separator . "idx_{$table}_" . implode("_", $columns) . $this->separator . " (" . implode(',', $c) . ")");
	}

	/**
	 * Drop index
	 * @param $table
	 * @param $columns
	 * @return mysql
	 */
	public function drop_index($table, $columns) {
		return $this->query("ALTER TABLE " . $this->separator . $table . $this->separator . " DROP INDEX " . $this->separator . "idx_{$table}_" . implode("_", $columns) . $this->separator);
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
		return $this->query("DROP TABLE {$this->separator}{$table}{$this->separator}");
	}

	/**
	 * Выбор всех записей в таблице по запросу
	 * @param string $table Имя таблицы
	 * @param string $where
	 * @return Model
	 */
	public function select($table, $where = null, $colum = null, $order = null, $limit = null) {
		return $this->query('SELECT ' . ($colum === null ? '*' : $colum) . ' FROM `' . $table . '`' . ($where ? ' WHERE ' . $where : '') . ($order ? " ORDER BY " . $order : "") . ($limit ? " LIMIT " . $limit : ""));
	}

	/**
	 * Чтение 1 записи, возврат объекта
	 * @return stdClass
	 */
	public function row() {
		return mysqli_fetch_object($this->result);
	}

	/**
	 * Чтение 1 записи, возврат массива
	 * @return array
	 */
	public function read() {
		return mysqli_fetch_assoc($this->result);
	}

	/**
	 * Считывает из запроса первую колонку таблицы
	 * @return array
	 */
	public function read_cols() {
		$r = array();
		while( $line = mysqli_fetch_array($this->result, MYSQLI_NUM) ) {
			$r[] = $line[0];
		}
		return $r;
	}

	/**
	 * Чтение кол-ва записай в запросе
	 * @return integer
	 */
	public function num_rows() {
		return mysql_num_rows($this->result);
	}

	/**
	 * Кол-во затронутых сток в запросе
	 * @return integer
	 */
	public function affected_rows() {
		return mysqli_affected_rows($this->_connect);
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

			$q .= ($q != '' ? ', ' : '') . '`' . $key . '`=' . (is_int($v) || is_null($v) ? (is_null($v) ? 'NULL' : $v)
							: "'$v'");
		}
		return $q;

	}

	/**
	 * Преобразовывает массив, в строку для INPUT, возвращает ключи и параметры в массиве
	 * @param array $data
	 * @param null  $pkey
	 * @return array ('insert','values')
	 */
	public function getInsertStringByArray(array $data, $pkey = null) {
		//Если ни каких данных не передали, выходим
		if( count($data) == 0 ) {
			return false;
		}

		$q = '';
		$i = '';
		foreach( $data as $key => $v ) {
			//Ключи VALUES
			$i .= ($i != '' ? ',' : '') . '`' . $key . '`';
			//Ключи INSERT
			$q .= ($q != '' ? ', ' : '') . ($pkey && $key == $pkey ? "LAST_INSERT_ID(" . $this->getStringQueryByValue($v) . ")" : $this->getStringQueryByValue($v));
		}
		return array('insert' => $q, 'values' => $i);

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
		return (is_int($value) || is_null($value) ? (is_null($value) ? 'NULL' : addslashes($value)) : "'" . mysqli_real_escape_string($this->_connect, $value) . "'");
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

		$this->query("UPDATE `$table` SET $q" . ($where ? ' WHERE ' . $where : ''));
		return $this->affected_rows();

	}

	/**
	 * @param       $table
	 * @param array $data
	 * @param null  $pkey
	 * @return bool|int|string
	 */
	public function insert($table, array $data, $pkey = null) {

		$q = $this->getInsertStringByArray($data, $pkey);
		//Если данных в массиве не было, выходим
		if( $q === false ) {
			return false;
		}

		$this->query("INSERT INTO " . $this->escape_identifier($table) . " (" . $q['values'] . ")VALUES(" . $q['insert'] . ");");
		return mysqli_insert_id($this->_connect);
	}

	/**
	 * Удаление
	 * @param $table
	 * @param $column
	 * @param $id
	 * @return void
	 */
	public function delete($table, $column, $id) {
		$this->query("DELETE FROM " . $this->escape_identifier($table) . " WHERE " . $this->escape_identifier($column) . " = {$id};");
	}
}