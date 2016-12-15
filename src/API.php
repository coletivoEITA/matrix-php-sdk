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

	private $home_server;
	private $access_token = null;

	private $request_timeout = 30000;

	private $return_assoc_array = true;


	/**
	 * @var - (bool) if connection could happen with current home_server, user, auth
	 */
	public $could_connect = null;

	public function __construct($home_server,$access_token=null) {
		$this->home_server = $home_server;
		$this->access_token = $access_token;
	}

	/**
	 * @param $method       - HTTP method
	 * @param $relative_url - Url to reach in the homeserver, without the
	 *                        servername and the '_matrix/' part
	 * @param $params       - Request parameters
	 * @param $use_access_token - True for methods that need auth
	 */
	public function doRequest($relative_url, $params=array(), $method='GET', $use_access_token=true) {
		$url = $this->home_server.'/_matrix/'.trim($relative_url,'/');

		$get_params = array();

		if ($use_access_token) {
			$get_params['access_token'] = $this->access_token;
		}

		if ($method=='GET') {
			$get_params = array_merge($get_params,$params);
		}

		if (count($get_params) > 0) {
			$url .= '?'.http_build_query($get_params);
		}

		//open connection
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_HEADER,'Content-Type: application/json');
		curl_setopt($ch,CURLOPT_USERAGENT,'Nextcloud');

		switch ($method) {
			case 'GET':
				curl_setopt($ch,CURLOPT_NOBODY,true);
				break;
			case 'POST':
				curl_setopt($ch,CURLOPT_POST,1);
				curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($params));
				break;

			case 'PUT':
				curl_setopt($ch,CURLOPT_PUT,1);
				curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($params));
				break;

			case 'DELETE':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
				curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($params));
				break;

			default:
				throw new Exception("MatrixOrg: invalid method to do a request.");
		}

		$result = curl_exec($ch);

		if ($result === false) {
			$errno = curl_errno($ch);

			switch ($errno) {
				case CURLE_OPERATION_TIMEOUTED:
					throw new \MatrixOrg_Exception_Timeout(curl_error($ch));
				default:
					throw new \MatrixOrg_Exception_NetworkError(curl_error($ch));
			}
		}

		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		$json_result = json_decode($result, $this->return_assoc_array);

		return array(
			'status' => $httpcode,
			'data' => $json_result
		);

	}

	public function login($username, $password) {

		$fields = array(
			'type' => 'm.login.password',
			'user' => $username,
			'password' => $password
		);

		$result = $this->doRequest('client/r0/login',$fields,'POST',false);

		$this->access_token = ($result['status'] == 200) ? $result['data']['access_token'] : null;

		//TODO treat case where server is not reachable
		$this->could_connect = !empty($this->access_token);

		return $result;
	}

	public function sync($since=null,$filter=1) {
		$fields = array(
			'filter'  => $filter,
			'timeout' => $this->request_timeout
		);

		if ($since != null) $fields['since'] = $since;

		return $this->doRequest('client/r0/sync',$fields,'GET',true);
	}


	/**
	 * @return mixed
	 */
	public function getAccessToken() {
		return $this->access_token;
	}

	/**
	 * @param mixed $access_token
	 */
	public function setAccessToken($access_token) {
		$this->access_token = $access_token;
	}
}
