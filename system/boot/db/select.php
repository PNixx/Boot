<?php
/**
 * Конструктор селекта
 * User: P.Nixx
 * Date: 11.10.12
 * Time: 15:09
 */
class Select {

	/**
	 * Список возвращаемых колонок
	 * @var null|array
	 */
	private $_column = null;

	/**
	 * Условие выборки
	 * @var null|string
	 */
	private $_where = null;

	/**
	 * Сортировка
	 * @var null
	 */
	private $_order = null;

	/**
	 * Группировка
	 * @var null
	 */
	private $_group_by = null;

	/**
	 * Лимит
	 * @var null
	 */
	private $_limit = null;

	/**
	 * Таблица
	 * @var null|array
	 */
	private $_table = null;

	/**
	 * JOIN к таблице
	 * @var null
	 */
	private $_joins = null;

	/**
	 * Драйвер
	 * @var postgres|mysql
	 */
	private $driver = null;

	/**
	 * Конструктор
	 * @param string|array $table
	 * @param null $where
	 * @param null $column
	 * @param null $order
	 * @param null $limit
	 */
	public function __construct($table, $where = null, $column = null, $order = null, $limit = null) {

		$this->_table = is_array($table) ? $table : array($table);

		//Получаем имя драйвера
		$driver = Boot::getInstance()->config->db->adapter;

		//Инитим драйвер
		$this->driver = new $driver(null, null, null, null, null);

		if( $where ) {
			$this->where($where);
		}
		if( $column ) {
			$this->column($column);
		}
		if( $order ) {
			$this->order($order);
		}
		if( $limit ) {
			$this->limit($limit);
		}
	}

	private function getStringQueryByArray($array) {
		$string = "";
		foreach($array as $v) {
			$string .= ($string == "" ? "" : ",") . ($v instanceof DB_Expr ? $v : $this->driver->getStringQueryByValue($v));
		}
		return $string;
	}

	private function getIdentifierByArray($array) {
		$string = "";
		foreach($array as $v) {
			$string .= ($string == "" ? "" : ",") . ($v instanceof DB_Expr ? $v : $this->driver->escape_identifier($v));
		}
		return $string;
	}

	/**
	 * Указываем выборку колонок
	 * @param array|string $column
	 * @return Select
	 */
	public function column($column) {
		$this->_column = is_array($column) ? $column : array($column);
		return $this;
	}

	/**
	 * AND WHERE
	 * @param array|string $where
	 * @return Select
	 */
	public function where($where) {

		//Создаем условие
		$where = $this->make_where($this->_table[0], $where);

		//Если получили результат
		if( $where ) {
			$this->_where .= ($this->_where == null ? "" : " AND ") . $where;
		}

		return $this;
	}

	/**
	 * Возвращает SQL условие из параметров
	 * @param string       $table
	 * @param array|string $where
	 * @return string|null
	 */
	private function make_where($table, $where) {

		if( is_array($where) ) {
			$sql = [];
			foreach($where as $k => $v) {

				//Добавляем таблицу в строку
				$s = $this->driver->escape_identifier($table) . ".";

				//Добавляем колонку
				$s .= $this->driver->escape_identifier($k);

				//Если значение массив
				if( is_array($v) ) {
					$s .= " IN (" . ($v ? $this->driver->getStringQueryByValue($v) : "NULL") . ")";
				} elseif( is_null($v) ) {
					$s .= " IS NULL";
				} else {
					$s .= " = " . $this->driver->getStringQueryByValue($v);
				}

				//Добавляем в массив
				$sql[] = $s;
			}

			if( $sql ) {
				$where = implode(' AND ', $sql);
			}
		}

		if( $where ) {
			return "({$where})";
		}
		return null;
	}

	/**
	 * AND NOT IN
	 * @param string $column
	 * @param array  $array
	 * @return Select
	 */
	public function notIn($column, array $array) {
		$s = "";

		//Добавляем таблицу в строку
		if( count($this->_table) == 1 ) {
			$s = $this->driver->escape_identifier($this->_table[0]) . ".";
		}

		//Добавляем колонку
		$s .= $this->driver->escape_identifier($column);
		$s .= " NOT IN (" . ($array ? $this->driver->getStringQueryByValue($array) : "NULL") . ")";

		//Добавляем условие
		$this->_where .= ($this->_where == null ? "" : " AND ") . "({$s})";

		return $this;
	}

	/**
	 * Добавление join к запросу (Один ко многим)
	 * @param string            $table
	 * @param string|array|null $on
	 * @param bool              $many
	 * @throws DB_Exception
	 */
	public function joins($table, $on = null, $many = true) {

		//Если указано кол-во таблиц больше 1, то хз че делать
		if( count($this->_table) > 1 ) {
			throw new DB_Exception("Must be table count 1");
		}

		//Добавочное условие
		if( $on === null ) {
			$where = null;
		} else {
			$where = " AND " . $this->make_where($table, $on);
		}

		//Добавляем
		$this->_joins .= " INNER JOIN " . $this->driver->escape_identifier($table) . " ON " . $this->driver->escape_identifier($many ? $table : $this->_table[0]) . "." . $this->driver->escape_identifier($many ? $this->_table[0] : $table . "_id") . " = " . $this->driver->escape_identifier($many ? $this->_table[0] : $table) . "." . $this->driver->escape_identifier("id") . $where;
	}

	/**
	 * AND WHERE
	 * @param $where
	 * @return Select
	 */
	public function orWhere($where) {

		if( is_array($where) ) {
			$sql = "";
			foreach($where as $k => $v) {
				$sql .= ($sql == "" ? "" : " AND ") . $this->driver->separator . $k . $this->driver->separator . " = " . $this->getStringQueryByValue($v);
			}

			$where = $sql;
		}

		$this->_where .= ($this->_where == null ? "" : " OR ") . "({$where})";

		return $this;
	}

	/**
	 * Указываем сортировку
	 * @param string $orderby
	 * @return Select
	 */
	public function order($orderby) {
		if( $orderby === null ) {
			$this->_order = null;
		} else {
			$this->_order = " ORDER BY " . $orderby;
		}
		return $this;
	}

	public function group_by($column) {
		$this->_group_by = " GROUP BY " . $column;
		return $this;
	}

	/**
	 * Указываем лимит выборки
	 * @param $limit
	 * @param $offset
	 * @return Select
	 */
	public function limit($limit, $offset = null) {
		if( $limit ) {
			$this->_limit = " LIMIT {$limit}" . ($offset ? (Boot::getInstance()->config->db == "mysql" ? "," : " OFFSET ") . $offset : "");
		} else {
			$this->_limit = null;
		}
		return $this;
	}

	/**
	 * Преобразует запрос в строку
	 * @return string
	 */
	public function __toString() {

		//Получаем колонки
		$column = $this->_column ? $this->getIdentifierByArray($this->_column) : "*";
		$table = $this->getIdentifierByArray($this->_table);
		$where = $this->_where ? " WHERE " . $this->_where : "";

		return "SELECT {$column} FROM {$table}{$this->_joins}{$where}{$this->_group_by}{$this->_order}{$this->_limit}";
	}
}

/**
 * DB вставка
 */
class DB_Expr {
	private $value;
	public function __construct($value) {
		$this->value = $value;
	}
	public function __toString() {
		return $this->value;
	}
}
