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
	 * Подключение класса загрузки
	 * [
	 *  column => class name
	 * ]
	 * @var array
	 */
	protected static $mount_uploader = [];

	/**
	 * Функция выполнения до сохранения записи
	 * @var null|string
	 */
	protected static $before_save = null;

	/**
	 * Функция выполнения после успешного сохранения записи
	 * @var null|string
	 */
	protected static $after_save = null;

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
	 * @var stdClass
	 */
	private $_row;

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
	 * @var Select[]
	 */
	private static $select = [];

	/**
	 * Конструктор класса
	 * @param null $row
	 * @param bool $new_record
	 */
	public function __construct($row = null, $new_record = false) {

		//Сохраняем данные
		$this->_row = (object)$row;
		$this->_new_record = $new_record;

		//Инициализируем загрузчики
		foreach( static::$mount_uploader as $column => $class ) {
			$this->_row->$column = new $class($this, $column, $this->$column);
		}
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

	/**
	 * Выполняется до сохранения
	 */
	protected function before_save() {

	}

	/**
	 * Выполняется после успешного сохранения
	 * @param stdClass $old Старые данные до обновления
	 */
	protected function after_save($old) {

	}

	// ------------------- Public ------------------>

	/**
	 * Получаем данные
	 * @param $name
	 * @return string
	 */
	public function __get($name) {
		if( array_key_exists($name, $this->_row_update) ) {
			return $this->_row_update[$name];
		} elseif( property_exists($this->_row, $name) ) {
			return $this->_row->$name;
		} else {
			return false;
		}
	}

	/**
	 * Получаем данные
	 * @param $name
	 * @param $value
	 * @return string
	 */
	public function __set($name, $value) {
		$this->_row_update[$name] = $value;
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
	 * @param array|Boot_Params $row
	 * @return static
	 */
	public static function create($row = array()) {

		//Получаем имя вызываемого класса
		$class = get_called_class();

		//Создаем экземплятор
		return new $class(is_array($row) ? $row : $row->getValues(), true);
	}

	/**
	 * Вставка строки
	 * @param array|Boot_Params $data
	 * @return int
	 */
	public static function insert($data = array()) {

		//Создаем строку
		$object = static::create($data);
		$object->save();

		//Создаем экземплятор
		return $object->id;
	}

	/**
	 * Вставка строки
	 * @param $data
	 * @return static
	 */
	public static function find_or_insert_by($data = array()) {

		//Пробуем найти
		$row = static::where($data)->row();

		//Если нашли строку
		if( $row ) {
			return $row;
		}

		//Создаем строку
		$object = static::create($data);
		$object->save();

		//Создаем экземплятор
		return $object;
	}

	/**
	 * Инициализация запроса для конкретной модели
	 */
	private static function init_select() {
		if( isset(self::$select[self::getTable()]) == false ) {
			self::$select[self::getTable()] = new Select(static::getTable(), null, null, static::$default_order);
		}
	}

	/**
	 * Проверка, инициализирован ли select
	 * @throws DB_Exception
	 */
	private static function check_select() {
		if( isset(self::$select[self::getTable()]) == false ) {
			throw new DB_Exception("Non select constructor");
		}
	}

	/**
	 * Выполнение запроса
	 * @return mysql|postgres
	 */
	private static function query_select() {

		//Выполняем запрос
		$result = DB::getDB()->query(self::$select[self::getTable()]);
		unset(self::$select[self::getTable()]);

		//Возвращаем результат
		return $result;
	}

	/**
	 * Проходит по списку колонок и выполняет необходимые действия
	 */
	private function getUpdateColumns() {

		//Получаем наборы изменений
		if( $this->_new_record ) {
			$columns = &$this->_row;
		} else {
			$columns = (object)$this->_row_update;
		}

		//Проходим по колонкам
		foreach( $columns as $key => $column ) {

			if( !isset($column) ) {
				$columns->$key = null;
			}

			//Проверяем загрузчик
			if( $column instanceof Boot_Uploader_Abstract ) {
				$column->uploadFile();
				$columns->$key = $column->__toString();
			}
		}

		return (array)$columns;
	}

	/**
	 * Обновляет данные в таблице
	 * @param array       $set
	 * @param null|string $where
	 */
	public static function update_all(array $set, $where = null) {
		DB::getDB()->update(self::getTable(), $set, $where);
	}

	/**
	 * @return static
	 * @throws DB_Exception
	 */
	public static function row() {
		self::check_select();

		//Выполняем запрос
		$result = self::query_select();

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
		self::check_select();

		//Выполняем запрос
		$result = self::query_select();

		//Получаем строку
		return $result->read_cols();
	}

	/**
	 * @return array
	 * @throws DB_Exception
	 */
	public static function read_all() {
		self::check_select();

		//Выполняем запрос
		$result = self::query_select();

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
		self::init_select();

		//Выполняем запрос
		$result = self::query_select();

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
		self::$select[self::getTable()] = $query;

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
	 * @throws Exception
	 * @return static
	 */
	public static function where($where) {

		//Инициализируем
		self::init_select();

		//Добавляем условие
		if( self::$select[self::getTable()] && self::$select[self::getTable()] instanceof Select ) {
			self::$select[self::getTable()]->where($where);
		} else {
			throw new Exception('Select if null: ' . var_export(self::$select[self::getTable()], true));
		}

		//Возвращаем ту же функцию
		return new static();
	}

	/**
	 * AND NOT IN
	 * @param string $column
	 * @param array  $array
	 * @return static
	 * @throws Exception
	 */
	public static function notIn($column, array $array) {

		//Инициализируем
		self::init_select();

		//Добавляем условие
		if( self::$select[self::getTable()] && self::$select[self::getTable()] instanceof Select ) {
			self::$select[self::getTable()]->notIn($column, $array);
		} else {
			throw new Exception('Select if null: ' . var_export(self::$select[self::getTable()], true));
		}

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
		if( isset(self::$select[self::getTable()]) == false ) {
			self::$select[self::getTable()] = new Select(static::getTable(), null, new DB_Expr(static::getTable() . ".*"), static::$default_order);
		}

		//Добавляем join
		self::$select[self::getTable()]->joins($table, $on);

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
		self::init_select();

		//Добавляем условие
		self::$select[self::getTable()]->where("LOWER(" . DB::getDB()->escape_identifier($column) . ") ILIKE " . DB::getDB()->getStringQueryByValue(strtolower($value)));

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
		self::init_select();

		//Добавляем колонки выборки
		self::$select[self::getTable()]->column($column);

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
		if( isset(self::$select[self::getTable()]) == false ) {
			self::$select[self::getTable()] = new Select(static::getTable());
		}

		//Добавляем колонки выборки
		self::$select[self::getTable()]->order($order_by);

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
		self::init_select();

		//Добавляем колонки выборки
		self::$select[self::getTable()]->limit($limit, $offset);

		//Возвращаем ту же функцию
		return new static();
	}

	/**
	 * Сортировка
	 * @param array|string $where
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
		DB::getDB()->create_table($table, $column, $pkey, $ukey);
		self::create_index($table, [$pkey]);
	}

	/**
	 * @param       $table
	 * @param array $columns
	 */
	static public function create_index($table, array $columns) {
		DB::getDB()->create_index($table, $columns);
	}

	/**
	 * @param       $table
	 * @param array $columns
	 */
	static public function drop_index($table, array $columns) {
		DB::getDB()->drop_index($table, $columns);
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
		self::init_select();
		$select = self::$select[self::getTable()];
		unset(self::$select[self::getTable()]);

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
			$row = DB::getDB()->select(static::getTable(), self::getPKey() . " = " . DB::getDB()->getStringQueryByValue($id))->row();

			//Если ничего не нашли
			if( $row == false ) {
				throw new DB_Exception("Record not found", 404);
			}

			//Сохраняем в кеш
			self::$_cached_by_find[static::getTable()][$id] = $row;
		}

		//Создаем экземплятор
		return self::createRow($row);
	}

	// ---------------- Row methods --------------------->

	/**
	 * Новая строка?
	 * @return bool
	 */
	public function isNew() {
		return $this->_new_record;
	}

	/**
	 * Обновление данных строки
	 * @param array|Boot_Params $data
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

		//Получаем данные
		if( $data instanceof Boot_Params ) {
			$values = $data->getValues();
		} else {
			$values = &$data;
		}

		//Обнуляем список
		$this->_row_update = [];

		/**
		 * Инициализируем загрузчики
		 * @var Boot_Uploader_Abstract $class
		 */
		foreach( static::$mount_uploader as $column => $class ) {
			if( $class::fetchUploadFile(static::getTable(), $column) ) {

				//Загружаем новые файлыё
				$this->$column->remove();
				$this->$column->uploadFile();
				$this->_row_update[$column] = $this->$column->__toString();
			}
		}

		//Проходим по списку
		foreach( $values as $key => $value ) {
			if( $value instanceof DB_Expr || empty($this->_row->$key) || $this->_row->$key != $value ) {
				$this->_row_update[$key] = $value;
			}
		}

		//Пытаемся обновить
		return $this->save();
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function save() {

		//Определяем функцию
		//todo убрать в релизе 2.2.0
		$after_save = function() {

			//Если указана функция
			if( static::$after_save ) {
				Boot::getInstance()->debug("Variable static::\$after_save was deprecated! Use abstract function after_save()", true);
				$this->{static::$after_save}();
			}
		};

		//Перед сохранением
		//todo убрать в релизе 2.2.0
		$before_save = function() {

			//Если указана функция
			if( static::$before_save ) {
				Boot::getInstance()->debug("Variable static::\$before_save was deprecated! Use abstract function before_save()", true);
				$this->{static::$before_save}();
			}
		};

		//Сохраняем старые значения
		$old = clone $this->_row;

		//Проверяем не новая ли запись
		if( $this->_new_record == false ) {

			//Пытаемся обновить
			if( $this->_row_update ) {

				//Запускаем транзакцию
				$this->begin_transaction();
				try {

					//Выполняем функции колбека
					$before_save();
					$this->before_save();

					//Обновляем
					$result = DB::getDB()->update(static::getTable(), $this->getUpdateColumns(), self::getPKey() . " = " . pg_escape_literal($this->{static::$pkey}));
					if( $result ) {

						//Изменяем данные строки
						$this->merge_after_update();

						//Выполняем функции колбека
						$after_save();
						$this->after_save($old);

						//Завершаем транзакцию
						$this->commit();

						//Возвращаем результат
						return $result;
					} else {
						$this->rollback();
					}
				} catch(Exception $e) {
					$this->rollback();
					throw $e;
				}
			} else {
				return true;
			}
		} else {

			//Запускаем транзакцию
			$this->begin_transaction();
			try {

				//Выполняем функции колбека
				$before_save();
				$this->before_save();

				//Добавляем строку
				$id = DB::getDB()->insert(static::getTable(), $this->getUpdateColumns(), static::$pkey);
				if( $id > 0 ) {
					$this->_row->id = $id;
					$this->_new_record = false;

					//Выполняем функции колбека
					$after_save();
					$this->after_save($old);

					//Завершаем транзакцию
					$this->commit();

					//Возвращаем результат
					return true;
				} else {
					$this->rollback();
				}
			} catch(Exception $e) {
				$this->rollback();
				throw $e;
			}
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

			//Проходим по колонкам
			foreach( $this->_row as $key => $column ) {

				//Проверяем загрузчик
				if( $column instanceof Boot_Uploader_Abstract ) {
					$column->remove();
				}
			}

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

	/**
	 * Извлечение объекта
	 * @return array|object
	 */
	public function toStdClass() {
		return $this->_row;
	}

	/**
	 * Преобразование в строку для вывода
	 */
	public function __toString() {
		return print_r($this->toArray(), true);
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
