<?php
/**
 * User: nixx
 * Date: 15.04.14
 * Time: 15:49
 * @property int $id
 * @property int $date
 */
abstract class ActiveRecord {

	/**
	 * Связь с таблицами
	 * Многие к одному
	 * @var null|array
	 */
	protected static $belongs_to = array();

	/**
	 * Связь с таблицами
	 * Один ко многим
	 * @var DBLinks[]
	 */
	protected static $has_many = array();

	/**
	 * Первичный ключ
	 * @var null|string
	 */
	protected static $pkey = "id";

	/**
	 * Сортировка по умолчанию
	 * @var null
	 */
	protected static $default_order = null;

	/**
	 * Хранилище для кеширования объектов belongs_to
	 * @var array
	 */
	private $_cached = array();

	/**
	 * Кеширование запросов для поиска через find
	 * @var array
	 */
	static private $_cached_by_find = array();

	/**
	 * Хранилище данных
	 * @var array
	 */
	private $_row = array();

	/**
	 * Хранилище обновляемых данных
	 * @var array
	 */
	private $_row_update = array();

	/**
	 * Новая строка?
	 * @var bool
	 */
	private $_new_record;

	/**
	 * Набор условий для поиска
	 * @var Select
	 */
	private static $select = null;

	/**
	 * Конструктор класса
	 */
	public function __construct($row = null, $new_record = false) {

		//Сохраняем данные
		$this->_row = (object)$row;
		$this->_new_record = $new_record;
	}

	/**
	 * Получение первичного ключа
	 * @return string
	 */
	private static function getPKey() {
		return DB::getDB()->escape_identifier(static::$pkey);
	}

	/**
	 * Получение имени таблицы
	 * @return string
	 */
	protected static function getTable() {
		return strtolower(str_ireplace("Model_", "", get_called_class()));
	}

	/**
	 * Создание строки после поиска
	 * @param $row
	 * @return static
	 */
	private static function createRow($row) {

		//Получаем имя вызываемого класса
		$class = get_called_class();

		//Создаем экземплятор
		return new $class($row, false);
	}

	/**
	 * Миграция после обновления
	 */
	private function merge_after_update() {
		foreach( $this->_row_update as $key => $value ) {
			$this->_row->$key = $value;
		}
		$this->_row_update = array();
	}

	// ------------------- Public ------------------>

	/**
	 * Получаем данные
	 * @param $name
	 * @return string
	 */
	public function __get($name) {
		if( isset($this->_row->$name) ) {
			return $this->_row->$name;
		} else {
			return false;
		}
	}

	/**
	 * Запрос функции
	 * @param $name
	 * @param $params
	 * @throws Exception
	 * @return string|ActiveRecord[]|ActiveRecord|Model_Collection
	 */
	public function __call($name, $params) {

		if( preg_match("/^get([A-Z].*?)$/", $name, $match) ) {

			//Получаем ключ
			$key = strtolower(preg_replace("/(?!^)([A-Z])/", "_$1", $match[1]));

			//Если есть в связях таблицы belongs_to
			if( array_key_exists($key, static::$belongs_to) || in_array($key, static::$belongs_to) ) {

				/**
				 * Строим класс
				 * @var $class ActiveRecord
				 */
				$class = "Model_" . ucfirst($key);

				//Строим конструктор
				$db_links = new DBLinks(static::getTable(), $key, array_key_exists($key, static::$belongs_to) ? static::$belongs_to[$key] : []);

				//Если нет данных в кеше
				if( isset($this->_cached[$key]) == false ) {
					//Получаем строку
					if( $this->{$db_links->foreign_key} > 0 ) {
						$this->_cached[$key] = $class::find($this->{$db_links->foreign_key});
						return $this->_cached[$key];
					}
				} else {
					//Отдаем строку
					return $this->_cached[$key];
				}
				return null;
			}

			//Если есть в переменной
			if( array_key_exists(strtolower($match[1]), $this->_row) ) {
				return $this->{strtolower($match[1])};
			}
		}

		//Связь с has_many
		if( preg_match("/^list([A-Z].*?)$/", $name, $match) ) {

			//Получаем ключ
			$key = strtolower(preg_replace("/(?!^)([A-Z])/", "_$1", $match[1]));

			//Проверяем условие совпадения
			if( array_key_exists($key, static::$has_many) ) {

				/**
				 * Строим класс
				 * @var $class ActiveRecord
				 */
				$class = "Model_" . ucfirst($key);

				//Строим конструктор
				$db_links = new DBLinks($key, static::getTable(), static::$has_many[$key]);

				//Получаем строки
				return $class::where([$db_links->foreign_key => $this->id])->all();
			}
		}
		throw new Exception("Функция {$name} не определена");
	}

