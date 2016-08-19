<?php
/**
 * User: nixx
 * Date: 26.02.14
 * Time: 15:38
 */
class Boot_Form_Lib extends Boot_Abstract_Library {

	/**
	 * Отключаем инициализицию
	 * @var bool
	 */
	public static $is_init = false;

	/**
	 * @var ActiveRecord|Form_Row
	 */
	protected $_row = null;

	/**
	 * @var string
	 */
	protected $_name;

	/**
	 * Конструктор и инициализатор фукнции
	 * @param string $name имя массива хранилища
	 * @param ActiveRecord|Form_Row $row
	 * @param array $params
	 */
	public function __construct($name, $row, $params = array()) {
		$this->_row = &$row;
		$this->_name = $name;

		//Если не указан экшен
		if( empty($params['action']) && $row instanceof ActiveRecord ) {
			$params['action'] = (!empty($params['namespace']) ? '/' . $params['namespace'] : '') . '/' . strtolower(str_ireplace("Model_", "", get_class($row))) . '/' . ($row->isNew() ? 'create' : 'save');
		}

		//Сразу рисуем форму
		print "<form id=\"{$name}\"" . $this->implode($params) . (isset($params['method']) && strtolower($params['method']) == 'post' ? ' enctype="multipart/form-data"' : '') . ">";

		//Если запись сохранена и имеет id
		if( $row->id ) {
			print $this->input("id", array("as" => "hidden"));
		}
	}

	/**
	 * Закрытие формы
	 */
	public function end() {
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

		//Для required
		if( isset($params['required']) && $params['required'] === false ) {
			unset($params['required']);
		}

		//Собираем параметры для полей ввода
		$p = array();
		foreach( $params as $key => $value ) {
			if( !in_array($key, ["as", "label"]) ) {
				$p[$key] = $params[$key];
			}
		}

		//Если не указано значение
		if( !isset($params['value']) ) {
			if( !$this->_row->$name && isset($params['default']) ) {
				$params['value'] = $params['default'];
			} else {
				$params['value'] = $this->_row->$name;
			}
		}

		//Выводим строку ввода
		switch( $params["as"] ) {

			//Textarea
			case "text":
				$print .= "<textarea name=\"{$this->_name}[$name]\" id=\"{$this->_name}_$name\"" . $this->implode($p) . ">" . htmlspecialchars($params['value']) . "</textarea>";
				break;

			case "checkbox":
				$print .= "<div class='checkbox'>" .
					"<input name=\"{$this->_name}[$name]\" type=\"hidden\" value=\"0\">" .
					"<input name=\"{$this->_name}[$name]\" id=\"{$this->_name}_$name\" type=\"checkbox\" value=\"1\"" . ($params['value'] ? " checked=\"checked\"" : "") . $this->implode($p) . ">" .
					$this->label($name, $params) .
					"</div>";
				break;

			//Дефолтный инпут
			default:
				$print .= "<input name=\"{$this->_name}[$name]\" id=\"{$this->_name}_$name\" type=\"" . ($params["as"] == "string" ? "text" : $params["as"]) . "\" value=\"" . htmlspecialchars($params['value']) . "\"" . $this->implode($p) . ">";
		}

		return $print;
	}

	/**
	 * Строит селетор со связью из модели
	 * @param       $table
	 * @param array $params
	 *   column_name - указывает имя колонки, откуда брать название
	 * @return string
	 * @throws Boot_Exception
	 */
	public function association($table, $params = []) {

		//Проверяем тип модели
		if( !($this->_row instanceof ActiveRecord) ) {
			throw new Boot_Exception('Model must be instance of ActiveRecord');
		}

		/**
		 * Имя класса
		 * @var ActiveRecord $class
		 */
		$class = get_class($this->_row);

		//Ищем объявленную связь
		if( !$class::isBelongs($table) ) {
			throw new Boot_Exception('Model must be declared and is associated with the table ' . $table);
		}
		$db_links = $class::getBelongsTo($table);

		/**
		 * Модель выборки
		 * @var ActiveRecord $model
		 */
		$model = "Model_" . ucfirst($table);
		$column_name = empty($params['column_name']) ? 'name' : $params['column_name'];

		//Получаем массив
		$options = $model::column([$column_name, 'id'])->read_all();
		$options = array_map(function($v) use($column_name) {
			return [$v[$column_name], $v['id']];
		}, $options);

		//Выводим массив
		return $this->select($db_links->foreign_key, $options, array_merge_recursive($params, ['include_blank' => false]));
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
		$print .= "<select name=\"{$this->_name}[$name]\" id=\"{$this->_name}_$name\"" . $this->implode(array_diff_key($params, ['include_blank' => ''])) . ">";

		//Добавляем пустое поле
		if( $params["include_blank"] ) {
			$print .= "<option value=''>" . ($params["include_blank"] !== true ? $params["include_blank"] : '') . "</option>";
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

//Form default model
class Form_Row {

	/**
	 * Получаем данные
	 * @param $name
	 * @return string
	 */
	public function __get($name) {
		return false;
	}
}