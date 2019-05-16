<?php
/**
 * User: nixx
 * Date: 21.07.13
 * Time: 10:49
 */
class Model_Collection implements Iterator, ArrayAccess, Countable  {

	//Строка модели
	private $_model_row = null;

	//Позиция
	private $_position = 0;

	/**
	 * Коллекция строк
	 * @var ActiveRecord[]
	 */
	private $_rows = array();

	/**
	 * Конструктор
	 * @param $model_row
	 * @param array $rows
	 */
	public function __construct($model_row, array $rows) {
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
	 * @return ActiveRecord
	 */
	public function current() {
		return new $this->_model_row($this->_rows[$this->_position], false);
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
		return new $this->_model_row($this->_rows[$offset], false);
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

	/**
	 * Добавляет в начало массива
	 * @param ActiveRecord $record
	 */
	public function prepend(ActiveRecord $record) {
		array_unshift($this->_rows, $record->toStdClass());
	}

	public function toArray() {
		return $this->_rows;
	}
}