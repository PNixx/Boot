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
	 * Результат запроса
	 * @var null
	 */
	private $result = null;

	public function __construct($host, $port, $user, $pass, $dbase) {

		$this->_host = $host;
		$this->_port = $port;
		$this->_user = $user;
		$this->_pass = $pass;
		$this->_dbase = $dbase;
	}

	static private function error($query = null) {
		throw new Exception(mysql_error() . ($query ? ", query: " . $query : "") . "\n", 500);
	}

	/**
	 * Подключаемся к БД
	 * @return void
	 */
	public function connect() {

		if( Boot::getInstance()->_connect === null ) {
			$connect = mysql_connect($this->_host . ":" . $this->_port, $this->_user, $this->_pass) or self::error();
			mysql_select_db($this->_dbase, $connect) or self::error();
			mysql_query("SET NAMES utf8", $connect) or self::error();

			return $connect;
		} else {
			return Boot::getInstance()->_connect;
		}
	}

	/**
	 * SQL query
	 * @param	$query
	 * @return Model
	 */
	public function query($query) {
		$this->result = mysql_query($query) or self::error($query);
		return $this;
	}

	public function show_tables($table) {
		return $this->query("SHOW TABLES LIKE '" . $table . "'")->read();
	}

	/**
	 * Create table
	 * @param $table
	 * @param $column
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
		return $this->query("CREATE TABLE {$this->separator}{$table}{$this->separator} ({$sql}{$sql_pkey}{$sql_ukey});");
	}

	/**
	 * Drop table
	 * @param $table
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
	 * @return dbrow
	 */
	public function row() {
		return mysql_fetch_object($this->result);
	}

	/**
	 * Чтение 1 записи, возврат массива
	 * @return array
	 */
	public function read() {
		return @mysql_fetch_assoc($this->result);
	}

	/**
	 * Считывает из запроса первую колонку таблицы
	 * @return array
	 */
	public function read_cols() {
		$r = array();
		while( $line = @mysql_fetch_array($this->result, MYSQL_NUM) ) {
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
		return @mysql_affected_rows();
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
			$i .= ($i != '' ? ',' : '') . '`' . $key . '`';
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

		$this->query("UPDATE `$table` SET $q" . ($where ? ' WHERE ' . $where : ''));
		return $this->affected_rows();

	}

	public function insert($table, array $data) {

		$q = $this->getInsertStringByArray($data);
		//Если данных в массиве не было, выходим
		if( $q === false ) {
			return false;
		}

		$this->query("INSERT INTO `$table` (" . $q['values'] . ")VALUES(" . $q['insert'] . ");");
		return mysql_insert_id();
	}

	/**
	 * Удаление
	 * @param $table
	 * @param $id
	 * @return void
	 */
	public function delete($table, $id) {
		$this->query("DELETE FROM `{$table}` WHERE id = {$id};");
	}
}