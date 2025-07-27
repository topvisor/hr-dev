<?php

use DB\PDO\Mysql;
use TV\API;

$startTime = microtime(true);

try {
	tStart();

	$module = request('module', '', true);

	if (isset($_POST['page']) and $_POST['page'] < 1 or isset($_REQUEST['page']) and $_REQUEST['page'] < 1) {
		throw new Exception(
			"'Page' must be greater than zero",
		);
	}
} catch (Exception $Exception) {
	core()->error($Exception->getMessage(), null, $Exception->getCode());
	core()->exception_json();
}

// ====================== ПОДГОТОВКА ЗАПРОСА ======================

$getList = req('getList'); // Имя колонки, выводимой в результате, если необходимо вывести список только по одной колонке
$getList = Mysql::prepare_column_name($getList);

$getFormat = req('getFormat', 'json_pager'); // Формат вывода данных

$func = request('func'); // Функция модуля, отвечающая за получения данных
if (!$func) $func = request('fget'); // deprecated
if (!is_string($func)) $func = '--';

$limit = (int) request('rows', 0); // Количество элементов на странице
if (!$limit) $limit = (int) request('limit', 0); // Количество элементов на странице
$pageNumber = (int) request('page', 1); // Номер страницы запроса
$offset = (int) request('offset', 0); // Количество пропускаемых записей
$sidx = request('sidx'); // Имя поля по которому будет вестись сортировка
if ($sidx) {
	$sidx = str_replace('`', '', $sidx);
	$sidx = str_replace('.', '`.`', $sidx);
	$sidx = str_replace(',', '`,`', $sidx);
	$sidx = "`$sidx`";
}
$sord = strtoupper(request('sord')); // Направление сортировки
if ($sord and $sord != 'ASC' and $sord != 'DESC') $sord = '';
$o = trim(str_replace(',', " $sord,", $sidx) . ' ' . $sord);

$offset = $offset + ($pageNumber - 1) * $limit;
$limitSql = ($limit) ? $offset . ', ' . $limit : '';

// ====================== ВЫПОЛНЕНИЕ ЗАПРОСА ======================
try {
	$isNewServicesSchema = preg_match('~_2$~', $module);

	if ($isNewServicesSchema) {
		$result = API\Entry::call('get', $module, $func, $_REQUEST);
	} else {
		$result = m($module)->get('', $limitSql, $o, $func);
	}

	if ($getFormat != 'apiV2') $result = (array) $result;

	if (is_array($result) and isset($result[0]) and is_array($result[0]) and isset($result[0]['total'])) {
		$total = (int) $result[0]['total'];
		array_shift($result);
	} else {
		if (!is_null(core()->resultTotal)) {
			$total = core()->resultTotal;
		} else $total = (int) dbh()->selFoundRows();

		if (is_array($result)) {
			if (count($result) > $total) $total = count($result);
		}
	}
} catch (Exception $Exception) {
	if ($Exception->getMessage() || $Exception->getCode()) {
		if ($Exception instanceof TV\Exception) {
			core()->error($Exception->getMessage(), $Exception->details, $Exception->getCode());
		} else {
			core()->error($Exception->getMessage(), null, $Exception->getCode());
		}
	}

	$result = 0;
}

Core::$metrics['total'] = microtime(true) - $startTime;

core()->addHeadersMetrics();

Core::$metadata['oper'] = 'get';
core()->addHeadersMetadata();

// вывести ошибку, без формирования результата
if (core()->errors) core()->exception_json();

include_once('get.results.php');
