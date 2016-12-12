<?php
/**
 * Copyright (c) 2016 Cooperativa EITA (eita.org.br)
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

/**
 * Class MatrixOrg_API
 *
 * Implements the Client-Server communication, according to the API
 *
 *
 * @package MatrixOrg
 */
class MatrixOrg_API {

	public function __construct($params) {
	#	echo "A";
	}

	public function login($home_server, $username, $password) {
		$url = $home_server.'/_matrix/client/r0/login';
		$fields = array(
			'type' => 'm.login.password',
			'user' => $username,
			'password' => $password
		);

		//url-ify the data for the POST
		$fields_string = http_build_query($fields);

		//open connection
		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_POST,count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($fields));
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_HEADER,'Content-Type: application/json');

		//execute post
		$result = curl_exec($ch);

		\OCP\Util::writeLog(
			'files_external',
			'RESULT: '.$result,
			\OCP\Util::ERROR
		);

		if ($result === FALSE) { return false; }

		return true;
	}
}
