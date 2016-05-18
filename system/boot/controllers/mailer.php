<?php
namespace Boot\Controllers;

use Boot;
use Boot\Abstracts\Controller;

class MailerController extends Controller {

	public function indexAction() {
		Boot::getInstance()->disableLayout();
		Boot::getInstance()->disableView();

		//Отображаем весь список
		if( !$this->getParam('file') ) {
			foreach( glob(APPLICATION_ROOT . '/test/mailers/*.php') as $file ) {
				$class = pathinfo($file, PATHINFO_FILENAME);
				echo '<h1>' . ucfirst($class) . '</h1>';
				echo '<ul>';
				require APPLICATION_ROOT . '/test/mailers/' . $class . '.php';

				//Проходим по списку методов
				foreach(get_class_methods($class . 'MailerPreview') as $method) {
					if( in_array($method, get_class_methods($class . 'Mailer')) ) {
						echo "<li><a href=\"/boot/mailer/{$class}/{$method}\">{$class}/{$method}</a></li>";
					}
				}
				echo '</ul>';
			}
		} else {
			require APPLICATION_ROOT . '/test/mailers/' . $this->getParam('file')[0] . '.php';

			$class = $this->getParam('file')[0] . 'MailerPreview';

			//Если нужно вывести только письмо
			echo $class::{$this->getParam('file')[1]}();
		}
	}
}