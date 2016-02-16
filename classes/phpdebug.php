<?php
/**
 * Created by PhpStorm.
 * User: Nikk
 * Date: 18.01.2016
 * Time: 19:39
 *
 * // Включаем код для отладки и определяем объект
 * require_once("PHPDebug.php");
 * $debug = new PHPDebug();
 *
 * // Простое сообщение на консоль
 * $debug->debug("Очень простое сообщение на консоль");
 *
 * // Вывод переменной на консоль
 * $x = 3;
 * $y = 5;
 * $z = $x/$y;
 * $debug->debug("Переменная Z: ", $z);
 *
 * // Предупреждение
 * $debug->debug("Простое предупреждение", null, WARN);
 *
 * // Информация
 * $debug->debug("Простое информационное сообщение", null, INFO);
 *
 * // Ошибка
 * $debug->debug("Простое сообщение об ошибке", null, ERROR);
 *
 * // Выводим массив в консоль
 * $fruits = array("банан", "яблоко", "клубника", "ананас");
 * $fruits = array_reverse($fruits);
 * $debug->debug("Массив фруктов:", $fruits);
 *
 * // Выводим объект на консоль
 * $book               = new stdClass;
 * $book->title        = "Гарри Потный и кто-то из Ашхабада";
 * $book->author       = "Д. K. Роулинг";
 * $book->publisher    = "Arthur A. Levine Books";
 * $book->amazon_link  = "http://www.amazon.com/dp/0439136369/";
 * $debug->debug("Объект: ", $book);
 */
class PHPDebug
{

    function __construct()
    {
        if (!defined("LOG")) define("LOG", 1);
        if (!defined("INFO")) define("INFO", 2);
        if (!defined("WARN")) define("WARN", 3);
        if (!defined("ERROR")) define("ERROR", 4);

        define("NL", "\r\n");
        echo '<script type="text/javascript">' . NL;

        /// Данный код предназначен для браузеров без консоли
        echo 'if (!window.console) console = {};';
        echo 'console.log = console.log || function(){};';
        echo 'console.warn = console.warn || function(){};';
        echo 'console.error = console.error || function(){};';
        echo 'console.info = console.info || function(){};';
        echo 'console.debug = console.debug || function(){};';
        echo '</script>';
        /// Конец секции для браузеров без консоли
    }

    public static function debug($name, $var = null, $type = LOG)
    {
        echo '<script type="text/javascript">' . NL;
        switch ($type) {
            case LOG:
                echo 'console.log("' . $name . '");' . NL;
                break;
            case INFO:
                echo 'console.info("' . $name . '");' . NL;
                break;
            case WARN:
                echo 'console.warn("' . $name . '");' . NL;
                break;
            case ERROR:
                echo 'console.error("' . $name . '");' . NL;
                break;
        }

        if (!empty($var)) {
            if (is_object($var) || is_array($var)) {
                $object = json_encode($var);
                echo 'var object' . preg_replace('~[^A-Z|0-9]~i', "_", $name) . ' = \'' . str_replace("'", "\'", $object) . '\';' . NL;
                echo 'var val' . preg_replace('~[^A-Z|0-9]~i', "_", $name) . ' = eval("(" + object' . preg_replace('~[^A-Z|0-9]~i', "_", $name) . ' + ")" );' . NL;
                switch ($type) {
                    case LOG:
                        echo 'console.debug(val' . preg_replace('~[^A-Z|0-9]~i', "_", $name) . ');' . NL;
                        break;
                    case INFO:
                        echo 'console.info(val' . preg_replace('~[^A-Z|0-9]~i', "_", $name) . ');' . NL;
                        break;
                    case WARN:
                        echo 'console.warn(val' . preg_replace('~[^A-Z|0-9]~i', "_", $name) . ');' . NL;
                        break;
                    case ERROR:
                        echo 'console.error(val' . preg_replace('~[^A-Z|0-9]~i', "_", $name) . ');' . NL;
                        break;
                }
            } else {
                switch ($type) {
                    case LOG:
                        echo 'console.debug("' . str_replace('"', '\\"', $var) . '");' . NL;
                        break;
                    case INFO:
                        echo 'console.info("' . str_replace('"', '\\"', $var) . '");' . NL;
                        break;
                    case WARN:
                        echo 'console.warn("' . str_replace('"', '\\"', $var) . '");' . NL;
                        break;
                    case ERROR:
                        echo 'console.error("' . str_replace('"', '\\"', $var) . '");' . NL;
                        break;
                }
            }
        }
        echo '</script>' . NL;
    }
}