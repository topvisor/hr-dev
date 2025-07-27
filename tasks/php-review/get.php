<?php

use DB\PDO\Mysql;
use TV\API;

$startTime = microtime(true);

try {
	tStart();

	$module = req('module', '', true);
	// todo: сделать обязательным после обновления всего js кода на `API.gen()`
	$oper = r_exp('oper', 'get', false, ['get', 'add', 'edit', 'del']);
	if (!is_string($oper)) $oper = '--';
} catch (Exception $Exception) {
	core()->error($Exception->getMessage(), null, $Exception->getCode());
	core()->exception_json();
}

/**
 * Генерирует данные, очищая значения входного массива и исключая системные параметры API
 *
 * @return array Очищенные данные из входного массива
 */
function genData(): array {
	$memoryLimit = ini_get('memory_limit');
	ini_set('memory_limit', '64M');

	$data = [];
	foreach ($_POST as $index => $val) {
		if (
			$index == 'ssi' ||
			$index == 'id' ||
			$index == 'module' ||
			$index == 'oper' ||
			$index == 'func' ||
			$index == 'type_result' ||
			$index == 'api_key' ||
			$index == 'app_auth'
		) {
			continue;
		}

		$data[$index] = sanitize($val);
	}
	ini_set('memory_limit', $memoryLimit);

	return $data;
}

if ($oper === 'get') {
	$getList = req('getList'); // Имя колонки, выводимой в результате, если необходимо вывести список только по одной колонке
	$getList = Mysql::prepare_column_name($getList);

	$getFormat = req('getFormat'); // Формат вывода данных
}

if ($oper === 'edit' || $oper === 'del') {
	$id = req('id', '');

	// разрешенные значения для $id
	if (is_array($id)) {
		// todo: убрать поддержку передачи id как массива и удалить
		foreach ($id as $index => $id_i) {
			$id[$index] = (int) $id_i;
		}
	} elseif (!is_numeric($id)) {
		// todo: удалить после обновление API v2 на новый формат 2025
		$id = explode(',', $id);
		foreach ($id as $index => $id_i) {
			$id[$index] = (int) $id_i;
		}
		$id = implode(',', $id);

		if (!$id) $id = (int) $id;
	} else {
		$id = (int) $id;
	}
}

$func = req('func'); // Функция модуля, отвечающая за получения данных
if (!is_string($func)) $func = '';

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
		$result = API\Entry::call($oper, $module, $func, $_REQUEST);
	} else {
		switch ($oper) {
			case 'get':
				$result = m($module)->get('', $limitSql, $o, $func);

				break;
			case 'add':
				$data = genData();
				$result = m($module)->add($data, $func);

				break;
			case 'edit':
				$data = genData();
				$result = m($module)->edit($data, $id, $func);

				break;
			case 'del':
				$result = m($module)->del($id, $func);

				break;
			default:
				exit();
		}
	}

	if ($oper === 'get') {
		if ($getFormat === 'tpl') $result = (array) $result;

		if (is_array($result) and isset($result[0]) and is_array($result[0]) and isset($result[0]['total'])) {
			// todo: заменить во всем коде установку `total` в таком виде на `core()->setResultTotal()`
			$total = (int) $result[0]['total'];
			array_shift($result);
		} else {
			if (!is_null(core()->resultTotal)) {
				$total = core()->resultTotal;
			} else {
				$total = (int) dbh()->selFoundRows();
			}

			if (is_array($result)) {
				if (count($result) > $total) $total = count($result);
			}
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

Core::$metadata['oper'] = $oper;
core()->addHeadersMetadata();

// вывести ошибку, без формирования результата
if (core()->errors) core()->exception_json();

if ($oper === 'get') {
	include_once('get.results.php');
} else {
	if ($result === false or $result === null) $result = 0;

	core()->exception_json($result);
}
