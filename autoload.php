<?php
/**
 * Copyright (c) 2016 Cooperativa EITA (eita.org.br)
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */


function matrix_api_php_client_autoload($className)
{
	#echo $className;
	$classPath = explode('_', $className);
	if ($classPath[0] != 'MatrixOrg') {
		return;
	}
	// Drop 'MatrixOrg', and maximum class file path depth in this project is 3.
	$classPath = array_slice($classPath, 1, 2);
	$filePath = dirname(__FILE__) . '/src/' . implode('/', $classPath) . '.php';

	#echo $filePath;
	if (file_exists($filePath)) {
	#	echo "file exists";
		require_once($filePath);
	} else {
	#	echo "NOT exists";
	}
}
spl_autoload_register('matrix_api_php_client_autoload');