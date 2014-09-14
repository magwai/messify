<?php
/**
 * messify
 *
 * Copyright (c) 2012 Magwai Ltd. <info@magwai.ru>, http://magwai.ru
 * Licensed under the MIT License:
 * http://www.opensource.org/licenses/mit-license.php

USAGE:

$messify = new messify(array(
	'token' => 'ключ_токена'
));
try {
	// Пример получения токена
	$token = $messify->fetch_token();

	// Устанавливаем токен для messify
	$messify->set_token($token['token']);

	// Устанавливаем секрет токен для messify
	$messify->set_token_secret($token['token_secret']);

	// Расширенное инфо
	$result = $messify->check_token(array(
		'info' => 1
	));
	var_dump($result);

	// Сжатие CSS
	$css = $messify->compress('css', array('yui', 'cssmin'), '.test_class{ border:1px solid red; }');
	var_dump($css);

	// Сжатие JavaScript
	$js = $messify->compress('js', array('gcc', 'yui', 'jsmin'), 'function test($str) { alert($str); }');
	var_dump($js);
}
catch (Exception $e) {
	var_dump($e);
}

*/

class messify {
	private $_service_host = 'messify.ru';
	private $_token = null;
	private $_token_secret = null;
	private $_host = null;

	public function __construct($options = array()) {
		if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) {
			$this->set_host($_SERVER['HTTP_HOST']);
		}
		if (!$options) {
			return;
		}
		if (is_array($options)) {
			foreach ($options as $key => $value) {
				$method = 'set_'.$key;
				if (method_exists($this, $method)) {
					$this->$method($value);
				}
				else {
					$this->_error(30, 'Option "'.$key.'" does not exists');
				}
			}
		}
		else {
			$this->_error(31, 'Options should be Array');
		}
	}

	public function set_service_host($host) {
		if ($host) {
			$this->_service_host = $host;
		}
		else {
			$this->_error(32, 'Service host can not be empty');
		}
	}

	public function set_host($host) {
		if ($host) {
			$this->_host = $host;
		}
		else {
			$this->_error(33, 'Host can not be empty');
		}
	}

	public function set_token($token) {
		if ($token) {
			$this->_token = $token;
		}
		else {
			$this->_error(34, 'Token can not be empty');
		}
	}

	public function set_token_secret($token_secret) {
		if ($token_secret) {
			$this->_token_secret = $token_secret;
		}
		else {
			$this->_error(35, 'Token secret can not be empty');
		}
	}

	public function token($param = array()) {
		return $this->_request('token', $param);
	}

	public function compress($type, $compressors, $data) {
		if (!$this->_token) {
			$this->_error(36, 'Token is not set');
		}
		if (!$this->_token_secret) {
			$this->_error(37, 'Token secret is not set');
		}
		if (!$compressors || !is_array($compressors)) {
			$this->_error(38, 'Compressors can not be empty');
		}
		return $this->_request('compress', array(
			'type' => $type,
			'compressors' => $compressors,
			'data' => $data
		));
	}

	private function _error($code, $message = '') {
		throw new Exception($message, $code);
	}

	private function _request($endpoint, $post = array()) {
		if (!$this->_host) {
			$this->_error(39, 'Can not determine host');
		}
		try {
			$post = array_merge(array(
				'token' => $this->_token,
				'token_secret' => $this->_token_secret,
				'host' => $this->_host
			), $post);
			$result = file_get_contents('http://'.$this->_service_host.'/api/'.$endpoint, false, stream_context_create(array(
				'http' => array(
					'method' =>	'POST',
					'user_agent' => 'messify-1.0',
					'content' => http_build_query($post),
				)
			)));
		}
		catch (Exception $e) {
			$this->_error($e->getCode(), $e->getMessage());
		}
		$code = 8;
		$message = 'HTTP error';
		$result_encoded = $this->_result_encode($result);
		if (!$result_encoded) {
			$this->_error(40, "No result available. Returned:\n\n".$result);
		}
		if (isset($result_encoded['error'])) {
			$code = $result_encoded['error']['code'];
			$message = $result_encoded['error']['message'];
		}
		else {
			return $result_encoded;
		}
		$this->_error($code, $message);
	}

	private function _result_encode($json) {
		try {
			$result = json_decode($json, true);
		}
		catch (Exception $e) {
			$this->_error($e->getCode(), $e->getMessage());
		}
		return $result;
	}
}