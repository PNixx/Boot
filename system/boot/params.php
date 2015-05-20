<?php
/**
 * User: nixx
 * Date: 24.12.14
 * Time: 13:45
 */

class Boot_Params implements Iterator, ArrayAccess {

	/**
	 * Позиция
	 * @var int
	 */
	private $_position = 0;

	/**
	 * Коллекция строк
	 * @var array
	 */
	private $_params;

	/**
	 * Разрешенные поля для формы
	 * @var array
	 */
	private $_permit = [];

	/**
	 * Конструктор
	 * @param $params
	 */
	public function __construct($params) {
		$this->_params = $params;
	}

	/**
	 * Установка разрешенный полей для формы
	 * @param array $permit
	 * @return $this
	 */
	public function permit(array $permit) {
		$this->_permit = $permit;
		return $this;
	}

	/**
	 * Получение только разрешенных параметров
	 * @return array
	 */
	public function getValues() {
		$values = [];
		if( array_diff(array_keys($this->_params), $this->_permit) ) {
			Boot::getInstance()->debug('  Unpermitted params: ' . implode(', ', array_diff(array_keys($this->_params), $this->_permit)));
		}
		foreach( $this->_params as $key => $param ) {
			if( in_array($key, $this->_permit) ) {
				$values[$key] = $param;
			}
		}
		return $values;
	}
	
	/**
	 * @return ActiveRecord
	 */
	public function current() {
		return $this->_params[$this->_position];
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
		return isset($this->_params[$this->_position]);
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
		return count($this->_params);
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
		return in_array($offset, $this->_params);
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
		return isset($this->_params[$offset]) ? $this->_params[$offset] : null;
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
		$this->_params[$offset] = $value;
		if( !in_array($offset, $this->_permit) ) {
			$this->_permit[] = $offset;
		}
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
		unset($this->_params[$offset]);
	}
}