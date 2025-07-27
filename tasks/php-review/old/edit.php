<?php

use TV\API;

$startTime = microtime(true);

try {
	tStart();

	$module = req('module', '', true);
	$oper = req('oper', '', true);
	if (!is_string($oper)) $oper = '--';
} catch (Exception $Exception) {
	core()->error($Exception->getMessage(), null, $Exception->getCode());
	core()->exception_json();
}

$func = req('func'); // Функция модуля, отвечающая за получения данных
if (!$func) $func = req('fedit'); // deprecated

if ($oper != 'add') {
	$id = req('id', '');

	// разрешенные значения для $id
	if (is_array($id)) {
		foreach ($id as $index => $id_i) {
			$id[$index] = (int) $id_i;
		}
	} elseif (!is_numeric($id)) {
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

try {
	$isNewServicesSchema = preg_match('~_2$~', $module);
	if ($isNewServicesSchema) {
		$result = API\Entry::call($oper, $module, $func, $_REQUEST);
	} else {
		switch ($oper) {
			case 'add':
				$result = m($module)->add($data, $func);

				break;
			case 'edit':
				$result = m($module)->edit($data, $id, $func);

				break;
			case 'del':
				$result = m($module)->del($id, $func);

				break;
			default:
				throw new Exception('Unknown oper', ERROR_CODE_OPERATOR);
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

if ($result === false or $result === null) $result = 0;

core()->exception_json($result);
