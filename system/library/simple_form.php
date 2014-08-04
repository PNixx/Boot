<?php
/**
 * User: nixx
 * Date: 22.07.14
 * Time: 13:51
 */

class Boot_Simple_Form_Lib extends \Boot_Abstract_Library {

	/**
	 * Отключаем инициализицию
	 * @var bool
	 */
	public static $is_init = false;

	/**
	 * @var ActiveRecord
	 */
	protected $_row = null;

	/**
	 * @var string
	 */
	protected $_name;

	/**
	 * Форма закрыта?
	 * @var bool
	 */
	private $is_end = false;

	/**
	 * Конструктор и инициализатор фукнции
	 * @param ActiveRecord $row
	 * @param array $params
	 */
	public function __construct(ActiveRecord $row, $params = array()) {
		$this->_row = &$row;
		$this->_name = strtolower(preg_replace("/^Model_/i", "", get_class($row)));

		//Получаем урл
		if( !isset($params['action']) ) {
			$params['action'] = "";
		}

		//Если новая строка
		$params['action'] .= $row->isNew() ? '/create' : '/update';

		//Сразу рисуем форму
		print "<form id=\"{$this->_name}\"" . $this->implode($params) . (isset($params['method']) && strtolower($params['method']) == 'post' ? ' enctype="multipart/form-data"' : '') . ">";

		//Если запись сохранена и имеет id
		if( $row->id ) {
			print $this->input("id", array("as" => "hidden"));
		}
	}

	/**
	 * Проверяет, закрыта ли была форма
	 */
	public function __destruct() {
		if( !$this->is_end ) {
			throw new Boot_Exception('Form tag is not close');
		}
	}

	/**
	 * Закрытие формы
	 */
	public function end() {
		$this->is_end = true;
		print "</form>";
	}

	/**
	 * Строка ввода
	 * @param $name
	 * @param array $params
	 * @return string
	 */
	public function input($name, $params = array()) {
		$print = "";

		//Если не указан тип
		if( isset($params["as"]) == false ) {
			$params["as"] = "string";
		}

		//Печатаем лейбл
		if( !in_array($params["as"], ["hidden", "checkbox"]) && (isset($params["label"]) && $params["label"] !== false || isset($params["label"]) == false ) ) {
			$print .= $this->label($name, $params);
		}

		//Собираем параметры для полей ввода
		$p = array();
		foreach( $params as $key => $value ) {
			if( !in_array($key, ["as", "label"]) ) {
				$p[$key] = $params[$key];
			}
		}

		//Выводим строку ввода
		switch( $params["as"] ) {

			//Textarea
			case "text":
				$print .= "<textarea name=\"{$this->_name}[$name]\" id=\"{$this->_name}_$name\"" . $this->implode($p) . ">{$this->_row->$name}</textarea>";
				break;

			case "checkbox":
				$print .= "<div class='checkbox'>" .
					"<input name=\"{$this->_name}[$name]\" type=\"hidden\" value=\"0\">" .
					"<input name=\"{$this->_name}[$name]\" id=\"{$this->_name}_$name\" type=\"checkbox\" value=\"1\"" . ($this->_row->$name ? " checked=\"checked\"" : "") . $this->implode($p) . ">" .
					$this->label($name, $params) .
					"</div>";
				break;

			//Дефолтный инпут
			default:
				$print .= "<input name=\"{$this->_name}[$name]\" id=\"{$this->_name}_$name\" type=\"" . ($params["as"] == "string" ? "text" : $params["as"]) . "\" value=\"{$this->_row->$name}\"" . $this->implode($p) . ">";
		}

		return $print;
	}

	/**
	 * @param $name
	 * @param $params
	 * @return string
	 */
	protected function label($name, $params) {
		return "<label for=\"{$this->_name}_$name\">" . $this->getLabelTitle($name, $params) . "</label>";
	}

	/**
	 * Select box
	 * @param string $name
	 * @param array $options
	 * @param array $params
	 * @return string
	 */
	public function select($name, array $options, $params = []) {

		//Если не указано добавление пустого поля
		if( isset($params["include_blank"]) == false ) {
			$params["include_blank"] = true;
		}
		$print = "";

		//Печатаем лейбл
		if( (isset($params["label"]) && $params["label"] !== false || isset($params["label"]) == false ) ) {
			$print .= $this->label($name, $params);
		}

		//Строим селектор
		$print .= "<select name=\"{$this->_name}[$name]\" id=\"{$this->_name}[$name]\"" . $this->implode($params) . ">";

		//Добавляем пустое поле
		if( $params["include_blank"] ) {
			$print .= "<option></option>";
		}

		//Проходим по списку значений
		foreach( $options as $option ) {

			//Если опция указана как массив
			if( is_array($option) ) {
				$print .= "<option value=\"{$option[1]}\"" . ($this->_row->$name == $option[1] ? " selected=\"selected\"" : "") . ">{$option[0]}</option>";
			} else {
				$print .= "<option value=\"$option\"" . ($this->_row->$name == $option ? " selected=\"selected\"" : "") . ">$option</option>";
			}
		}

		//Возвращаем данные
		return $print . "</select>";
	}

	/**
	 * Submit button
	 * @param string $name
	 * @param array $params
	 * @return string
	 */
	public function submit($name, $params = []) {
		return "<button id=\"submit\"" . $this->implode($params) . ">$name</button>";
	}

	/**
	 * Получение заголовка для label
	 * @param $name
	 * @param $params
	 * @return string
	 */
	private function getLabelTitle($name, $params) {

		//Если был указан заголовок
		if( isset($params["label"]) ) {
			return $params["label"];
		}

		//Если есть класс переводчика
		if( class_exists("Boot_Translate_Lib", false) ) {
			return Boot::getInstance()->library->{"translate"}->_($name);
		}

		//Возвращаем имя
		return ucfirst($name);
	}

	/**
	 * Собирает параметры
	 * @param $params
	 * @return string
	 */
	private function implode($params) {
		$string = "";
		foreach( $params as $key => $value ) {
			$string .= " " . $key . "=\"" . $value . "\"";
		}
		return $string;
	}
}