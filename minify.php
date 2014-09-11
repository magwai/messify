<?php
/**
 * minify
 *
 * Copyright (c) 2012 Magwai Ltd. <info@magwai.ru>, http://magwai.ru
 * Licensed under the MIT License:
 * http://www.opensource.org/licenses/mit-license.php

USAGE:

$minify = new minify(array(
	'token' => 'ключ_токена'
));
try {
	// Пример получения токена
	$token = $minify->fetch_token();

	// Устанавливаем токен для minify
	$minify->set_token($token);

	// Проверяем валидность токена
	$result = $minify->check_token($token);
	var_dump($result);

	// Сжатие CSS
	$css = $minify->compress('css', array('yui', 'cssmin'), '.test_class{ border:1px solid red; }');
	var_dump($css);

	// Сжатие JavaScript
	$js = $minify->compress('js', array('gcc', 'yui', 'jsmin'), 'function test($str) { alert($str); }');
	var_dump($js);
}
catch (Exception $e) {
	var_dump($e);
}

*/

class minify {
	private $_service_host = 'util.magwai.ru';
	private $_token = null;

	public function __construct($options = array()) {
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
					$this->_error(2, 'Option "'.$key.'" does not exists');
				}
			}
		}
		else {
			$this->_error(1, 'Options should be Array');
		}
	}

	public function service_host($host) {
		if ($host) {
			$this->_service_host = $host;
		}
		else {
			$this->_error(3, 'Service host can not be empty');
		}
	}

	public function set_token($token) {
		if ($token) {
			$this->_token = $token;
		}
		else {
			$this->_error(4, 'Token can not be empty');
		}
	}

	public function check_token($token = null) {
		return $this->_request('check', array(
			'token' => $token === null ? $this->_token : $token,
		));
	}

	public function fetch_token() {
		return $this->_request('token');
	}

	public function compress($type, $compressors, $data) {
		if (!$this->_token) {
			$this->_error(5, 'Token is not set');
		}
		if (!$compressors || !is_array($compressors)) {
			$this->_error(6, 'Compressors can not be empty');
		}
		return $this->_request('compress', array(
			'token' => $this->_token,
			'type' => $type,
			'compressors' => $compressors,
			'data' => $data
		));
	}

	private function _error($code, $message = '') {
		throw new Exception($message, $code);
	}

	private function _request($endpoint, $post = array()) {
		try {
			$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
			$result = file_get_contents('http://'.$this->_service_host.'/minify/'.$endpoint, false, stream_context_create(array(
				'http' => array(
					'method' =>	'POST',
					'user_agent' => 'minify',
					'header' => $host ? array(
						"Referer: ".$host
					) : array(),
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
			$this->_error(7, "No result available. Returned:\n\n".$result);
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