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

	private $user_id = null;


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
	public function doRequest($relative_url, $params=array(), $method='GET', $use_access_token=true, $file=null) {
		$url = $this->home_server.'/_matrix/'.trim($relative_url,'/');

		$get_params = array();

		if ($file) {
			$http_headers = array('Accept: application/json','Content-type: '.mime_content_type($file));
		} else {
			$http_headers = array('Accept: application/json','Content-type: application/json');
		}


		if ($use_access_token) {
			$get_params['access_token'] = $this->access_token;
		}

		if ($file) {
			$get_params['filename'] = $params['filename'];
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
		curl_setopt($ch,CURLOPT_USERAGENT,'Nextcloud');

		//FIXME set proper ssl verification
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		switch ($method) {
			case 'GET':
				break;
			case 'POST':

				if ($file) {
					#curl_setopt($ch,CURLOPT_VERBOSE,true);
					#$flog = fopen('/tmp/curllog','a');
					#fseek($flog,0,SEEK_END);
					#curl_setopt($ch,CURLOPT_STDERR,$flog);

					curl_setopt($ch,CURLOPT_POST,true);

					$mime =  mime_content_type($file);

					curl_setopt(
						$ch,
						CURLOPT_POSTFIELDS,
						file_get_contents($file)
					);


					curl_setopt($ch,CURLOPT_HEADER,false);

				} else {
					curl_setopt($ch,CURLOPT_POST,true);
					curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($params,JSON_FORCE_OBJECT));
				}

				break;

			case 'PUT':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
				curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($params,JSON_FORCE_OBJECT));
				break;

			case 'DELETE':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
				curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($params,JSON_FORCE_OBJECT));
				break;

			default:
				throw new Exception("MatrixOrg: invalid method to do a request.");
		}

		curl_setopt($ch,CURLOPT_HTTPHEADER,$http_headers);
		#echo "BEFORE CURL_EXEC";
		$result = curl_exec($ch);

		#fclose($flog);
		#echo "AFTER CURL_EXEC";

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
			'url' => $url,
			'params' => $params,
			'status' => $httpcode,
			'data' => $json_result
		);

	}

	//TODO tentar conexão persistente HTTP 1.1
	public function login($username, $password) {
		if ($this->access_token != null) return;

		$fields = array(
			'type' => 'm.login.password',
			'user' => $username,
			'password' => $password
		);

		$result = $this->doRequest('client/r0/login',$fields,'POST',false);

		$this->access_token = ($result['status'] == 200) ? $result['data']['access_token'] : null;
		$this->user_id = ($result['status'] == 200) ? $result['data']['user_id'] : null;


		if ($this->access_token == null) {
			throw new \MatrixOrg_Exception_Connection("Matrix.org login: could not connect.");
		}
	}

	/**
	 * Returns true if there is a connection with Matrix.org server
	 *
	 * As Matrix.org server API is a WebService, there is not a persistent
	 * connection to the server. Instead we simulate it by knowing if the last
	 * login attempt was successful or not.
	 *
	 * @return bool true if the last login tentative was successful
	 */
	public function connected() {
		return $this->access_token != null;
	}


	public function sync($since=null,$filter=0) {
		$fields = array(
			'filter'       => $filter,
			'timeout'      => $this->request_timeout
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

	/**
	 * @return mixed
	 */
	public function getHomeServer() {
		return $this->home_server;
	}

	/**
	 * Converts a mxc:// address to a file to a download address
	 * @param $mxcAddress
	 */
	public function downloadUrl($mxcAddress) {
		if (preg_match('/^mxc:\/\//',$mxcAddress)) {
			return $this->home_server.'/_matrix/media/v1/download/'.preg_replace('/^mxc:\/\//',"",$mxcAddress);
		}
		return "";
	}

	/**
	 * @param $file filename in current filesystem
	 * @param $filename filename in matrix
	 */
	public function upload($file, $filename) {
		return $this->doRequest('media/v1/upload',array('filename' => $filename),'POST',true,$file);
	}

	/**************************************************************************
	 * User Methods                                                           *
	 **************************************************************************/

	/**
	 * Gets the user id of the logged-in user
	 */
	public function getUserId() {
		return $this->user_id;
	}

	/**
	 * @param $params (must follow spec in http://matrix.org/docs/spec/client_server/r0.2.0.html#id122)
	 * @param $user_id (optional) If not provided, will use the logged in user_id
	 */
	public function createFilter($params,$user_id=null) {
		if ($user_id == null) {
			$user_id = $this->user_id;
		}
		return $this->doRequest('client/r0/user/'.urlencode($user_id).'/filter',$params,'POST',true);
	}

	/**
	 * @param $params (must follow spec in http://matrix.org/docs/spec/client_server/r0.2.0.html#id121)
	 * @param $user_id (optional) If not provided, will use the logged in user_id
	 */
	public function queryFilter($filter_id,$user_id=null) {
		if ($user_id == null) {
			$user_id = $this->user_id;
		}
		return $this->doRequest('client/r0/user/'.urlencode($user_id).'/filter/'.urlencode($filter_id),array(),'GET',true);
	}

	/**************************************************************************
	 * Room Methods                                                           *
	 **************************************************************************/
	public function messages($room_id, $from, $to=null, $dir='b', $limit=20, $filter=[]) {
		$fields = [];
		$fields['from'] = $from;
		if ($to) $fields['to'] = $to;
		$fields['dir'] = $dir ? $dir : 'b';
		$fields['limit'] = $limit ? $limit : 20;
		if ($filter) $fields['filter'] = json_encode($filter,JSON_FORCE_OBJECT);

		return $this->doRequest('client/r0/rooms/'.urlencode($room_id).'/messages',$fields,'GET',true);
	}

	public function redact($room_id, $event_id) {
		return $this->doRequest('client/r0/rooms/'.urlencode($room_id).'/redact/'.urlencode($event_id),[],'POST',true);
	}

	public function send($room_id, $event_type, $params=[], $txn_id=null) {
		$txn_id = 'm'.round(microtime(true) * 1000).".0";

		return $this->doRequest('client/r0/rooms/'.urlencode($room_id).'/send/'.$event_type.'/'.$txn_id,$params,'PUT',true);
	}

	/**************************************************************************
	 * Message Methods                                                        *
	 **************************************************************************/

	public function sendFile($room_id, $file, $filename, $file_thumbnail = null) {

		$final_result = array();

		$file_info = array(
			'size'     => filesize($file),
			'mimetype' => mime_content_type($file)
		);

		$mime_group = explode('/',$file_info['mimetype'])[0];

		$file_type = (in_array($mime_group,array('video','audio','image'))) ? 'm.'.$mime_group : 'm.file';

		if ($mime_group == 'image') {
			list($width, $height) = @getimagesize($file);
			$file_info['w'] = $width;
			$file_info['h'] = $height;

			if ($file_thumbnail) {
				$thumb_up_res = $this->upload($file_thumbnail,"undefined");
				list($thumb_w,$thumb_h) = @getimagesize($file_thumbnail);
				$thumb_file_info = array(
					'w'        => $thumb_w,
					'h'        => $thumb_h,
					'mimetype' => mime_content_type($file_thumbnail),
					'size'     => filesize($file_thumbnail)
				);

				$file_info['thumbnail_info'] = $thumb_file_info;
				$file_info['thumbnail_url'] = $thumb_up_res['data']['content_uri'];

				$final_result['thumb_upload_request_result'] = $thumb_up_res;
			}
		}

		$result = $this->upload($file,$filename);

		$final_result['file_upload_request_result'] = $result;

		if ($result['status'] == 200) {

			$final_result['url'] = $result['data']['content_uri'];

			$final_result = array_merge($final_result,$file_info);

			$final_result['msgtype'] = $file_type;

			$params = array(
				"body"    => $filename,
    			"info"    => $file_info,
    			"msgtype" => $file_type,
    			"url"     => $result['data']['content_uri']
			);

			$result = $this->send($room_id, 'm.room.message', $params);

			$final_result['3rd_request_result'] = $result;

			if ($result['status'] == 200) {
				$final_result['event_id'] = $result['data']['event_id'];
			}
		}

		if ($result['status'] == 200) {
			return $final_result;
		}
		return false;
	}

}
