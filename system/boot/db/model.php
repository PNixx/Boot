<?
//@todo Написать эксепшены
class Model {

	/**
	 * @var mysql|postgres
	 */
	private $_db = null;

	private $_select = null;
	protected $table = null;

	public function __construct() {

		//Получаем имя драйвера
		$db = Boot::getInstance()->config->db;
		$driver = $db->adapter;
		$host = $db->host;
		$port = $db->port;
		$user = $db->user;
		$pass = $db->password;
		$dbase = $db->dbase;

		//Инитим драйвер
		$this->_db = new $driver($host, $port, $user, $pass, $dbase);

		//Подключаемся к базе
		$this->connect();
	}

	/**
	 * Подключаемся к БД
	 * @return void
	 */
	public function connect() {

		//Проверяем было ли подключение
		if( Boot::getInstance()->_connect === null ) {
			Boot::getInstance()->_connect = $this->_db->connect();
		}
	}

	/**
	 * SQL query
	 * @param	$query
	 * @return Model
	 */
	public function query($query) {
		$this->_select = $this->_db->query($query);
		return $this;
	}

	public function show_tables() {
		return $this->_db->show_tables($this->table);
	}

	/**
	 * Create table
	 * @param $table
	 * @param $column
	 */
	public function create_table($table, $column, $pkey = null) {
		return $this->_db->create_table($table, $column, $pkey);
	}

	/**
	 * Поиск записей по условию, используя db_table
	 * @param null $where
	 * @return Model
	 */
	public function find($where = null, $colum = null, $order = null, $limit = null) {
		return $this->select($this->table, $where, $colum, $order, $limit);
	}

	/**
	 * Выбор всех записей в таблице по запросу
	 * @param string $table Имя таблицы
	 * @param string $where
	 * @return Model
	 */
	public function select($table, $where = null, $colum = null, $order = null, $limit = null) {
		$this->_select = $this->_db->select($table, $where, $colum, $order, $limit);
		return $this;
	}

	/**
	 * Чтение 1 записи, возврат объекта
	 * @return dbrow
	 */
	public function row() {
		try {
			$exist = class_exists(get_class($this) . "_Row");

			if( $exist ) {

				//Имя класса
				$class = get_class($this) . "_Row";

				//Получае строку
				$row = $this->_select->row();

				//Если получили строку
				if( $row ) {
					return new $class($row, $this->table);
				} else {
					return false;
				}
			} else {
				return $this->_select->row();
			}

		} catch( Exception $e ) {
			return $this->_select->row();
		}
	}

	/**
	 * Получить по ID
	 * @param $id
	 * @return Model_User_Row
	 */
	public function getById($id) {
		if( (int)$id < 1 ) {
			return false;
		}

		return $this->find('id = ' . $id)->row();
	}

	/**
	 * Чтение записей, возврат массива
	 * @return array
	 */
	public function row_all() {
		$r = array();
		while( $line = $this->row() ) {
			$r[] = $line;
		}
		return $r;
	}

	/**
	 * Чтение 1 записи, возврат массива
	 * @return array
	 */
	public function read() {
		return $this->_db->read();
	}

	/**
	 * Чтение записей, возврат массива
	 * @return array
	 */
	public function read_all() {
		$r = array();
		while( $line = $this->read() ) {
			$r[] = $line;
		}
		return $r;
	}

	/**
	 * Считывает из запроса первую колонку таблицы
	 * @return array
	 */
	public function read_cols() {
		return $this->_db->read_cols();
	}

	/**
	 * Чтение кол-ва записай в запросе
	 * @return integer
	 */
	public function num_rows() {
		return $this->_db->num_rows();
	}

	/**
	 * Кол-во затронутых сток в запросе
	 * @return integer
	 */
	public function affected_rows() {
		return $this->_db->affected_rows();
	}

	/**
	 * Обновить данные таблицы из массива
	 * @param string $table
	 * @param array $data
	 * @param string $where
	 * @return <type>
	 */
	public function update($data, $where = null) {

		return $this->_db->update($this->table, $data, $where);
	}

	/**
	 * Добавить строку в таблицу
	 * @param	$table
	 * @param array $data
	 * @return void
	 */
	public function insert(array $data) {

		return $this->_db->insert($this->table, $data);
	}

	/**
	 * Кол-во строк по колонке
	 * @param $colum
	 * @return void
	 */
	public function count($where = null) {
		return $this->query("SELECT count(1) AS id FROM {$this->table} " . ($where ? "WHERE " . $where : ""))->row()->id;
	}

	/**
	 * Удаление
	 * @param $id
	 * @return void
	 */
	public function delete($id) {
		$this->_db->delete($this->table, $id);
	}

	/**
	 * Создание строки
	 * @param $data
	 */
	public function create($data) {
		return new Model_Row((object)$data, $this->table, true);
	}
}