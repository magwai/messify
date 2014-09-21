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
	$token = $messify->token();

	// Устанавливаем токен для messify
	$messify->set_token($token['token']);

	// Устанавливаем секрет токен для messify
	$messify->set_token_secret($token['token_secret']);

	// Расширенное инфо
	$result = $messify->token(array(
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
	private $_path_root = '';
	private $_cache_dir = 'messify';
	private $_cache_dir_create = true;
	private $_cache_dir_mode = 0777;
	private $_remote_hash = '1';
	private $_all_offset = -1;
	private $_dirty = array(
		'js' => true,
		'css' => true
	);
	private $_compressors = array(
		'js' => 'default',
		'css' => 'default'
	);
	private $_merge = array(
		'js' => true,
		'css' => true
	);
	private $_compress = array(
		'js' => true,
		'css' => true
	);
	private $_files = array(
		'js' => array(),
		'css' => array()
	);
	private $_error_messages = array(
		30 => 'Option "%s" does not exists',
		31 => 'Options should be Array',
		32 => 'Place is not set',
		33 => 'Can not read file "%s"',
		34 => 'Token can not be empty',
		35 => 'Token secret can not be empty',
		36 => 'Token is not set',
		37 => 'Token secret is not set',
		38 => 'Compressors can not be empty',
		39 => 'Condition is not set',
		40 => "No result available. Returned:\n\n%s",
		41 => 'Cache dir not set',
		45 => 'File "%s" is not exists',
		46 => 'Offset should be integer >= 0',
		47 => 'Offset can not be "%s"',
		48 => 'No file exists at offset "%s"',
		49 => 'Index should be >= 0',
		51 => 'Path root should exist',
		52 => 'Path root not set',
		53 => 'Cache dir is not writable',
		54 => 'Cache dir does not exist'
	);

	public function __construct($options = array()) {
		$this->set_path_root('_DOCUMENT_ROOT_');
		$this->options($options);
	}

	private function _is_file_remote($file) {
		return preg_match('/^(http\:|https\:|)\/\//i', $file);
	}

	private function _check_file($file) {
		if (!$file || (!$this->_is_file_remote($file) && !file_exists($this->_path_root.'/'.$file))) $this->_error(45, $file);
	}

	private function _read_file($file, $remote_hash = false) {
		try {
			if ($this->_is_file_remote($file)) {
				$res = null;
				$file = preg_replace('/^\/\//i', 'http://', $file);
				if ($remote_hash) {
					$md5 = md5($file.$remote_hash);
					if ($this->_cache_exists('remote', $md5)) {
						$res = $this->_cache_load('remote', $md5);
					}
					else {
						$res = file_get_contents($file);
						$this->_cache_save('remote', $md5, $res);
					}
				}
				else {
					$res = file_get_contents($file);
				}
			}
			else {
				$res = file_get_contents($this->_path_root.'/'.ltrim($file, '/'));
			}
			return trim($res);
		}
		catch (Exception $ex) {
			$this->_error(33, $file);
		}

	}

	private function _check_options($options) {
		if (!is_array($options)) $this->_error(31);
	}

	private function _prepare_array(&$array, $parts) {
		$key = array_shift($parts);
		if ($key) {
			if (!isset($array[$key])) $array[$key] = array();
			if ($parts) {
				$this->_prepare_array($array[$key], $parts);
			}
		}
	}

	private function _set_file($offset, $type, $param_1_key, $param_2_key, $file, $options) {
		$this->_check_options($options);
		$this->_correct_options($type, $options, true);
		if ($offset !== null) {
			if ($offset < 0 || !is_int($offset)) {
				$this->_error(46, $offset);
			}
			if ($offset == $this->_all_offset) {
				$this->_error(47, $this->_all_offset, $offset);
			}
		}
		if (isset($options['inline']) && $options['inline']) {
			$content = trim($file);
			$file = '';
		}
		else {
			$this->_check_file($file);
			if (isset($options['remote']) && $options['remote']) {
				$content = '';
			}
			else {
				$content = $this->_read_file($file, isset($options['remote_hash']) ? $options['remote_hash'] : $this->_remote_hash);
			}
		}
		$param_1 = $options[$param_1_key];
		unset($options[$param_1_key]);
		$param_2 = $options[$param_2_key];
		unset($options[$param_2_key]);
		if (!isset($options['render_inline'])) $options['render_inline'] = isset($options['inline']) ? $options['inline'] : false;
		if ($options['render_inline']) $options['merge'] = false;
		$this->_prepare_array($this->_files[$type], array($param_1, $param_2));
		$data = array_merge(array(
			'file' => $file,
			'content' => $content,
			'compress' => null,
			'merge' => null
		), $options);
		if ($offset === null) {
			$this->_files[$type][$param_1][$param_2][] = $data;
		}
		else {
			array_splice($this->_files[$type][$param_1][$param_2], $offset, 1, $data);
		}
		$this->set_dirty(array(
			$type => true
		));
	}

	private function _remove_file($offset, $type, $param_1_key, $param_2_key, $options) {
		$this->_check_options($options);
		$this->_correct_options($type, $options, true);
		if ($offset == $this->_all_offset) {
			$this->_error(47, $this->_all_offset, $offset);
		}
		$param_1 = $options[$param_1_key];
		unset($options[$param_1_key]);
		$param_2 = $options[$param_2_key];
		unset($options[$param_2_key]);
		if (!isset($this->_files[$type][$param_1][$param_2][$offset])) {
			$this->_error(48, $offset);
		}
		$this->set_dirty(array(
			$type => true
		));
	}

	private function _clear_file($type) {
		$this->_files[$type] = array();
		$this->set_dirty(array(
			$type => true
		));
		return $this;
	}

	private function _correct_options($type, &$options, $should_exist = false) {
		if (isset($options['condition']) || $should_exist) {
			if (!isset($options['condition']) || !$options['condition']) $options['condition'] = 'none';
			else $options['condition'] = trim(strtolower($options['condition']));
		}
		if ($type == 'js') {
			if (isset($options['place']) || $should_exist) {
				if (!isset($options['place']) || !$options['place']) $options['place'] = 'body';
				else $options['place'] = trim(strtolower($options['place']));
			}
		}
		else if ($type == 'css') {
			if (isset($options['media']) || $should_exist) {
				if (!isset($options['media']) || !$options['media']) $options['media'] = 'all';
				else $options['media'] = trim(strtolower($options['media']));
			}
		}
	}

	private function _render_file($type, $file, $param) {
		if ($file['inline']) {
			if ($type == 'js') {
				$ret = '<script type="text/javascript">'.$file['content'].'</script>';
			}
			else {
				$ret = '<style type="text/css">'.$file['content'].'</style>';
			}
		}
		else {
			if ($type == 'js') {
				$ret = '<script type="text/javascript" src="/'.$this->_cache_dir.'/js/'.$file['file'].'.js"></script>';
			}
			else {
				$ret = '<link href="/'.$this->_cache_dir.'/css/'.$file['file'].'.css" media="'.$param.'" rel="stylesheet" type="text/css" />';
			}
		}
		return $ret;
	}

	public function result($type, $options = array()) {
		$this->_check_options($options);
		$this->_correct_options($options, $type);
		if ($type == 'js') {
			if (isset($options['condition']) && !isset($options['place'])) {
				$this->_error(39);
			}
		}
		else if ($type == 'css') {
			if (isset($options['media']) && !isset($options['condition'])) {
				$this->_error(32);
			}
		}
		$this->run($type);
		$ret = array();
		$key_1 = $type == 'js' ? 'place' : 'condition';
		$key_2 = $type == 'js' ? 'condition' : 'media';
		foreach ($this->_files[$type] as $param_1_key => $param_1) {
			$isset_1 = isset($options[$key_1]);
			if (!$param_1 || !$isset_1 || $param_1_key == $options[$key_1]) {
				foreach ($param_1 as $param_2_key => $files) {
					$isset_2 = isset($options[$key_2]);
					if (!$files || !$isset_2 || $param_2_key == $options[$key_2]) {
						$this->_prepare_array($ret, array($param_1_key, $param_2_key));
						foreach ($files as $offset => $file) {
							if ($offset == $this->_all_offset || !$file['result']['merge']) {
								$ret[$param_1_key][$param_2_key][$offset] = $offset == $this->_all_offset ? $file : $file['result'];
							}
						}
						ksort($ret[$param_1_key][$param_2_key], SORT_NUMERIC);
						if ($isset_2) {
							return $ret[$param_1_key][$param_2_key];
						}
					}
				}
				if ($isset_1) {
					return $ret[$param_1_key];
				}
			}
		}
		return $ret;
	}

	private function _preprocess_css($content, $file) {
		if ($file) {
			$dir = ltrim(dirname($file), './');
			$found = preg_match_all('/url\((\'|\"|)([^\)]*?)(\'|\"|)\)/si', $content, $match);
			if ($found) {
				$from = $to = array();
				foreach ($match[2] as $k => $v) {
					if (substr($v, 0, 1) == '/') continue;
					$from[] = $match[0][$k];
					$to[] = 'url('.$match[1][$k].($dir ? '/'.$dir : '').'/'.$v.$match[3][$k].')';
				}
				if ($from) $content = str_replace($from, $to, $content);
			}
			$found = preg_match_all('/src\=(\'|\"|)([^\)]*?)(\'|\"|\,|\))/si', $content, $match);
			if ($found) {
				$from = $to = array();
				foreach ($match[2] as $k => $v) {
					if (substr($v, 0, 1) == '/') continue;
					$from[] = $match[0][$k];
					$to[] = 'src='.$match[1][$k].($dir ? '/'.$dir : '').'/'.$v.$match[3][$k];
				}
				if ($from) $content = str_replace($from, $to, $content);
			}
		}
		return $content;
	}

	private function _cache_exists($type, $md5) {
		return file_exists($this->_path_root.'/'.$this->_cache_dir.'/'.$type.'/'.$md5.'.'.$type);
	}

	private function _cache_load($type, $md5) {
		return $this->_read_file($this->_cache_dir.'/'.$type.'/'.$md5.'.'.$type);
	}

	private function _cache_save($type, $md5, $content) {
		if (!$this->_cache_dir) {
			$this->_error(41);
		}
		if (!file_exists($this->_path_root.'/'.$this->_cache_dir)) {
			if ($this->_cache_dir_create) {
				mkdir($this->_path_root.'/'.$this->_cache_dir, $this->_cache_dir_mode, true);
				chmod($this->_path_root.'/'.$this->_cache_dir, $this->_cache_dir_mode);
				if (!file_exists($this->_path_root.'/'.$this->_cache_dir)) {
					$this->_error(54);
				}
			}
			else $this->_error(54);
		}
		if (!is_writable($this->_path_root.'/'.$this->_cache_dir)) {
			chmod($this->_path_root.'/'.$this->_cache_dir, $this->_cache_dir_mode);
			if (!is_writable($this->_path_root.'/'.$this->_cache_dir)) {
				$this->_error(53);
			}
		}
		if (!file_exists($this->_path_root.'/'.$this->_cache_dir.'/'.$type)) {
			mkdir($this->_path_root.'/'.$this->_cache_dir.'/'.$type, $this->_cache_dir_mode, true);
			chmod($this->_path_root.'/'.$this->_cache_dir.'/'.$type, $this->_cache_dir_mode);
		}
		if (!file_exists($this->_path_root.'/'.$this->_cache_dir.'/'.$type)) {
			$this->_error(54);
		}
		if (!is_writable($this->_path_root.'/'.$this->_cache_dir.'/'.$type)) {
			chmod($this->_path_root.'/'.$this->_cache_dir.'/'.$type, $this->_cache_dir_mode);
			if (!is_writable($this->_path_root.'/'.$this->_cache_dir.'/'.$type)) {
				$this->_error(53);
			}
		}
		$ret = file_put_contents($this->_path_root.'/'.$this->_cache_dir.'/'.$type.'/'.$md5.'.'.$type, $content);
		if ($ret) {
			chmod($this->_path_root.'/'.$this->_cache_dir.'/'.$type.'/'.$md5.'.'.$type, $this->_cache_dir_mode);
		}
		return $ret;
	}

	private function _cache_purge($type) {
		try {
			$dir = $this->_path_root.'/'.$this->_cache_dir.'/'.$type;
			$handle = opendir($dir);
			while ($path = readdir($handle)) {
				if (is_file($dir.'/'.$path) && $path != '.' && $path != '..') {
					try {
						unlink($dir.'/'.$path);
					}
					catch (Exception $ex) { }
				}
			}
			closedir($handle);
		}
		catch (Exception $ex) { }
	}

	private function _error($code, $message = '') {
		if (isset($this->_error_messages[$code])) {
			$args = func_get_args();
			$args[0] = $this->_error_messages[$code];
			$message = call_user_func_array('sprintf', $args);
		}
		if (!$message) $message = 'Unknown error';
		throw new Exception($message, $code);
	}

	private function _request($endpoint, $post = array()) {
		try {
			$post = array_merge(array(
				'token' => $this->_token,
				'token_secret' => $this->_token_secret,
				'host' => @$_SERVER['HTTP_HOST']
			), $post);
			$result = file_get_contents('https://'.$this->_service_host.'/api/'.$endpoint, false, stream_context_create(array(
				'http' => array(
					'method' =>	'POST',
					'header' => 'Content-Type: application/x-www-form-urlencoded',
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
			$this->_error(40, $result);
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

	public function options($options) {
		if (!$options) {
			return;
		}
		$this->_check_options($options);
		foreach ($options as $key => $value) {
			$method = 'set_'.$key;
			if (method_exists($this, $method)) {
				$this->$method($value);
				$this->set_dirty(true);
			}
			else {
				$this->_error(30, $key);
			}
		}
		return $this;
	}

	public function set_all_offset($index) {
		if (is_int($index) && $index > -1) {
			$this->_all_offset = $index;
		}
		else {
			$this->_error(49, $index);
		}
		return $this;
	}

	public function set_compressors($type, $compressors) {
		if ($compressors && ($compressors == 'default' || is_array($compressors))) {
			$this->_compressors[$type] = $compressors;
		}
		else {
			$this->_error(38);
		}
		return $this;
	}

	public function set_remote_hash($hash) {
		$this->_remote_hash = $hash;
	}

	public function set_dirty($dirty) {
		if (is_array($dirty)) {
			foreach ($dirty as $k => $v) {
				$this->_dirty[$k] = $v;
			}
		}
		else {
			foreach ($this->_dirty as $k => $v) {
				$this->_dirty[$k] = $dirty;
			}
		}
	}

	public function set_merge($merge) {
		if (is_array($merge)) {
			foreach ($merge as $k => $v) {
				$this->_merge[$k] = $v;
			}
		}
		else {
			foreach ($this->_merge as $k => $v) {
				$this->_merge[$k] = $merge;
			}
		}
	}

	public function set_compress($compress) {
		if (is_array($compress)) {
			foreach ($compress as $k => $v) {
				$this->_compress[$k] = $v;
			}
		}
		else {
			foreach ($this->_compress as $k => $v) {
				$this->_compress[$k] = $compress;
			}
		}
	}

	public function set_cache_dir_create($cache_dir_create) {
		$this->_cache_dir_create = $cache_dir_create;
	}

	public function set_path_root($path_root) {
		if ($path_root) {
			$path_root = str_replace(array(
				'_DOCUMENT_ROOT_'
			), array(
				isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : ''
			), $path_root);
			if (!file_exists($path_root)) {
				$this->_error(51);
			}
			$this->_path_root = $path_root;
		}
		else {
			$this->_error(52);
		}
		return $this;
	}

	public function set_cache_dir($cache_dir) {
		if ($cache_dir) {
			$this->_cache_dir = $cache_dir;
		}
		else {
			$this->_error(41);
		}
		return $this;
	}

	public function set_token($token) {
		if ($token) {
			$this->_token = $token;
		}
		else {
			$this->_error(34);
		}
		return $this;
	}

	public function set_token_secret($token_secret) {
		if ($token_secret) {
			$this->_token_secret = $token_secret;
		}
		else {
			$this->_error(35);
		}
		return $this;
	}

	public function run($type = '') {
		$types = array('css', 'js');
		foreach ($types as $type_cur) {
			if (($type && $type != $type_cur) || !$this->_files[$type_cur] || !$this->_dirty[$type_cur]) continue;
			foreach ($this->_files[$type_cur] as $param_1_key => $param_1) {
				if (!$param_1) continue;
				foreach ($param_1 as $param_2_key => $files) {
					if (!$files) continue;
					$hash = '';
					$content_all = array();
					$was_merged = 0;
					foreach ($files as $offset => $file) {
						if ($offset == $this->_all_offset) continue;
						$compress = $file['compress'] === null ? $this->_compress[$type_cur] : $file['compress'];
						$merge = $file['merge'] === null ? $this->_merge[$type_cur] : $file['merge'];
						$content = $file['content'];
						$md5 = md5($content.$compress);
						if (!$this->_cache_exists($type_cur, $md5)) {
							if ($type_cur == 'css') $content = $this->_preprocess_css($content, $file['file']);
							if ($compress) {
								$result = $this->compress($type_cur, $content);
								if ($result) {
									$content = $result['content'];
								}
							}
							$this->_cache_save($type_cur, $md5, $content);
						}
						if ($merge) {
							$hash .= $md5;
							$content_all []= $content;
							$was_merged++;
						}
						$this->_files[$type_cur][$param_1_key][$param_2_key][$offset]['result'] = array(
							'file' => $md5,
							'content' => $content,
							'inline' => $file['render_inline'],
							'merge' => $merge
						);
					}
					if ($was_merged > 1) {
						$hash_md5 = md5($hash);
						$glue = ($type == 'js' ? ';' : '')."\n";
						$content = implode($glue, $content_all);
						if (!$this->_cache_exists($type_cur, $hash_md5)) {
							$this->_cache_save($type_cur, $hash_md5, implode($glue, $content_all));
						}
						$this->_files[$type_cur][$param_1_key][$param_2_key][$this->_all_offset] = array(
							'file' => $hash_md5,
							'content' => $content,
							'inline' => false,
							'merge' => false
						);
					}
					else {
						foreach ($this->_files[$type_cur][$param_1_key][$param_2_key] as $offset => $file) {
							$this->_files[$type_cur][$param_1_key][$param_2_key][$offset]['result']['merge'] = false;
						}
						unset($this->_files[$type_cur][$param_1_key][$param_2_key][$this->_all_offset]);
					}
				}
			}
			$this->_dirty[$type_cur] = false;
		}
		return $this;
	}


	public function add($type, $file, $options = array()) {
		$this->_set_file(null, $type, $type == 'js'
			? 'place'
			: 'condition',
		$type == 'js'
			? 'condition'
			: 'media', $file, $options);
		return $this;
	}

	public function set($type, $offset, $file, $options = array()) {
		$this->_set_file($offset, $type, $type == 'js'
			? 'place'
			: 'condition',
		$type == 'js'
			? 'condition'
			: 'media', $file, $options);
		return $this;
	}

	public function remove($type, $offset, $options = array()) {
		$this->_remove_file($offset, $type, $type == 'js'
			? 'place'
			: 'condition',
		$type == 'js'
			? 'condition'
			: 'media', $options);
		return $this;
	}

	public function clear($type) {
		$this->_clear_file($type);
		return $this;
	}

	public function purge_cache($type) {
		$this->_cache_purge($type);
		return $this;
	}

	public function token($param = array()) {
		$ret = $this->_request('token', $param);
		if ($ret) {
			if (isset($ret['token']) && !$this->_token) $this->set_token($ret['token']);
			if (isset($ret['token_secret']) && !$this->_token_secret) $this->set_token_secret($ret['token_secret']);
		}
		return $ret;
	}

	public function compress($type, $data, $compressors = array()) {
		if (!$this->_token) {
			$this->token();
			if (!$this->_token) {
				$this->_error(36);
			}
		}
		if (!$this->_token_secret) {
			$this->_error(37);
		}

		if (!$compressors && isset($this->_compressors[$type])) $compressors = $this->_compressors[$type];

		if (!$compressors || ($compressors != 'default' && !is_array($compressors))) {
			$this->_error(38);
		}
		return $this->_request('compress', array(
			'type' => $type,
			'compressors' => $compressors,
			'data' => $data
		));
	}

	public function render($type, $options = array()) {
		$result = $this->result($type, $options);
		$ret = array();
		$key_1 = $type == 'js' ? 'place' : 'condition';
		$key_2 = $type == 'js' ? 'condition' : 'media';
		if ($result) {
			if (isset($options[$key_1])) {
				foreach ($result as $param_2_key => $files) {
					if (!$files) continue;
				}
			}
			else {
				foreach ($result as $param_1_key => $param_1) {
					if (!$param_1) continue;
					$ret_inner_1 = array();
					foreach ($param_1 as $param_2_key => $files) {
						if (!$files) continue;
						$ret_inner_2 = array();
						foreach ($files as $offset => $file) {
							$ret_inner_2[] = $this->_render_file($type, $file, $param_2_key);
						}
						if ($key_2 == 'condition' && $param_2_key != 'none') {
							$ret_inner_2 = array('<!--[if '.$param_2_key.']>'.implode('', $ret_inner_2).'<![endif]-->');
						}
						if ($ret_inner_2) $ret_inner_1 = array_merge($ret_inner_1, $ret_inner_2);
					}
					if ($key_1 == 'condition' && $param_1_key != 'none') {
						$ret_inner_1 = array('<!--[if '.$param_1_key.']>'.implode('', $ret_inner_1).'<![endif]-->');
					}
					if ($ret_inner_1) $ret = array_merge($ret, $ret_inner_1);
				}
			}
		}
		return implode('', $ret);
	}
}