	/**
	 * Создание строки
	 * @param $row
	 * @return static
	 */
	public static function create($row = array()) {

		//Получаем имя вызываемого класса
		$class = get_called_class();

		//Создаем экземплятор
		return new $class($row, true);
	}

	/**
	 * Вставка строки
	 * @param $row
	 * @return int
	 */
	public static function insert($row = array()) {

		//Получаем имя вызываемого класса
		$object = static::create($row);
		$object->save();

		//Создаем экземплятор
		return $object->id;
	}

	/**
	 * @return static
	 * @throws DB_Exception
	 */
	public static function row() {
		if( self::$select == null ) {
			throw new DB_Exception("Non select constructor");
		}

		//Выполняем запрос
		$result = DB::getDB()->query(self::$select);
		self::$select = null;

		//Получаем строку
		$row = $result->row();

		//Создаем экземплятор
		if( $row ) {
			return self::createRow($row);
		}
		return null;
	}

	/**
	 * @return array
	 * @throws DB_Exception
	 */
	public static function read_cols() {
		if( self::$select == null ) {
			throw new DB_Exception("Non select constructor");
		}

		//Выполняем запрос
		$result = DB::getDB()->query(self::$select);
		self::$select = null;

		//Получаем строку
		return $result->read_cols();
	}

	/**
	 * @return array
	 * @throws DB_Exception
	 */
	public static function read_all() {
		if( self::$select == null ) {
			throw new DB_Exception("Non select constructor");
		}

		//Выполняем запрос
		$result = DB::getDB()->query(self::$select);
		self::$select = null;

		$rows = array();
		while( $line = $result->read() ) {
			$rows[] = $line;
		}

		//Получаем строку
		return $rows;
	}

	/**
	 * @return static[]|Model_Collection
	 * @throws DB_Exception
	 */
	public static function all() {
		if( self::$select == null ) {
			self::$select = new Select(static::getTable(), null, null, static::$default_order);
		}

		//Выполняем запрос
		$result = DB::getDB()->query(self::$select);
		self::$select = null;

		$rows = array();
		$i = 0;
		while( $line = $result->row($i++) ) {
			$rows[] = $line;
		}

		//Возвращаем коллекцию
		return new Model_Collection(get_called_class(), $rows);
	}

	/**
	 * Обычный, чистый запрос к БД
	 * @param $query
	 * @return static
	 */
	public static function query($query) {

		//Выполняем запрос
		self::$select = $query;

		//Возвращаем ту же функцию
		return new static();
	}

	/**
	 * Постраничное получение данных
	 * @param $page
	 * @param $limit
	 * @return static
	 */
	public static function page($page, $limit) {
		return self::limit($limit, $page * $limit);
	}

	/**
	 * Условие выборки
	 * @param string|array $where
	 * @return static
	 */
	public static function where($where) {

		//Инициализируем
		if( self::$select === null ) {
			self::$select = new Select(static::getTable(), null, null, static::$default_order);
		}

		//Добавляем условие
		self::$select->where($where);

		//Возвращаем ту же функцию
		return new static();
	}

	/**
	 * @param string $table
	 * @param string|array|null $on
	 * @return static
	 */
	public static function joins($table, $on = null) {

		//Инициализируем
		if( self::$select === null ) {
			self::$select = new Select(static::getTable(), null, new DB_Expr(static::getTable() . ".*"), static::$default_order);
		}

		//Добавляем join
		self::$select->joins($table, $on);

		//Возвращаем ту же функцию
		return new static();
	}

