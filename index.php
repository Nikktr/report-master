<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/apps/vendor/autoload.php');  // init Composer
require_once($_SERVER['DOCUMENT_ROOT'] . '/apps/vendor/phpoffice/phpexcel-1-8-1/Classes/PHPExcel.php');  // init Composer
require_once("tools.php");
require_once("classes/cb24.php");
require_once("classes/capplication.php");

global $firstDay, $lastDay, $arDate;

if (!empty($_REQUEST)) {
    $obApp = new CApplication();
    $obApp->start();
   if ($obApp->is_ajax_mode) {
       $obApp->manageAjax($_REQUEST['operation']);
   } else {
       header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
       header('Expires: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
       header("Cache-Control: no-cache, must-revalidate");
       header("Pragma: no-cache");
       $obApp->getItems($arDate[$obApp->arSettings['firstDay']], $arDate[$obApp->arSettings['lastDay']]);
       $obApp->draw_main_UI();

      // echo "<pre>" . var_export($arDate) . "</pre> "; // Вывод отладочной инфы
   }
}












