<?
require_once("config.php");  // Класс для вывода отладочной инфы в консоль браузера (JS concole.log)
$debug = 1; // Вывод отладочной инфы в шаблон  1=выводить
$debugInfo = '';

$arDate = array(
    'last_month_year' => date('Y', strtotime('now -1 month')),
    'last_month' => date('m', strtotime('now -1 month')),
    'last_week_monday' => date('Y-m-d', strtotime('last Monday -1 week')),
    'last_week_sunday' => date('Y-m-d', strtotime('last Sunday')),
    'last_month_first_monday' => date('Y-m-d', strtotime(date('Y-m', strtotime('now -1 month')) . "-" .
        compute_day(1, 1, date('m', strtotime('now -1 month')), date('Y', strtotime('now -1 month'))))),
    'this_year' => date('Y', strtotime('now')),
    'this_month' => date('m', strtotime('now')),
    'this_week_monday' => date("Y-m-d", strtotime("last Monday")),
    'this_month_first_monday' => date('Y-m-d', strtotime(date('Y-m', strtotime('now')) . "-" .
        compute_day(1, 1, date('m', strtotime('now')), date('Y', strtotime('now'))))),
    'yesterday' => date("Y-m-d", strtotime("-1 day")),
    'today' => date("Y-m-d"),
    'this_month_first_day' => date("Y-m-d", strtotime(date('Y-m', strtotime('now')) . "-01")),
    'last_month_last_day' => date("Y-m-d", strtotime('last day of previous month')),
    'last_month_first_day' => date("Y-m-d", strtotime(
		                            date('Y', strtotime('now -1 month')) . "-" .
		                            date('m', strtotime('now -1 month')) . "-01")),
);

// Каталог с шаблонами Twig - шаблонизатор
$twigTemplatesDir = 'views';

/**
 * @param integer $weekNumber Номер недели в месяце.
 * @param integer $dayOfWeek Порядковый номер дня недели.
 * @param integer $monthNumber Порядковый номер месяца.
 * @param integer $year Год.
 * @return integer День месяца.
 * первый понедельник января 2011
 * echo compute_day(1, 1, 1, 2011); // 3
 */
function compute_day($weekNumber, $dayOfWeek, $monthNumber, $year)
{
	// порядковый номер дня недели первого дня месяца $monthNumber
	$dayOfWeekFirstDayOfMonth = date('w', mktime(0, 0, 0, $monthNumber, 1, $year));

	// сколько дней осталось до дня недели $dayOfWeek относительно дня недели $dayOfWeekFirstDayOfMonth
	$diference = 0;

	// если нужный день недели $dayOfWeek только наступит относительно дня недели $dayOfWeekFirstDayOfMonth
	if ($dayOfWeekFirstDayOfMonth <= $dayOfWeek) {
		$diference = $dayOfWeek - $dayOfWeekFirstDayOfMonth;
	} // если нужный день недели $dayOfWeek уже прошёл относительно дня недели $dayOfWeekFirstDayOfMonth
	else {
		$diference = 7 - $dayOfWeekFirstDayOfMonth + $dayOfWeek;
	}

	return 1 + $diference + ($weekNumber - 1) * 7;
}

/**
 * чистим строку
 * @param string $str  Строка для очистки
 * @param string $delNL  Флаги: notDelNL -(по умолчанию) оставляем переносы строк; delNL -чистим переносы строк
 * @return string
 */

function cleanStr($str, $delNL='notDelNL')
{
    if ($delNL == 'delNL') {
        $str = trim(str_replace(array("\r\n", "\r", "\n", "\t"), " ", $str)); //удаляем переносы строки, табуляцию
    } else {
        $str = trim(str_replace(array("\t"), " ", $str)); //удаляем табуляцию
    }
    $str = strip_tags($str); //удаляем теги из описания
    $str = preg_replace('/ {2,}/', ' ', $str); //удаляем лишние пробелы
    $str = StripBadUTF8($str);
    $str = html_entity_decode($str);
    return $str;
}

function StripBadUTF8($str)
{ // Анализирует строку в UTF-8 и убирает «битые» символы.
    $ret = '';
    for ($i = 0; $i < strlen($str);) {
        $tmp = $str{$i++};
        $ch = ord($tmp);
        if ($ch > 0x7F) {
            if ($ch < 0xC0) continue;
            elseif ($ch < 0xE0) $di = 1;
            elseif ($ch < 0xF0) $di = 2;
            elseif ($ch < 0xF8) $di = 3;
            elseif ($ch < 0xFC) $di = 4;
            elseif ($ch < 0xFE) $di = 5;
            else continue;

            for ($j = 0; $j < $di; $j++) {
                $tmp .= $ch = $str{$i + $j};
                $ch = ord($ch);
                if ($ch < 0x80 || $ch > 0xBF)
                    continue 2;
            }
            $i += $di;
        }
        $ret .= $tmp;
    }
    return $ret;
}

function debug()
{
    define("NL", "\r\n");

    $args = func_get_args();
    global $debugInfo;
    $debugI = "";
    ob_start();
    debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    //$dd = debug_backtrace();
    //var_export($dd[0]['file']);
    //var_export($dd);
    $debugI .= "\n\r" . ob_get_clean();

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    ) {
        // Если к нам идёт Ajax запрос, то ловим его
        $isAjax = 1;
    } else {//Если это не ajax запрос
        $isAjax = 0;
    }

    foreach ($args as $key => $value) {
        if (!empty($value)) {
            if (is_object($value) || is_array($value)) {
                ob_start();
                var_export($value);
                $debugI .= ob_get_clean() . "\n\r";
            }
            if (is_scalar($value) && !is_string($value)) $debugI .= '{' . $value . '}';
            if (is_string($value)) $debugI .= $value;
        }
    }
    $debugInfo .= $debugI."\n\r";
}





