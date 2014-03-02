<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nixx
 * Date: 21.07.13
 * Time: 10:49
 * To change this template use File | Settings | File Templates.
 */

class Model_Collection implements Iterator, ArrayAccess  {

	/**
	 * Рабочая таблица модели
	 * @var null||string
	 */
	protected $_table = null;

	/**
	 * Связь с таблицами
	 * Многие к одному
	 * @var null|array
	 */
	protected $_belongs_to = null;

	/**
	 * Связь с таблицами
	 * Один ко многим
	 * @var null|array([column] => [table])
	 */
	protected $_has_many = null;

	/**
	 * Первичный ключ
	 * @var null|array
	 */
	protected $_pkey = null;

	/**
	 * Инстанс модели
	 * @var Model
	 */
	private $_model_instance = null;

	//Строка модели
	private $_model_row = null;

	//Позиция
	private $_position = 0;

	/**
	 * Коллекция строк
	 * @var Model_Row[]
	 */
	private $_rows = array();

	/**
	 * Конструктор
	 * @param $table
	 * @param $belongs_to
	 * @param $has_many
	 * @param $pkey
	 * @param $model_row
	 * @param Model $model
	 * @param array $rows
	 */
	public function __construct($table, $belongs_to, $has_many, $pkey, $model_row, Model &$model, array $rows) {
		$this->_table = $table;
		$this->_belongs_to = $belongs_to;
		$this->_has_many = $has_many;
		$this->_pkey = $pkey;
		$this->_model_instance = $model;
		$this->_model_row = $model_row;
		$this->_rows = $rows;
	}

	/**
	 * Получение списка id коллекции
	 * @return array
	 */
	public function getIDs() {
		$id = array();
		foreach($this->_rows as $row) {
			$id[] = $row->id;
		}
		return array_unique($id);
	}

	/**
	 * @return Model_Row
	 */
	public function current() {
		try {
			if( class_exists($this->_model_row) ) {
				return new $this->_model_row($this->_rows[$this->_position], $this->_table, $this->_belongs_to, $this->_has_many, $this->_pkey, $this->_model_instance);
			} else {
				return $this->_rows[$this->_position];
			}

		} catch( Exception $e ) {
			return $this->_rows[$this->_position];
		}
	}

	/**
	 * @return void
	 */
	public function next() {
		++$this->_position;
	}

	/**
	 * @return int
	 */
	public function key() {
		return $this->_position;
	}

	/**
	 * @return boolean
	 */
	public function valid() {
		return isset($this->_rows[$this->_position]);
	}

	/**
	 * перемотка в начало
	 * @return void
	 */
	public function rewind() {
		$this->_position = 0;
	}

	/**
	 * Кол-во строк
	 * @return int
	 */
	public function count() {
		return count($this->_rows);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Whether a offset exists
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset <p>
	 * An offset to check for.
	 * </p>
	 * @return boolean true on success or false on failure.
	 * </p>
	 * <p>
	 * The return value will be casted to boolean if non-boolean was returned.
	 */
	public function offsetExists($offset) {
		return in_array($offset, $this->_rows);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to retrieve
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param mixed $offset <p>
	 * The offset to retrieve.
	 * </p>
	 * @return mixed Can return all value types.
	 */
	public function offsetGet($offset) {
		try {
			if( class_exists($this->_model_row) ) {
				return new $this->_model_row($this->_rows[$offset], $this->_table, $this->_belongs_to, $this->_has_many, $this->_pkey, $this->_model_instance);
			} else {
				return $this->_rows[$offset];
			}
		} catch( Exception $e ) {
			return $this->_rows[$offset];
		}
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to set
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param mixed $offset <p>
	 * The offset to assign the value to.
	 * </p>
	 * @param mixed $value <p>
	 * The value to set.
	 * </p>
	 * @return void
	 */
	public function offsetSet($offset, $value) {
		$this->_rows[$offset] = $value;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to unset
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset <p>
	 * The offset to unset.
	 * </p>
	 * @return void
	 */
	public function offsetUnset($offset) {
		unset($this->_rows[$offset]);
	}
}