	/**
	 * Выборка с поиском ILIKE
	 * @param $column
	 * @param $value
	 * @return static
	 */
	public static function iLike($column, $value) {

		//Инициализируем
		if( self::$select === null ) {
			self::$select = new Select(static::getTable(), null, null, static::$default_order);
		}

		//Добавляем условие
		self::$select->where(DB::getDB()->escape_identifier($column) . " ILIKE " . DB::getDB()->getStringQueryByValue($value));

		//Возвращаем ту же функцию
		return new static();
	}

	/**
	 * Выборка колонок
	 * @param array|string $column
	 * @return static
	 */
	public static function column($column) {

		//Инициализируем
		if( self::$select === null ) {
			self::$select = new Select(static::getTable(), null, null, static::$default_order);
		}

		//Добавляем колонки выборки
		self::$select->column($column);

		//Возвращаем ту же функцию
		return new static();
	}

	/**
	 * Сортировка
	 * @param string $order_by
	 * @return static
	 */
	public static function order($order_by) {

		//Инициализируем
		if( self::$select === null ) {
			self::$select = new Select(static::getTable());
		}

		//Добавляем колонки выборки
		self::$select->order($order_by);

		//Возвращаем ту же функцию
		return new static();
	}

	/**
	 * Сортировка
	 * @param $limit
	 * @param int $offset
	 * @return static
	 */
	public static function limit($limit, $offset = 0) {

		//Инициализируем
		if( self::$select === null ) {
			self::$select = new Select(static::getTable(), null, null, static::$default_order);
		}

		//Добавляем колонки выборки
		self::$select->limit($limit, $offset);

		//Возвращаем ту же функцию
		return new static();
	}

	/**
	 * Сортировка
	 * @param $where
	 * @return int
	 */
	public static function count($where = null) {

		//Если есть условие
		if( $where != null ) {
			self::where($where);
		}

		//Добавляем колонки выборки
		self::column(new DB_Expr("count(1) AS c"));
		self::order(null);

		//Возвращаем ту же функцию
		return self::row()->c;
	}

	/**
	 * Показать список таблиц
	 * @return array
	 */
	static public function show_tables() {
		return DB::getDB()->show_tables(static::getTable());
	}

	/**
	 * Create table
	 * @param $table
	 * @param $column
	 * @param null $pkey
	 * @param null $ukey
	 * @return static
	 */
	static public function create_table($table, $column, $pkey = null, $ukey = null) {
		return DB::getDB()->create_table($table, $column, $pkey, $ukey);
	}

	/**
	 * Изменение имени колонки
	 * @param $column
	 * @param $new_name
	 */
	static public function rename_column($column, $new_name) {
		DB::getDB()->rename_column(static::getTable(), $column, $new_name);
	}

	/**
	 * Добавление колонки
	 * @param $column
	 * @param $type
	 */
	static public function add_column($column, $type) {
		DB::getDB()->add_column(static::getTable(), $column, $type);
	}

	/**
	 * Добавление колонки
	 * @param $column
	 */
	static public function drop_column($column) {
		DB::getDB()->drop_column(static::getTable(), $column);
	}

	/**
	 * Drop table
	 * @param $table
	 * @return static
	 */
	static public function drop_table($table) {
		return DB::getDB()->drop_table($table);
	}

	/**
	 * Преобразование запроса
	 * @return Select
	 */
	public static function toSql() {

		//Инициализируем
		if( self::$select === null ) {
			self::$select = new Select(static::getTable(), null, null, static::$default_order);
		}
		$select = self::$select;
		self::$select = null;

		//Возвращем
		return $select;
	}

	/**
	 * Запуск транзакции
	 */
	public static function begin_transaction() {
		DB::getDB()->begin_transaction();
	}

	/**
	 * Завершение транзации
	 */
	public static function commit() {
		DB::getDB()->commit();
	}

	/**
	 * Отмена транзации
	 */
	public static function rollback() {
		DB::getDB()->rollback();
	}

	//------------- Select ----------------->

