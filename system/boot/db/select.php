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
	 * Драйвер
	 * @var null
	 */
	private $driver = null;

	/**
	 * Конструктор
	 * @param string|array $table
	 * @param null $where
	 * @param null $colum
	 * @param null $order
	 * @param null $limit
	 */
	public function __construct($table, $where = null, $colum = null, $order = null, $limit = null) {

		$this->_table = is_array($table) ? $table : array($table);

		//Получаем имя драйвера
		$driver = Boot::getInstance()->config->db->adapter;

		//Инитим драйвер
		$this->driver = new $driver(null, null, null, null, null);

		if( $where ) {
			$this->where($where);
		}
		if( $colum ) {
			$this->column($colum);
		}
		if( $order ) {
			$this->order($order);
		}
		if( $limit ) {
			$this->limit($limit);
		}
	}

	/**
	 * Получить строку представления для запроса по типу данного value
	 * @param $value
	 * @return int|string
	 */
	private function getStringQueryByValue($value) {
		if( is_int($value) ) {
			return $value;
		}
		if( is_null($value) ) {
			return "NULL";
		}
		if( is_bool($value) ) {
			return $value ? "TRUE" : "FALSE";
		}
		return $this->driver->int_separator . $value . $this->driver->int_separator;
	}

	private function getStringQueryByArray($array) {
		$string = "";
		foreach($array as $v) {
			$string .= ($string == "" ? "" : ",") . ($v instanceof DB_Expr ? $v : "{$this->driver->separator}{$v}{$this->driver->separator}");
		}
		return $string;
	}

	/**
	 * Указываем выборку колонок
	 * @param $column
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

		if( is_array($where) ) {
			$sql = "";
			foreach($where as $k => $v) {
				$sql .= ($sql == "" ? "" : " AND ") . $this->driver->separator . $k . $this->driver->separator . " = " . $this->getStringQueryByValue($v);
			}

			$where = $sql;
		}

		$this->_where .= ($this->_where == null ? "" : " AND ") . "({$where})";

		return $this;
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
	 * @param $orderby
	 * @return Select
	 */
	public function order($orderby) {
		$this->_order = " ORDER BY " . $orderby;
		return $this;
	}

	/**
	 * Указываем лимит выборки
	 * @param $limit
	 * @param $offset
	 * @return Select
	 */
	public function limit($limit, $offset = null) {
		$this->_limit = " LIMIT {$limit}" . ($offset ? (Boot::getInstance()->config->db == "mysql" ? "," : " OFFSET ") . $offset : "");
		return $this;
	}

	/**
	 * Преобразует запрос в строку
	 * @return string
	 */
	public function __toString() {

		//Получаем колонки
		$column = $this->_column ? $this->getStringQueryByArray($this->_column) : "*";
		$table = $this->getStringQueryByArray($this->_table);
		$where = $this->_where ? " WHERE " . $this->_where : "";

		return "SELECT {$column} FROM {$table}{$where}{$this->_order}{$this->_limit}";
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
