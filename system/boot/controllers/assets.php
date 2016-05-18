<?php
namespace Boot\Controllers;

use Boot;
use Boot\Abstracts\Controller;
use Boot\Assets;
use Boot_Exception;

class AssetsController extends Controller {

	//Поиск ассетов для develop
	public function indexAction() {
		Boot::getInstance()->disableLayout();
		Boot::getInstance()->disableView();

		//Доступ только для develop
		if( !Boot::getInstance()->isDevelopment() ) {
			throw new \Controller_Exception('File not found', 404);
		}

		$path = preg_replace('/^(css|js)\//', '', $this->getParam('path'));
		$ext = pathinfo($path, PATHINFO_EXTENSION);

		switch( $ext ) {
			case "css":
				header("Content-Type: text/css");
				break;
			case "js":
				header("Content-Type: application/javascript");
				break;
			case "eot";
			case "svg";
			case "ttf";
			case "woff";
			case "woff2";
				header("Content-Type: font/" . $ext);
				break;
		}

		$assets = new Assets($ext, true, false);
		$assets->setCompress(false);

		if( $assets->full_path($path) ) {
			echo $assets->readfile($path);
		} elseif( $ext == 'css' ) {

			//Если файл не найден, пробуем найти scss
			$filename = pathinfo($path, PATHINFO_FILENAME);
			$scss = $assets->normalizePath(pathinfo($path, PATHINFO_DIRNAME) . '/' . $filename . '.scss');

			//Если файл существует
			if( $assets->full_path($scss) ) {
				echo $assets->readfile($scss);
			} else {
				throw new Boot_Exception('File ' . $path . ' not found', 404);
			}
		} else {
			echo readfile($assets->find_path($path));
		}
	}
}