	/**
	 * Поиск по id
	 * @param $id
	 * @throws Exception
	 * @return static
	 */
	public static function find($id) {

		//Если есть в кеше
		if( isset(self::$_cached_by_find[static::getTable()][$id]) ) {
			$row = self::$_cached_by_find[static::getTable()][$id];
		} else {
			//Получаем строку из БД
			$row = DB::getDB()->select(static::getTable(), self::getPKey() . " = " . pg_escape_literal($id))->row();
			self::$_cached_by_find[static::getTable()][$id] = $row;

			//Если ничего не нашли
			if( $row == false ) {
				throw new DB_Exception("Record not found", 404);
			}
		}

		//Создаем экземплятор
		return self::createRow($row);
	}

	// ---------------- Row methods --------------------->

	/**
	 * Обновление данных строки
	 * @param $data
	 * @return bool|int
	 * @throws DB_Exception
	 */
	public function update($data) {

		//Проверяем не новая ли запись
		if( $this->_new_record ) {
			throw new DB_Exception("You don't use update method a new record, use save method");
		}
		if( count($data) == 0 ) {
			return true;
		}

		//Сохраняем обновляемые данные
		$this->_row_update = $data;

		//Пытаемся обновить
		$result = DB::getDB()->update(static::getTable(), $data, self::getPKey() . " = " . pg_escape_literal($this->{static::$pkey}));
		if( $result ) {

			//Изменяем данные строки
			$this->merge_after_update();
			return $result;
		}
		return false;
	}

	/**
	 * @return bool
	 * @throws DB_Exception
	 */
	public function save() {

		//Проверяем не новая ли запись
		if( $this->_new_record == false ) {
			return $this->update($this->_row_update);
		}

		//Добавляем строку
		$id = DB::getDB()->insert(static::getTable(), (array)$this->_row, static::$pkey);
		if( $id > 0 ) {
			$this->_row->id = $id;
			$this->_new_record = false;
			return true;
		}
		return false;
	}

	/**
	 * Уничтожение записи
	 */
	public function destroy() {
		try {
			//Запускаем транзакцию
			self::begin_transaction();

			//Проходим по связям
			foreach(static::$has_many as $table => $values) {

				/**
				 * Класс модели
				 * @var $class ActiveRecord
				 */
				$class = "Model_" . ucfirst($table);

				//Строим конструктор
				$db_links = new DBLinks($table, static::getTable(), $values);

				//Если нужно удалить строку
				if( $db_links->dependent == "destroy" ) {

					//Получаем строки
					$rows = $class::where(array($db_links->foreign_key => $this->{static::$pkey}))->all();
					foreach($rows as $row) {
						$row->destroy();
					}
				}

				//Если нужно обнулить значение
				if( $db_links->dependent == "nullify" ) {
					DB::getDB()->update($table, array($db_links->foreign_key => null), $db_links->foreign_key . " = " . $this->{static::$pkey});
				}
			}

			//Удаляем саму строку
			DB::getDB()->delete(static::getTable(), static::$pkey, $this->{static::$pkey});

			//Комитим изменения
			self::commit();

		} catch(Exception $e) {
			self::rollback();
			throw $e;
		}
	}

	/**
	 * Преобразоване в массив
	 * @return array
	 */
	public function toArray() {
		return (array)$this->_row;
	}
}

/**
 * Связь строки таблицы с другой таблицей
 * Class Belongs
 */

class DBLinks {

	public $table;
	public $class_name;
	public $foreign_key;
	public $dependent;

	/**
	 * Конструктор
	 */
	public function __construct($table, $table_parent, $values) {

		//Если не было указано имени класса, создаем из текущей таблицы
		if( isset($values["class_name"]) == false ) {
			$values["class_name"] = "Model_" . ucfirst($table);
		}

		//Если не был указан ключ связки
		if( isset($values["foreign_key"]) == false ) {
			$values["foreign_key"] = strtolower($table_parent) . "_id";
		}

		//Запоминаем данные
		$this->table = strtolower($table);
		$this->class_name = $values["class_name"];
		$this->foreign_key = $values["foreign_key"];
		$this->dependent = isset($values["dependent"]) ? $values["dependent"] : null;
	}
}
