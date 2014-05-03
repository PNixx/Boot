<?

/**
 * Class Model
 * @deprecated
 */
class Model {

	/**
	 * @var mysql|postgres
	 */
	private $_db = null;

	/**
	 * @var postgres|mysql
	 */
	private $_select = null;

	/**
	 * Рабочая таблица модели
	 * @var null||string
	 */
	protected $table = null;

	/**
	 * Связь с таблицами
	 * Многие к одному
	 * @var null|array
	 */
	protected $belongs_to = null;

	/**
	 * Связь с таблицами
	 * Один ко многим
	 * @var null|array([table])
	 */
	protected $has_many = null;

	/**
	 * Первичный ключ
	 * @var null|string
	 */
	protected $pkey = "id";

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
	 * Получает первичный ключ таблицы
	 */
	public function getPkey() {
		if( $this->pkey ) {
			return $this->pkey;
		} else {
			throw new DB_Exception("pkey is not found");
		}
	}

	/**
	 * Подключаемся к БД
	 * @return void
	 */
	public function connect() {

		//Проверяем было ли подключение
		Boot::getInstance()->_connect = $this->_db->connect();
	}

	/**
	 * SQL query
	 * @param	$query
	 * @return Model
	 */
	public function query($query) {
		$this->_select = $this->_db->query((string)$query);
		return $this;
	}

	/**
	 * Показать список таблиц
	 * @return array
	 */
	public function show_tables() {
		return $this->_db->show_tables($this->table);
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
		return $this->_db->create_table($table, $column, $pkey, $ukey);
	}

	/**
	 * Изменение имени колонки
	 * @param $column
	 * @param $new_name
	 * @internal param $table
	 */
	public function rename_column($column, $new_name) {
		$this->_db->rename_column($this->table, $column, $new_name);
	}

	/**
	 * Добавление колонки
	 * @param $column
	 * @param $type
	 * @internal param $table
	 */
	public function add_column($column, $type) {
		$this->_db->add_column($this->table, $column, $type);
	}

	/**
	 * Добавление колонки
	 * @param $column
	 * @internal param $table
	 */
	public function drop_column($column) {
		$this->_db->drop_column($this->table, $column);
	}

	/**
	 * Drop table
	 * @param $table
	 * @return \Model
	 */
	public function drop_table($table) {
		return $this->_db->drop_table($table);
	}

	/**
	 * Поиск записей по первичному ключу
	 * @param int $id
	 * @return Model_Row
	 */
	public function find($id) {
		if( (int)$id < 1 ) {
			return false;
		}
		return $this->query($this->select(array($this->getPkey() => $id)))->row();
	}

	/**
	 * Поиск записей по первичному ключу
	 * @param int $id
	 * @return Model_Row
	 */
	public function find_array($id) {
		if( (int)$id < 1 ) {
			return false;
		}
		return $this->query($this->select(array($this->getPkey() => $id)))->read();
	}

	/**
	 * Поиск записей по условию key => value
	 * @param string|array $where
	 * @return \Model
	 */
	public function where($where = null) {
		return $this->query($this->select($where));
	}

	/**
	 * Выбор всех записей в таблице по запросу
	 * @param string|array $where
	 * @param string|array $column
	 * @param null $order
	 * @param null $limit
	 * @internal param string $table Имя таблицы
	 * @return Select
	 */
	public function select($where = null, $column = null, $order = null, $limit = null) {
		return new Select($this->table, $where, $column, $order, $limit);
	}

	/**
	 * Чтение 1 записи, возврат объекта
	 * @param null $i
	 * @return Model_Row
	 */
	public function row($i = null) {

		//Имя класса
		$class = class_exists(get_class($this) . "_Row") ? get_class($this) . "_Row" : "Model_Row";

		//Получае строку
		$row = $this->_select->row($i);

		//Если получили строку
		if( $row ) {
			return new $class($row, $this->table, $this->belongs_to, $this->has_many, $this->pkey, $this);
		} else {
			return false;
		}
	}

	/**
	 * Чтение записей, возврат массива
	 * @return Model_Collection
	 */
	public function row_all() {
		$r = array();
		$i = 0;
		while( $line = $this->_select->row($i++) ) {
			$r[] = $line;
		}

		//Если ничего не найдено
		if( count($r) == 0 ) {
			return null;
		}

		//Строим класс коллекции
		$class = class_exists(get_class($this) . "_Collection") ? get_class($this) . "_Collection" : "Model_Collection";
		$class_row = class_exists(get_class($this) . "_Row") ? get_class($this) . "_Row" : "Model_Row";

		//Возвращаем данные
		return new $class($this->table, $this->belongs_to, $this->has_many, $this->pkey, $class_row, $this, $r);
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
	 * @param array $data
	 * @param string $where
	 * @internal param string $table
	 * @return bool|int <type>
	 */
	public function update($data, $where = null) {

		return $this->_db->update($this->table, $data, $where);
	}

	/**
	 * Получить строку представления для запроса по типу данного value
	 * @param $value
	 * @return string
	 */
	public function getStringQueryByValue($value) {
		return $this->_db->getStringQueryByValue($value);
	}

	/**
	 * Добавить строку в таблицу
	 * @param array $data
	 * @return int
	 */
	public function insert(array $data) {

		$id = $this->_db->insert($this->table, $data, $this->pkey);
//		if( $id == false ) {
//			throw new DB_Exeption("Error insert value");
//		}
		return $id;
	}

	/**
	 * Кол-во строк по колонке
	 * @param null $where
	 * @internal param $colum
	 * @return int
	 */
	public function count($where = null) {
		return $this->query("SELECT count(1) AS id FROM {$this->table} " . ($where ? "WHERE " . $where : ""))->row()->id;
	}

	/**
	 * Удаление по первичному ключу или по группе ключей с условием
	 * @param int|array $id
	 * @throws DB_Exception
	 * @return void
	 */
	public function delete($id) {

		//Если передан массив
		if( is_array($id) ) {

			//Собираем в строку запрос
			$where = "";
			foreach($id as $k => $v) {
				$where .= ($where == "" ? "" : " AND ") . $this->_db->separator . $k . $this->_db->separator . " = " . $this->getStringQueryByValue($v);
			}

			if( $where ) {
				$this->query("DELETE FROM {$this->_db->separator}{$this->table}{$this->_db->separator} WHERE {$where};");
			}
		} else {
			if( (int)$id < 1 ) {
				throw new DB_Exception("Wrong pkey value");
			}
			$this->query("DELETE FROM {$this->_db->separator}{$this->table}{$this->_db->separator} WHERE {$this->_db->separator}{$this->pkey}{$this->_db->separator} = " . $this->getStringQueryByValue($id) . ";");
		}
	}

	/**
	 * Создание строки
	 * @param $data
	 * @return Model_Row
	 */
	public function create($data = array()) {
		if( class_exists(get_class($this) . "_Row") ) {
			$class = get_class($this) . "_Row";
		} else {
			$class = "Model_Row";
		}
		return new $class((object)$data, $this->table, $this->belongs_to, $this->has_many, $this->pkey, $this, true);
	}
}