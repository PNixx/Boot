<?php
/**
 * User: Odintsov S.A.
 * Date: 13.01.12
 * Time: 17:57
 */
namespace Boot\Library;

class Translate {

	/**
	 * Язык сайта
	 * @var string
	 */
	private $_lang;

  /**
   * Языка перевода по умолчанию
   * @var string
   */
  private $_default = 'ru';

  /**
   * Хранилище парсенных файлов
   * @var null
   */
  private $_parse = [];

	/**
	 * @var Translate
	 */
	private static $instance;

	/**
	 * Строим переводчик
	 */
  private function __construct() {

		//Получаем из куков язык
		if( class_exists(\Boot_Cookie::class, true) && \Boot_Cookie::get('lang') ) {
			$this->_lang = \Boot_Cookie::get('lang');
		} elseif( isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ) {
			$this->_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
		} else {
			$this->_lang = $this->_default;
		}

		//Загружаем дефолтные переводы
		$this->loadLang(SYSTEM_PATH . '/boot/lang');
  }

	/**
	 * Загружает переводы проекта
	 */
	public function loadProjectLang() {

		//Строим путь до базы
		$dir = APPLICATION_PATH . '/lang';

		//Проверяем директорию
		if( is_dir($dir) ) {
			$this->loadLang($dir);
		}
	}

	/**
	 * @return Translate
	 */
	static public function getInstance() {
		if( self::$instance === null ) {
			self::$instance = new Translate();
		}
		return self::$instance;
	}

	/**
	 * Перевод
	 * @param string $text
	 * @param null|int $args
	 * @param bool $plural
	 * @return string
	 */
  public function _($text, $args = null, $plural = false) {

    //Определяем язык
    $lang = !$plural && $args ? $args : $this->_lang;

    if( in_array($lang, $this->getLanguages()) == false ) {
      $lang = $this->_default;
    }

		if( $plural ) {
			return $this->plural($text, $args, $lang);
		}
    return !empty($this->_parse[$lang][$text]) ? $this->_parse[$lang][$text] : str_replace(['.', '_'], ' ', $text);
  }

  /**
   * Возвращает текущий язык
   */
  public function getLocale() {
    return $this->_lang;
  }

	/**
	 * Устанавливает текущий язык
	 * @param $lang
	 */
  public function setLocale($lang) {
    $this->_lang = $this->checkLocale($lang);
  }

  /**
   * Получить список доступных языков
	 * @use Translate::getLanguages()
	 * @deprecated
   */
  public function getLangs() {
    return $this->getLanguages();
  }

	/**
	 * Получить список доступных языков
	 */
	public function getLanguages() {
		return array_keys($this->_parse);
	}

	/**
	 * Проверяет доступность языка
	 * @param $lang
	 * @return string
	 */
  private function checkLocale($lang) {
		//Если файл перевода не найден
		if( file_exists(APPLICATION_PATH . '/lang/' . $lang . '.json') == false ) {
			$lang = 'ru';
			if( class_exists('Boot_Cookie') ) {
				\Boot_Cookie::set('lang', $lang);
			}
		}
		return $lang;
	}

	/**
	 * @param string $text
	 * @param int    $number
	 * @param string $lang
	 *
	 * array('арбуз', 'арбуза', 'арбузов')
	 *
	 * @return int
	 */
	private function plural($text, $number, $lang) {
		$i18n = $this->_($text);

		//Определяем позицию слова для перевода
		if( $lang == 'ru' ) {
			$i = ($number % 10 == 1 && $number % 100 != 11 ? 0 : ($number % 10 >= 2 && $number % 10 <= 4 && ($number % 100 < 10 || $number % 100 >= 20) ? 1 : 2));
		} else {
			$i = $number == 1 ? 0 : 1;
		}
		if( is_array($i18n) && count($i18n) > $i ) {
			return str_replace("%count%", $number, $i18n[$i]);
		}
		return str_replace("%count%", $number, $text);
	}

	/**
	 * Загружаем директорию в базу
	 * @param $directory
	 */
	public function loadLang($directory) {

		//Инклудим данные файлов
		foreach( glob(realpath($directory) . '/*.json') as $file ) {

			//Получаем данные файла
			$json = json_decode(file_get_contents($file), true);

			//Язык файла
			$lang = pathinfo($file, PATHINFO_FILENAME);

			//Если языка такого нет, создаем массив
			if( !isset($this->_parse[$lang]) ) {
				$this->_parse[$lang] = [];
			}

			//Мигрируем
			$this->_parse[$lang] = array_merge($json, $this->_parse[$lang]);
		}
	}

	/**
	 * @return array
	 */
	public function getParse() {
		return $this->_parse;
	}
}