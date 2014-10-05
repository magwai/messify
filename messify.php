<?php
/**
 * messify
 *
 * Copyright (c) 2014 Magwai Ltd. <info@magwai.ru>, http://magwai.ru
 * Licensed under the MIT License:
 * http://www.opensource.org/licenses/mit-license.php

USAGE:

// Include class and create instance
include 'messify.php';
$messify = new messify();
try {
	// Add CSS and JavaScript files
	$messify
		->append('js', 'http://code.jquery.com/jquery-latest.js')
		->append('js', 'alert("Hello World");', array(
			'inline' => true,
			'render_inline' => false
		))
		->append('css', 'http://cdnjs.cloudflare.com/ajax/libs/meyer-reset/2.0/reset.css')
		->append('css', 'body{background:#cccccc;}', array(
			'inline' => true,
			'render_inline' => false
		));

	// Output result for CSS
	echo $messify->render('css');

	// Output result for JavaScript
	echo $messify->render('js');
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
	private $_remote_hash = 'default';
	private $_all_offset = -1000;
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
	private $_scss = array(
		'images_dir' => '../images'
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
		46 => 'Offset should be integer > %s',
		47 => 'Offset can not be "%s"',
		48 => 'No file exists at offset "%s"',
		49 => 'Index should be >= 0',
		51 => 'Path root should exist',
		52 => 'Path root not set',
		53 => 'Cache dir is not writable',
		54 => 'Cache dir does not exist',
		55 => 'No file found'
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

	private function _prepare_array(&$array, $parts, $val = null) {
		$key = array_shift($parts);
		if ($key) {
			if (!isset($array[$key])) $array[$key] = array();
			if ($parts) {
				$this->_prepare_array($array[$key], $parts, $val);
			}
			else if ($val !== null) {
				$array[$key] = $val;
			}
		}
	}

	private function _set_file($offset, $type, $param_1_key, $param_2_key, $file, $options) {
		$this->_check_options($options);
		$this->_correct_options($type, $options, true);
		if ($offset !== null) {
			if ($offset < $this->_all_offset || !is_int($offset)) {
				$this->_error(46, $this->_all_offset, $offset);
			}
			if ($offset == $this->_all_offset) {
				$this->_error(47, $this->_all_offset, $offset);
			}
		}
		if (isset($options['inline']) && $options['inline']) {
			$content = trim($file);
			$file = '';
			if ($type == 'css') {
				if (isset($options['scss'])) $options['scss'] = $options['scss'];
				else $options['scss'] = false;
			}
		}
		else {
			$this->_check_file($file);
			if (isset($options['remote']) && $options['remote']) {
				$content = '';
			}
			else {
				$content = $this->_read_file($file, isset($options['remote_hash']) ? $options['remote_hash'] : $this->_remote_hash);
			}
			if ($type == 'css') {
				if (isset($options['scss'])) $options['scss'] = $options['scss'];
				else {
					if (preg_match('/\.scss$/i', $file)) $options['scss'] = true;
					else $options['scss'] = false;
				}
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
		else if ($offset === -1) {
			$keys = array_keys($this->_files[$type][$param_1][$param_2]);
			$min = min($keys);
			$this->_files[$type][$param_1][$param_2][$min - 1] = $data;
		}
		else {
			if (isset($this->_files[$type][$param_1][$param_2][$offset])) {
				array_splice($this->_files[$type][$param_1][$param_2], $offset, 1, $data);
			}
			else {
				$this->_files[$type][$param_1][$param_2][$offset] = $data;
			}
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
				$ret = '<script type="text/javascript" src="'.($file['remote'] ? $file['file'] : '/'.$this->_cache_dir.'/js/'.$file['file'].'.js').'"></script>';
			}
			else {
				$ret = '<link href="'.($file['remote'] ? $file['file'] : '/'.$this->_cache_dir.'/css/'.$file['file'].'.css').'" media="'.$param.'" rel="stylesheet" type="text/css" />';
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
		if ($file && isset($file['file']) && $file['file']) {
			if ($file['scss']) $content = $this->preprocess_scss($content, $file['file'], $file['scss']);
			$dir = ltrim(dirname($file['file']), './');
			$found = preg_match_all('/url\((\'|\"|)([^\)]*?)(\'|\"|)\)/si', $content, $match);
			if ($found) {
				$from = $to = array();
				foreach ($match[2] as $k => $v) {
					if (substr($v, 0, 1) == '/' || $this->_is_file_remote($v)) continue;
					$from[] = $match[0][$k];
					$to[] = 'url('.$match[1][$k].($dir ? '/'.$dir : '').'/'.$v.$match[3][$k].')';
				}
				if ($from) $content = str_replace($from, $to, $content);
			}
			$found = preg_match_all('/src\=(\'|\"|)([^\)]*?)(\'|\"|\,|\))/si', $content, $match);
			if ($found) {
				$from = $to = array();
				foreach ($match[2] as $k => $v) {
					if (substr($v, 0, 1) == '/' || $this->_is_file_remote($v)) continue;
					$from[] = $match[0][$k];
					$to[] = 'src='.$match[1][$k].($dir ? '/'.$dir : '').'/'.$v.$match[3][$k];
				}
				if ($from) $content = str_replace($from, $to, $content);
			}
		}
		return $content;
	}

	public function preprocess_scss($content, $file, $options = array()) {
		$opt = $this->_scss;
		if ($options && !is_array($options)) $options = array();
		if ($options) {
			$this->_check_options($options);
			$opt = array_merge($opt, $options);
		}

		// process scss file content
		$res = $this->_preprocess_scss_content($content, $file);

		// find all images from images_dir
		$images = array();
		$images_dir = rtrim($opt['images_dir'], './');
		$images_dir = trim(dirname($file).'/'.$images_dir, '/');
		$this->_preprocess_scss_image($images_dir, '', $images);

		// zip scss file and all images
		$zip = new messify_Zip();
		$zip->addFile($res, 'sass/style.scss');
		if ($images) foreach ($images as $el) {
			$zip->addFile($this->_read_file(ltrim($images_dir.'/', '/').$el), 'images/'.$el);
		}

		// preparing options for remote call
		$opt['file'] = basename($file);
		$opt['images_dir'] = '__cache_dir__';

		// cleaning gen images cache
		$this->_cache_purge('images/'.$opt['file']);

		// call api
		$result = $this->scss($zip->getZipData(), $opt);
		$content = $result['content'];

		// unzip images and replace their names in content
		if ($result['images']) {
			$dir = $this->_path_root.'/'.$this->_cache_dir.'/images/'.$opt['file'];
			$this->_cache_save('images/'.$opt['file'], 'temp', $result['images'], 'zip');
			try {
				$zip = new messify_Unzip();
				$zip->extract($dir.'/temp.zip', $dir);
				unlink($dir.'/temp.zip');
			}
			catch (Exception $ex) {}
			$content = str_replace('__cache_dir__', '/'.$this->_cache_dir.'/images/'.$opt['file'], $content);
		}
		return $content;
	}

	private function _preprocess_scss_image($root, $dir, &$images) {
		try {
			$dir_valid = '/'.trim($dir, '/.');
			if ($dir_valid != '/') $dir_valid .= '/';
			$handle = opendir($this->_path_root.'/'.$root.$dir_valid);
			while ($path = readdir($handle)) {
				if ($path == '.' || $path == '..') continue;
				if (is_file($this->_path_root.'/'.$root.$dir_valid.$path)) {
					if (preg_match('/\.(png|gif|jpg|jpeg|svg)$/i', $path)) {
						$images[] = ltrim($dir_valid, '/').$path;
					}
				}
				else if (is_dir($this->_path_root.'/'.$root.$dir_valid.$path)) {
					$this->_preprocess_scss_image($root, ltrim($dir_valid, '/').$path, $images);
				}
			}
			closedir($handle);
		}
		catch (Exception $ex) { }
	}

	private function _preprocess_scss_content($content, $file, $ex = array()) {
		$result = null;
		preg_match_all('/\@import.*?\"(.*?)\"\;/si', $content, $result);
		if ($result) {
			$dir = ltrim(dirname($file), './');
			$replace = array();
			foreach ($result[1] as $k => $v) {
				$dir_valid = ($dir ? '/'.$dir : '').'/';
				$fn_valid = $v;
				if (!preg_match('/\.scss$/i', $fn_valid)) $fn_valid .= '.scss';
				if (file_exists($this->_path_root.$dir_valid.'_'.$fn_valid)) $fn_valid = '_'.$fn_valid;
				else if (!file_exists($this->_path_root.$dir_valid.$fn_valid)) {
					$replace[] = $result[0][$k];
					continue;
				}
				if (in_array($dir_valid.$fn_valid, $ex)) {
					$replace[] = $result[0][$k];
					continue;
				}
				$ex[] = $dir_valid.$fn_valid;
				$inner = trim($this->_read_file($dir_valid.$fn_valid));
				if ($inner) $inner = "\n".$inner."\n";
				$replace[] = $this->_preprocess_scss_content($inner, $dir_valid.$fn_valid, $ex);
			}
			$content = str_replace($result[0], $replace, $content);
		}
		return $content;
	}

	private function _cache_exists($type, $md5) {
		return file_exists($this->_path_root.'/'.$this->_cache_dir.'/'.$type.'/'.$md5.'.'.$type);
	}

	private function _cache_load($type, $md5) {
		return $this->_read_file($this->_cache_dir.'/'.$type.'/'.$md5.'.'.$type);
	}

	private function _cache_prepare_dir($type) {
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
	}

	private function _cache_save($type, $md5, $content, $ext = '') {
		if (!$ext) $ext = $type;
		if (!$this->_cache_dir) {
			$this->_error(41);
		}
		$this->_cache_prepare_dir($type);
		$ret = file_put_contents($this->_path_root.'/'.$this->_cache_dir.'/'.$type.'/'.$md5.'.'.$ext, $content);
		if ($ret) {
			chmod($this->_path_root.'/'.$this->_cache_dir.'/'.$type.'/'.$md5.'.'.$ext, $this->_cache_dir_mode);
		}
		return $ret;
	}

	private function _cache_purge($type) {
		try {
			$dir = $this->_path_root.'/'.$this->_cache_dir.'/'.$type;
			$handle = opendir($dir);
			while ($path = readdir($handle)) {
				if ($path == '.' || $path == '..') continue;
				if (is_file($dir.'/'.$path)) {
					try {
						unlink($dir.'/'.$path);
					}
					catch (Exception $ex) { }
				}
				else if (is_dir($dir.'/'.$path)) {
					$this->_cache_purge($type.'/'.$path);
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
			if ($result) {
				foreach ($result as $k => $v) {
					$meta = substr($k, -7);
					if ($meta == ':base64') {
						unset($result[$k]);
						$result[substr($k, 0, -7)] = base64_decode($v);
					}
					else if ($meta == ':urlenc') {
						unset($result[$k]);
						$result[substr($k, 0, -7)] = urldecode($v);
					}
				}
			}
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

	public function set_scss($options) {
		$this->_check_options($options);
		$this->_scss = $options;
		return $this;
	}

	public function set_compressors($compressors) {
		if (is_array($compressors)) {
			foreach ($compressors as $k => $v) {
				$this->_compressors[$k] = $v;
			}
		}
		else {
			foreach ($this->_compressors as $k => $v) {
				$this->_compressors[$k] = $compressors;
			}
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
					ksort($files, SORT_NUMERIC);
					foreach ($files as $offset => $file) {
						if ($offset == $this->_all_offset) continue;
						if ($file['remote'] && $this->_is_file_remote($file['file'])) {
							$content = '';
							$merge = false;
							$md5 = $file['file'];
							$file['render_inline'] = false;
						}
						else {
							$compress = $file['compress'] === null ? $this->_compress[$type_cur] : $file['compress'];
							$merge = $file['merge'] === null ? $this->_merge[$type_cur] : $file['merge'];
							$content = $file['content'];
							$md5 = md5($content.$compress.(isset($file['scss']) ? var_export($file['scss'], 1) : ''));
							if ($this->_cache_exists($type_cur, $md5)) {
								$content = $this->_cache_load($type_cur, $md5);
							}
							else {
								if ($type_cur == 'css') $content = $this->_preprocess_css($content, $file);
								if ($compress) {
									$result = $this->compress($type_cur, $content);
									if ($result) {
										$content = $result['content'];
									}
								}
								$this->_cache_save($type_cur, $md5, $content);
							}
							if ($merge) {
								$hash .= $md5.$offset;
								$content_all[] = $content;
								$was_merged++;
							}
						}
						$this->_files[$type_cur][$param_1_key][$param_2_key][$offset]['result'] = array(
							'file' => $md5,
							'content' => $content,
							'remote' => $file['remote'],
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
		return $this->append($type, $file, $options);
	}

	public function append_inline($type, $file, $options = array()) {
		if (!$options || !is_array($options)) $options = array();
		$options['inline'] = true;
		return $this->append($type, $file, $options);
	}

	public function append($type, $file, $options = array()) {
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

	public function prepend_inline($type, $file, $options = array()) {
		if (!$options || !is_array($options)) $options = array();
		$options['inline'] = true;
		return $this->prepend($type, $file, $options);
	}

	public function prepend($type, $file, $options = array()) {
		$this->_set_file(-1, $type, $type == 'js'
			? 'place'
			: 'condition',
		$type == 'js'
			? 'condition'
			: 'media', $file, $options);
		return $this;
	}

	public function get($type, $offset, $options = array()) {
		$this->_check_options($options);
		$this->_correct_options($type, $options, true);
		$param_1_key = $type == 'js'
			? 'place'
			: 'condition';
		$param_2_key = $type == 'js'
			? 'condition'
			: 'media';
		$param_1 = $options[$param_1_key];
		$param_2 = $options[$param_2_key];
		if (isset($this->_files[$type][$param_1][$param_2])) {
			return $this->_files[$type][$param_1][$param_2];
		}
		else {
			$this->_error(55, $offset, $param_1, $param_2);
		}
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

	public function scss($data, $options = array()) {
		$this->_check_options($options);
		if (!$this->_token) {
			$this->token();
			if (!$this->_token) {
				$this->_error(36);
			}
		}
		if (!$this->_token_secret) {
			$this->_error(37);
		}
		$options['data'] = $data;
		return $this->_request('scss', $options);
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

	private function _parse_for_condition($html, $place, &$replace, &$files) {
		$found = preg_match_all('/\<\!\-\-.*?\[.*?if([^\]]*)\].*?\>(.*?)\<\!.*?\[.*?endif.*?\].*?\-\-\>/si', $html, $result);
		if ($found) {
			foreach ($result[2] as $k => $v) {
				$this->_parse_for_js($v, $place, trim($result[1][$k]), $replace, $files);
				$this->_parse_for_css($v, trim($result[1][$k]), $replace, $files);
			}
		}
	}

	private function _parse_for_data_options($html) {
		$ret = array();
		$data_src = preg_match_all('/\ data\-messify\-(.*?)\=(\"|\'|)(.*?)(\"|\'|\ |\>)/si', $html, $result);
		if ($data_src) {
			foreach ($result[1] as $k => $v) {
				$key = trim($v);
				$val = trim($result[3][$k]);
				$this->_prepare_array($ret, explode('-', $key), $val);
			}
		}
		return $ret;
	}

	private function _parse_for_js($html, $place, $condition, &$replace, &$files) {
		$found_src = preg_match_all('/\<script.*?src.*?\=.*?(\"|\'|)(.*?)(\"|\'|\ |\>).*?\>(.*?)\<.*?\/.*?script.*?\>/si', $html, $result);
		if ($found_src) {
			foreach ($result[2] as $k => $v) {
				$files[$condition === null ? 'all' : 'condition'][] = array('js', $v, array_merge(array(
					'condition' => $condition,
					'place' => $place
				), $this->_parse_for_data_options($result[0][$k])));
				$html = str_replace($result[0][$k], '', $html);
				$replace[] = $result[0][$k];
			}
		}
		$found_inline = preg_match_all('/\<script.*?\>(.*?)\<.*?\/.*?script.*?\>/si', $html, $result);
		if ($found_inline) {
			foreach ($result[1] as $k => $v) {
				$files[$condition === null ? 'all' : 'condition'][] = array('js', $v, array_merge(array(
					'condition' => $condition,
					'place' => $place,
					'inline' => true
				), $this->_parse_for_data_options($result[0][$k])));
				$html = str_replace($result[0][$k], '', $html);
				$replace[] = $result[0][$k];
			}
		}
	}

	private function _parse_for_css($html, $condition, &$replace, &$files) {
		$found_href = preg_match_all('/\<.*?link.*?rel.*?\=.*?(\"|\'|)stylesheet(\"|\'|\ |\>|\/).*?\>/si', $html, $result);
		if ($found_href) {
			foreach ($result[0] as $k => $v) {
				$media = 'all';
				$href = '';
				$found_media = preg_match('/media.*?\=.*?(\"|\'|)(.*?)(\"|\'|\ |\>|\/)/si', $v, $result_media);
				if ($found_media) $media = trim($result_media[2]);
				$found_href = preg_match('/href.*?\=.*?(\"|\'|)(.*?)(\"|\'|\ |\>)/si', $v, $result_href);
				if ($found_href) $href = trim($result_href[2]);
				if (!$href) continue;
				$files[$condition === null ? 'all' : 'condition'][] = array('css', $href, array_merge(array(
					'condition' => $condition,
					'media' => $media
				), $this->_parse_for_data_options($result[0][$k])));
				$html = str_replace($result[0][$k], '', $html);
				$replace[] = $result[0][$k];
			}
		}
		$found_inline = preg_match_all('/\<.*?style.*?\>(.*?)\<.*?\/.*?style.*?\>/si', $html, $result);
		if ($found_inline) {
			foreach ($result[1] as $k => $v) {
				$files[$condition === null ? 'all' : 'condition'][] = array('css', $v, array_merge(array(
					'inline' => true,
					'condition' => $condition,
					'media' => 'all'
				), $this->_parse_for_data_options($result[0][$k])));
				$html = str_replace($result[0][$k], '', $html);
				$replace[] = $result[0][$k];
			}
		}
	}

	public function parse($html, $options = array()) {
		$body = $html;
		$head = '';
		$found_head = preg_match_all('/\<head\>(.*?)\<\/head\>/si', $body, $result);
		if ($found_head) {
			foreach ($result[1] as $k => $v) {
				$body = str_replace($result[0][$k], '', $body);
				$head .= $v;
			}
		}

		$files = array(
			'all' => array(),
			'condition' => array()
		);
		$replace = array();
		$this->_parse_for_condition($head, 'head', $replace, $files);
		$this->_parse_for_condition($body, 'body', $replace, $files);
		$head = str_replace($replace, '', $head);
		$body = str_replace($replace, '', $body);
		$this->_parse_for_js($head, 'head', null, $replace, $files);
		$this->_parse_for_js($body, 'body', null, $replace, $files);
		$this->_parse_for_css($head, null, $replace, $files);
		$this->_parse_for_css($body, null, $replace, $files);
		$html = str_replace($replace, '', $html);

		$replace = array();
		$found_empty = preg_match_all('/\<\!\-\-.*?\[.*?if([^\]]*)\].*?\>[\ \n\t\;]*?\<\!.*?\[.*?endif.*?\].*?\-\-\>/si', $html, $result);
		if ($found_empty) {
			foreach ($result[0] as $v) {
				$replace[] = $v;
			}
		}
		$html = str_replace($replace, '', $html);
		if ($files['all']) {
			foreach ($files['all'] as $el) {
				$el[2] = array_merge($el[2], $options);
				call_user_func_array(array($this, 'add'), $el);
			}
		}
		if ($files['condition']) {
			foreach ($files['condition'] as $el) {
				$el[2] = array_merge($el[2], $options);
				call_user_func_array(array($this, 'add'), $el);
			}
		}
	}
}

/**
 * Class to create and manage a Zip file.
 *
 * Initially inspired by CreateZipFile by Rochak Chauhan  www.rochakchauhan.com (http://www.phpclasses.org/browse/package/2322.html)
 * and
 * http://www.pkware.com/documents/casestudies/APPNOTE.TXT Zip file specification.
 *
 * License: GNU LGPL 2.1.
 *
 * @author A. Grandt <php@grandt.com>
 * @copyright 2009-2014 A. Grandt
 * @license GNU LGPL 2.1
 * @link http://www.phpclasses.org/package/6110
 * @link https://github.com/Grandt/PHPZip
 * @version 1.62
 */
class messify_Zip {
    const VERSION = 1.62;

    const ZIP_LOCAL_FILE_HEADER = "\x50\x4b\x03\x04"; // Local file header signature
    const ZIP_CENTRAL_FILE_HEADER = "\x50\x4b\x01\x02"; // Central file header signature
    const ZIP_END_OF_CENTRAL_DIRECTORY = "\x50\x4b\x05\x06\x00\x00\x00\x00"; //end of Central directory record

    const EXT_FILE_ATTR_DIR = 010173200020;  // Permission 755 drwxr-xr-x = (((S_IFDIR | 0755) << 16) | S_DOS_D);
    const EXT_FILE_ATTR_FILE = 020151000040; // Permission 644 -rw-r--r-- = (((S_IFREG | 0644) << 16) | S_DOS_A);

    const ATTR_VERSION_TO_EXTRACT = "\x14\x00"; // Version needed to extract
    const ATTR_MADE_BY_VERSION = "\x1E\x03"; // Made By Version

	// UID 1000, GID 0
	const EXTRA_FIELD_NEW_UNIX_GUID = "\x75\x78\x0B\x00\x01\x04\xE8\x03\x00\x00\x04\x00\x00\x00\x00";

	// Unix file types
	const S_IFIFO  = 0010000; // named pipe (fifo)
	const S_IFCHR  = 0020000; // character special
	const S_IFDIR  = 0040000; // directory
	const S_IFBLK  = 0060000; // block special
	const S_IFREG  = 0100000; // regular
	const S_IFLNK  = 0120000; // symbolic link
	const S_IFSOCK = 0140000; // socket

	// setuid/setgid/sticky bits, the same as for chmod:

	const S_ISUID  = 0004000; // set user id on execution
	const S_ISGID  = 0002000; // set group id on execution
	const S_ISTXT  = 0001000; // sticky bit

	// And of course, the other 12 bits are for the permissions, the same as for chmod:
	// When addding these up, you can also just write the permissions as a simgle octal number
	// ie. 0755. The leading 0 specifies octal notation.
	const S_IRWXU  = 0000700; // RWX mask for owner
	const S_IRUSR  = 0000400; // R for owner
	const S_IWUSR  = 0000200; // W for owner
	const S_IXUSR  = 0000100; // X for owner
	const S_IRWXG  = 0000070; // RWX mask for group
	const S_IRGRP  = 0000040; // R for group
	const S_IWGRP  = 0000020; // W for group
	const S_IXGRP  = 0000010; // X for group
	const S_IRWXO  = 0000007; // RWX mask for other
	const S_IROTH  = 0000004; // R for other
	const S_IWOTH  = 0000002; // W for other
	const S_IXOTH  = 0000001; // X for other
	const S_ISVTX  = 0001000; // save swapped text even after use

	// Filetype, sticky and permissions are added up, and shifted 16 bits left BEFORE adding the DOS flags.

	// DOS file type flags, we really only use the S_DOS_D flag.

	const S_DOS_A  = 0000040; // DOS flag for Archive
	const S_DOS_D  = 0000020; // DOS flag for Directory
	const S_DOS_V  = 0000010; // DOS flag for Volume
	const S_DOS_S  = 0000004; // DOS flag for System
	const S_DOS_H  = 0000002; // DOS flag for Hidden
	const S_DOS_R  = 0000001; // DOS flag for Read Only

    private $zipMemoryThreshold = 1048576; // Autocreate tempfile if the zip data exceeds 1048576 bytes (1 MB)

    private $zipData = NULL;
    private $zipFile = NULL;
    private $zipComment = NULL;
    private $cdRec = array(); // central directory
    private $offset = 0;
    private $isFinalized = FALSE;
    private $addExtraField = TRUE;

    private $streamChunkSize = 65536;
    private $streamFilePath = NULL;
    private $streamTimestamp = NULL;
    private $streamFileComment = NULL;
    private $streamFile = NULL;
    private $streamData = NULL;
    private $streamFileLength = 0;
	private $streamExtFileAttr = null;
	/**
	 * A custom temporary folder, or a callable that returns a custom temporary file.
	 * @var string|callable
	 */
	public static $temp = null;

    /**
     * Constructor.
     *
     * @param boolean $useZipFile Write temp zip data to tempFile? Default FALSE
     */
    function __construct($useZipFile = FALSE) {
        if ($useZipFile) {
            $this->zipFile = tmpfile();
        } else {
            $this->zipData = "";
        }
    }

    function __destruct() {
        if (is_resource($this->zipFile)) {
            fclose($this->zipFile);
        }
        $this->zipData = NULL;
    }

    /**
     * Extra fields on the Zip directory records are Unix time codes needed for compatibility on the default Mac zip archive tool.
     * These are enabled as default, as they do no harm elsewhere and only add 26 bytes per file added.
     *
     * @param bool $setExtraField TRUE (default) will enable adding of extra fields, anything else will disable it.
     */
    function setExtraField($setExtraField = TRUE) {
        $this->addExtraField = ($setExtraField === TRUE);
    }

    /**
     * Set Zip archive comment.
     *
     * @param string $newComment New comment. NULL to clear.
     * @return bool $success
     */
    public function setComment($newComment = NULL) {
        if ($this->isFinalized) {
            return FALSE;
        }
        $this->zipComment = $newComment;

        return TRUE;
    }

    /**
     * Set zip file to write zip data to.
     * This will cause all present and future data written to this class to be written to this file.
     * This can be used at any time, even after the Zip Archive have been finalized. Any previous file will be closed.
     * Warning: If the given file already exists, it will be overwritten.
     *
     * @param string $fileName
     * @return bool $success
     */
    public function setZipFile($fileName) {
        if (is_file($fileName)) {
            unlink($fileName);
        }
        $fd=fopen($fileName, "x+b");
        if (is_resource($this->zipFile)) {
            rewind($this->zipFile);
            while (!feof($this->zipFile)) {
                fwrite($fd, fread($this->zipFile, $this->streamChunkSize));
            }

            fclose($this->zipFile);
        } else {
            fwrite($fd, $this->zipData);
            $this->zipData = NULL;
        }
        $this->zipFile = $fd;

        return TRUE;
    }

    /**
     * Add an empty directory entry to the zip archive.
     * Basically this is only used if an empty directory is added.
     *
     * @param string $directoryPath Directory Path and name to be added to the archive.
     * @param int    $timestamp     (Optional) Timestamp for the added directory, if omitted or set to 0, the current time will be used.
     * @param string $fileComment   (Optional) Comment to be added to the archive for this directory. To use fileComment, timestamp must be given.
	 * @param int    $extFileAttr   (Optional) The external file reference, use generateExtAttr to generate this.
     * @return bool $success
     */
    public function addDirectory($directoryPath, $timestamp = 0, $fileComment = NULL, $extFileAttr = self::EXT_FILE_ATTR_DIR) {
        if ($this->isFinalized) {
            return FALSE;
        }
        $directoryPath = str_replace("\\", "/", $directoryPath);
        $directoryPath = rtrim($directoryPath, "/");

        if (strlen($directoryPath) > 0) {
            $this->buildZipEntry($directoryPath.'/', $fileComment, "\x00\x00", "\x00\x00", $timestamp, "\x00\x00\x00\x00", 0, 0, $extFileAttr);
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Add a file to the archive at the specified location and file name.
     *
     * @param string $data        File data.
     * @param string $filePath    Filepath and name to be used in the archive.
     * @param int    $timestamp   (Optional) Timestamp for the added file, if omitted or set to 0, the current time will be used.
     * @param string $fileComment (Optional) Comment to be added to the archive for this file. To use fileComment, timestamp must be given.
     * @param bool   $compress    (Optional) Compress file, if set to FALSE the file will only be stored. Default TRUE.
	 * @param int    $extFileAttr (Optional) The external file reference, use generateExtAttr to generate this.
     * @return bool $success
     */
    public function addFile($data, $filePath, $timestamp = 0, $fileComment = NULL, $compress = TRUE, $extFileAttr = self::EXT_FILE_ATTR_FILE) {
        if ($this->isFinalized) {
            return FALSE;
        }

        if (is_resource($data) && get_resource_type($data) == "stream") {
            $this->addLargeFile($data, $filePath, $timestamp, $fileComment, $extFileAttr);
            return FALSE;
        }

        $gzData = "";
        $gzType = "\x08\x00"; // Compression type 8 = deflate
        $gpFlags = "\x00\x00"; // General Purpose bit flags for compression type 8 it is: 0=Normal, 1=Maximum, 2=Fast, 3=super fast compression.
        $dataLength = strlen($data);
        $fileCRC32 = pack("V", crc32($data));

        if ($compress) {
            $gzTmp = gzcompress($data);
            $gzData = substr(substr($gzTmp, 0, strlen($gzTmp) - 4), 2); // gzcompress adds a 2 byte header and 4 byte CRC we can't use.
            // The 2 byte header does contain useful data, though in this case the 2 parameters we'd be interrested in will always be 8 for compression type, and 2 for General purpose flag.
            $gzLength = strlen($gzData);
        } else {
            $gzLength = $dataLength;
        }

        if ($gzLength >= $dataLength) {
            $gzLength = $dataLength;
            $gzData = $data;
            $gzType = "\x00\x00"; // Compression type 0 = stored
            $gpFlags = "\x00\x00"; // Compression type 0 = stored
        }

        if (!is_resource($this->zipFile) && ($this->offset + $gzLength) > $this->zipMemoryThreshold) {
            $this->zipflush();
        }

        $this->buildZipEntry($filePath, $fileComment, $gpFlags, $gzType, $timestamp, $fileCRC32, $gzLength, $dataLength, $extFileAttr);

        $this->zipwrite($gzData);

        return TRUE;
    }

    /**
     * Add the content to a directory.
     *
     * @author Adam Schmalhofer <Adam.Schmalhofer@gmx.de>
     * @author A. Grandt
     *
     * @param string $realPath       Path on the file system.
     * @param string $zipPath        Filepath and name to be used in the archive.
     * @param bool   $recursive      Add content recursively, default is TRUE.
     * @param bool   $followSymlinks Follow and add symbolic links, if they are accessible, default is TRUE.
     * @param array &$addedFiles     Reference to the added files, this is used to prevent duplicates, efault is an empty array.
     *                               If you start the function by parsing an array, the array will be populated with the realPath
     *                               and zipPath kay/value pairs added to the archive by the function.
	 * @param bool   $overrideFilePermissions Force the use of the file/dir permissions set in the $extDirAttr
	 *							     and $extFileAttr parameters.
	 * @param int    $extDirAttr     Permissions for directories.
	 * @param int    $extFileAttr    Permissions for files.
     */
    public function addDirectoryContent($realPath, $zipPath, $recursive = TRUE, $followSymlinks = TRUE, &$addedFiles = array(),
					$overrideFilePermissions = FALSE, $extDirAttr = self::EXT_FILE_ATTR_DIR, $extFileAttr = self::EXT_FILE_ATTR_FILE) {
        if (file_exists($realPath) && !isset($addedFiles[realpath($realPath)])) {
            if (is_dir($realPath)) {
				if ($overrideFilePermissions) {
	                $this->addDirectory($zipPath, 0, null, $extDirAttr);
				} else {
					$this->addDirectory($zipPath, 0, null, self::getFileExtAttr($realPath));
				}
            }

            $addedFiles[realpath($realPath)] = $zipPath;

            $iter = new DirectoryIterator($realPath);
            foreach ($iter as $file) {
                if ($file->isDot()) {
                    continue;
                }
                $newRealPath = $file->getPathname();
                $newZipPath = self::pathJoin($zipPath, $file->getFilename());

                if (file_exists($newRealPath) && ($followSymlinks === TRUE || !is_link($newRealPath))) {
                    if ($file->isFile()) {
                        $addedFiles[realpath($newRealPath)] = $newZipPath;
						if ($overrideFilePermissions) {
							$this->addLargeFile($newRealPath, $newZipPath, 0, null, $extFileAttr);
						} else {
							$this->addLargeFile($newRealPath, $newZipPath, 0, null, self::getFileExtAttr($newRealPath));
						}
                    } else if ($recursive === TRUE) {
                        $this->addDirectoryContent($newRealPath, $newZipPath, $recursive, $followSymlinks, $addedFiles, $overrideFilePermissions, $extDirAttr, $extFileAttr);
                    } else {
						if ($overrideFilePermissions) {
							$this->addDirectory($zipPath, 0, null, $extDirAttr);
						} else {
							$this->addDirectory($zipPath, 0, null, self::getFileExtAttr($newRealPath));
						}
                    }
                }
            }
        }
    }

    /**
     * Add a file to the archive at the specified location and file name.
     *
     * @param string $dataFile    File name/path.
     * @param string $filePath    Filepath and name to be used in the archive.
     * @param int    $timestamp   (Optional) Timestamp for the added file, if omitted or set to 0, the current time will be used.
     * @param string $fileComment (Optional) Comment to be added to the archive for this file. To use fileComment, timestamp must be given.
	 * @param int    $extFileAttr (Optional) The external file reference, use generateExtAttr to generate this.
     * @return bool $success
     */
    public function addLargeFile($dataFile, $filePath, $timestamp = 0, $fileComment = NULL, $extFileAttr = self::EXT_FILE_ATTR_FILE)   {
        if ($this->isFinalized) {
            return FALSE;
        }

        if (is_string($dataFile) && is_file($dataFile)) {
            $this->processFile($dataFile, $filePath, $timestamp, $fileComment, $extFileAttr);
        } else if (is_resource($dataFile) && get_resource_type($dataFile) == "stream") {
            $fh = $dataFile;
            $this->openStream($filePath, $timestamp, $fileComment, $extFileAttr);

            while (!feof($fh)) {
                $this->addStreamData(fread($fh, $this->streamChunkSize));
            }
            $this->closeStream($this->addExtraField);
        }
        return TRUE;
    }

    /**
     * Create a stream to be used for large entries.
     *
     * @param string $filePath    Filepath and name to be used in the archive.
     * @param int    $timestamp   (Optional) Timestamp for the added file, if omitted or set to 0, the current time will be used.
     * @param string $fileComment (Optional) Comment to be added to the archive for this file. To use fileComment, timestamp must be given.
     * @param int    $extFileAttr (Optional) The external file reference, use generateExtAttr to generate this.
     * @throws Exception Throws an exception in case of errors
     * @return bool $success
     */
    public function openStream($filePath, $timestamp = 0, $fileComment = null, $extFileAttr = self::EXT_FILE_ATTR_FILE)   {
        if (!function_exists('sys_get_temp_dir')) {
            throw new Exception("Zip " . self::VERSION . " requires PHP version 5.2.1 or above if large files are used.");
        }

        if ($this->isFinalized) {
            return FALSE;
        }

        $this->zipflush();

        if (strlen($this->streamFilePath) > 0) {
            $this->closeStream();
        }

        $this->streamFile = self::getTemporaryFile();
        $this->streamData = fopen($this->streamFile, "wb");
        $this->streamFilePath = $filePath;
        $this->streamTimestamp = $timestamp;
        $this->streamFileComment = $fileComment;
        $this->streamFileLength = 0;
		$this->streamExtFileAttr = $extFileAttr;

        return TRUE;
    }

    /**
     * Add data to the open stream.
     *
     * @param string $data
     * @throws Exception Throws an exception in case of errors
     * @return mixed length in bytes added or FALSE if the archive is finalized or there are no open stream.
     */
    public function addStreamData($data) {
        if ($this->isFinalized || strlen($this->streamFilePath) == 0) {
            return FALSE;
        }

        $length = fwrite($this->streamData, $data, strlen($data));
        if ($length != strlen($data)) {
			throw new Exception("File IO: Error writing; Length mismatch: Expected " . strlen($data) . " bytes, wrote " . ($length === FALSE ? "NONE!" : $length));
		}
		$this->streamFileLength += $length;

		return $length;
    }

    /**
     * Close the current stream.
     *
     * @return bool $success
     */
    public function closeStream() {
        if ($this->isFinalized || strlen($this->streamFilePath) == 0) {
            return FALSE;
        }

        fflush($this->streamData);
        fclose($this->streamData);

        $this->processFile($this->streamFile, $this->streamFilePath, $this->streamTimestamp, $this->streamFileComment, $this->streamExtFileAttr);

        $this->streamData = null;
        $this->streamFilePath = null;
        $this->streamTimestamp = null;
        $this->streamFileComment = null;
        $this->streamFileLength = 0;
		$this->streamExtFileAttr = null;

        // Windows is a little slow at times, so a millisecond later, we can unlink this.
        unlink($this->streamFile);

        $this->streamFile = null;

        return TRUE;
    }

    private function processFile($dataFile, $filePath, $timestamp = 0, $fileComment = null, $extFileAttr = self::EXT_FILE_ATTR_FILE) {
        if ($this->isFinalized) {
            return FALSE;
        }

        $tempzip = self::getTemporaryFile();

        $zip = new ZipArchive;
        if ($zip->open($tempzip) === TRUE) {
            $zip->addFile($dataFile, 'file');
            $zip->close();
        }

        $file_handle = fopen($tempzip, "rb");
        $stats = fstat($file_handle);
        $eof = $stats['size']-72;

        fseek($file_handle, 6);

        $gpFlags = fread($file_handle, 2);
        $gzType = fread($file_handle, 2);
        fread($file_handle, 4);
        $fileCRC32 = fread($file_handle, 4);
        $v = unpack("Vval", fread($file_handle, 4));
        $gzLength = $v['val'];
        $v = unpack("Vval", fread($file_handle, 4));
        $dataLength = $v['val'];

        $this->buildZipEntry($filePath, $fileComment, $gpFlags, $gzType, $timestamp, $fileCRC32, $gzLength, $dataLength, $extFileAttr);

        fseek($file_handle, 34);
        $pos = 34;

        while (!feof($file_handle) && $pos < $eof) {
            $datalen = $this->streamChunkSize;
            if ($pos + $this->streamChunkSize > $eof) {
                $datalen = $eof-$pos;
            }
            $data = fread($file_handle, $datalen);
            $pos += $datalen;

            $this->zipwrite($data);
        }

        fclose($file_handle);

        unlink($tempzip);
    }

    /**
     * Close the archive.
     * A closed archive can no longer have new files added to it.
     *
     * @return bool $success
     */
    public function finalize() {
        if (!$this->isFinalized) {
            if (strlen($this->streamFilePath) > 0) {
                $this->closeStream();
            }
            $cd = implode("", $this->cdRec);

            $cdRecSize = pack("v", sizeof($this->cdRec));
            $cdRec = $cd . self::ZIP_END_OF_CENTRAL_DIRECTORY
            . $cdRecSize . $cdRecSize
            . pack("VV", strlen($cd), $this->offset);
            if (!empty($this->zipComment)) {
                $cdRec .= pack("v", strlen($this->zipComment)) . $this->zipComment;
            } else {
                $cdRec .= "\x00\x00";
            }

            $this->zipwrite($cdRec);

            $this->isFinalized = TRUE;
            $this->cdRec = NULL;

            return TRUE;
        }
        return FALSE;
    }

    /**
     * Get the handle ressource for the archive zip file.
     * If the zip haven't been finalized yet, this will cause it to become finalized
     *
     * @return zip file handle
     */
    public function getZipFile() {
        if (!$this->isFinalized) {
            $this->finalize();
        }

        $this->zipflush();

        rewind($this->zipFile);

        return $this->zipFile;
    }

    /**
     * Get the zip file contents
     * If the zip haven't been finalized yet, this will cause it to become finalized
     *
     * @return zip data
     */
    public function getZipData() {
        if (!$this->isFinalized) {
            $this->finalize();
        }
        if (!is_resource($this->zipFile)) {
            return $this->zipData;
        } else {
            rewind($this->zipFile);
            $filestat = fstat($this->zipFile);
            return fread($this->zipFile, $filestat['size']);
        }
    }

	/**
	 * Send the archive as a zip download
	 *
	 * @param String $fileName The name of the Zip archive, in ISO-8859-1 (or ASCII) encoding, ie. "archive.zip". Optional, defaults to NULL, which means that no ISO-8859-1 encoded file name will be specified.
	 * @param String $contentType Content mime type. Optional, defaults to "application/zip".
	 * @param String $utf8FileName The name of the Zip archive, in UTF-8 encoding. Optional, defaults to NULL, which means that no UTF-8 encoded file name will be specified.
	 * @param bool $inline Use Content-Disposition with "inline" instead of "attached". Optional, defaults to FALSE.
	 * @throws Exception Throws an exception in case of errors
	 * @return bool Always returns true (for backward compatibility).
	*/
	function sendZip($fileName = null, $contentType = "application/zip", $utf8FileName = null, $inline = false) {
		if (!$this->isFinalized) {
			$this->finalize();
		}
		$headerFile = null;
		$headerLine = null;
		if(headers_sent($headerFile, $headerLine)) {
        	throw new Exception("Unable to send file '$fileName'. Headers have already been sent from '$headerFile' in line $headerLine");
		}
		if(ob_get_contents() !== false && strlen(ob_get_contents())) {
			throw new Exception("Unable to send file '$fileName'. Output buffer contains the following text (typically warnings or errors):\n" . ob_get_contents());
		}
		if(@ini_get('zlib.output_compression')) {
			@ini_set('zlib.output_compression', 'Off');
		}
		header("Pragma: public");
		header("Last-Modified: " . @gmdate("D, d M Y H:i:s T"));
		header("Expires: 0");
		header("Accept-Ranges: bytes");
		header("Connection: close");
		header("Content-Type: " . $contentType);
		$cd = "Content-Disposition: ";
		if ($inline) {
			$cd .= "inline";
		} else {
			$cd .= "attached";
		}
		if ($fileName) {
			$cd .= '; filename="' . $fileName . '"';
		}
		if ($utf8FileName) {
			$cd .= "; filename*=UTF-8''" . rawurlencode($utf8FileName);
		}
		header($cd);
		header("Content-Length: ". $this->getArchiveSize());
		if (!is_resource($this->zipFile)) {
			echo $this->zipData;
		} else {
			rewind($this->zipFile);
			while (!feof($this->zipFile)) {
				echo fread($this->zipFile, $this->streamChunkSize);
			}
		}
		return true;
	}

    /**
     * Return the current size of the archive
     *
     * @return $size Size of the archive
     */
    public function getArchiveSize() {
        if (!is_resource($this->zipFile)) {
            return strlen($this->zipData);
        }
        $filestat = fstat($this->zipFile);

        return $filestat['size'];
    }

    /**
     * Calculate the 2 byte dostime used in the zip entries.
     *
     * @param int $timestamp
     * @return 2-byte encoded DOS Date
     */
    private function getDosTime($timestamp = 0) {
        $timestamp = (int)$timestamp;
        $oldTZ = @date_default_timezone_get();
        date_default_timezone_set('UTC');
        $date = ($timestamp == 0 ? getdate() : getdate($timestamp));
        date_default_timezone_set($oldTZ);
        if ($date["year"] >= 1980) {
            return pack("V", (($date["mday"] + ($date["mon"] << 5) + (($date["year"]-1980) << 9)) << 16) |
                    (($date["seconds"] >> 1) + ($date["minutes"] << 5) + ($date["hours"] << 11)));
        }
        return "\x00\x00\x00\x00";
    }

    /**
     * Build the Zip file structures
     *
     * @param string $filePath
     * @param string $fileComment
     * @param string $gpFlags
     * @param string $gzType
     * @param int    $timestamp
     * @param string $fileCRC32
     * @param int    $gzLength
     * @param int    $dataLength
     * @param int    $extFileAttr Use self::EXT_FILE_ATTR_FILE for files, self::EXT_FILE_ATTR_DIR for Directories.
     */
    private function buildZipEntry($filePath, $fileComment, $gpFlags, $gzType, $timestamp, $fileCRC32, $gzLength, $dataLength, $extFileAttr) {
        $filePath = str_replace("\\", "/", $filePath);
        $fileCommentLength = (empty($fileComment) ? 0 : strlen($fileComment));
        $timestamp = (int)$timestamp;
        $timestamp = ($timestamp == 0 ? time() : $timestamp);

        $dosTime = $this->getDosTime($timestamp);
        $tsPack = pack("V", $timestamp);

        if (!isset($gpFlags) || strlen($gpFlags) != 2) {
            $gpFlags = "\x00\x00";
        }

        $isFileUTF8 = mb_check_encoding($filePath, "UTF-8") && !mb_check_encoding($filePath, "ASCII");
        $isCommentUTF8 = !empty($fileComment) && mb_check_encoding($fileComment, "UTF-8") && !mb_check_encoding($fileComment, "ASCII");

		$localExtraField = "";
		$centralExtraField = "";

		if ($this->addExtraField) {
            $localExtraField .= "\x55\x54\x09\x00\x03" . $tsPack . $tsPack . messify_Zip::EXTRA_FIELD_NEW_UNIX_GUID;
			$centralExtraField .= "\x55\x54\x05\x00\x03" . $tsPack . messify_Zip::EXTRA_FIELD_NEW_UNIX_GUID;
		}

		if ($isFileUTF8 || $isCommentUTF8) {
            $flag = 0;
            $gpFlagsV = unpack("vflags", $gpFlags);
            if (isset($gpFlagsV['flags'])) {
                $flag = $gpFlagsV['flags'];
            }
            $gpFlags = pack("v", $flag | (1 << 11));

			if ($isFileUTF8) {
				$utfPathExtraField = "\x75\x70"
					. pack ("v", (5 + strlen($filePath)))
					. "\x01"
					.  pack("V", crc32($filePath))
					. $filePath;

				$localExtraField .= $utfPathExtraField;
				$centralExtraField .= $utfPathExtraField;
			}
			if ($isCommentUTF8) {
				$centralExtraField .= "\x75\x63" // utf8 encoded file comment extra field
					. pack ("v", (5 + strlen($fileComment)))
					. "\x01"
					. pack("V", crc32($fileComment))
					. $fileComment;
			}
        }

        $header = $gpFlags . $gzType . $dosTime. $fileCRC32
			. pack("VVv", $gzLength, $dataLength, strlen($filePath)); // File name length

        $zipEntry  = self::ZIP_LOCAL_FILE_HEADER
			. self::ATTR_VERSION_TO_EXTRACT
			. $header
			. pack("v", strlen($localExtraField)) // Extra field length
			. $filePath // FileName
			. $localExtraField; // Extra fields

		$this->zipwrite($zipEntry);

        $cdEntry  = self::ZIP_CENTRAL_FILE_HEADER
			. self::ATTR_MADE_BY_VERSION
			. ($dataLength === 0 ? "\x0A\x00" : self::ATTR_VERSION_TO_EXTRACT)
			. $header
			. pack("v", strlen($centralExtraField)) // Extra field length
			. pack("v", $fileCommentLength) // File comment length
			. "\x00\x00" // Disk number start
			. "\x00\x00" // internal file attributes
			. pack("V", $extFileAttr) // External file attributes
			. pack("V", $this->offset) // Relative offset of local header
			. $filePath // FileName
			. $centralExtraField; // Extra fields

		if (!empty($fileComment)) {
            $cdEntry .= $fileComment; // Comment
        }

        $this->cdRec[] = $cdEntry;
        $this->offset += strlen($zipEntry) + $gzLength;
    }

    private function zipwrite($data) {
        if (!is_resource($this->zipFile)) {
            $this->zipData .= $data;
        } else {
            fwrite($this->zipFile, $data);
            fflush($this->zipFile);
        }
    }

    private function zipflush() {
        if (!is_resource($this->zipFile)) {
            $this->zipFile = tmpfile();
            fwrite($this->zipFile, $this->zipData);
            $this->zipData = NULL;
        }
    }

    /**
     * Join $file to $dir path, and clean up any excess slashes.
     *
     * @param string $dir
     * @param string $file
     */
    public static function pathJoin($dir, $file) {
        if (empty($dir) || empty($file)) {
            return self::getRelativePath($dir . $file);
        }
        return self::getRelativePath($dir . '/' . $file);
    }

    /**
     * Clean up a path, removing any unnecessary elements such as /./, // or redundant ../ segments.
     * If the path starts with a "/", it is deemed an absolute path and any /../ in the beginning is stripped off.
     * The returned path will not end in a "/".
	 *
	 * Sometimes, when a path is generated from multiple fragments,
	 *  you can get something like "../data/html/../images/image.jpeg"
	 * This will normalize that example path to "../data/images/image.jpeg"
     *
     * @param string $path The path to clean up
     * @return string the clean path
     */
    public static function getRelativePath($path) {
        $path = preg_replace("#/+\.?/+#", "/", str_replace("\\", "/", $path));
        $dirs = explode("/", rtrim(preg_replace('#^(?:\./)+#', '', $path), '/'));

        $offset = 0;
        $sub = 0;
        $subOffset = 0;
        $root = "";

        if (empty($dirs[0])) {
            $root = "/";
            $dirs = array_splice($dirs, 1);
        } else if (preg_match("#[A-Za-z]:#", $dirs[0])) {
            $root = strtoupper($dirs[0]) . "/";
            $dirs = array_splice($dirs, 1);
        }

        $newDirs = array();
        foreach ($dirs as $dir) {
            if ($dir !== "..") {
                $subOffset--;
                $newDirs[++$offset] = $dir;
            } else {
                $subOffset++;
                if (--$offset < 0) {
                    $offset = 0;
                    if ($subOffset > $sub) {
                        $sub++;
                    }
                }
            }
        }

        if (empty($root)) {
            $root = str_repeat("../", $sub);
        }
        return $root . implode("/", array_slice($newDirs, 0, $offset));
    }

	/**
	 * Create the file permissions for a file or directory, for use in the extFileAttr parameters.
	 *
	 * @param int   $owner Unix permisions for owner (octal from 00 to 07)
	 * @param int   $group Unix permisions for group (octal from 00 to 07)
	 * @param int   $other Unix permisions for others (octal from 00 to 07)
	 * @param bool  $isFile
	 * @return EXTRERNAL_REF field.
	 */
	public static function generateExtAttr($owner = 07, $group = 05, $other = 05, $isFile = true) {
		$fp = $isFile ? self::S_IFREG : self::S_IFDIR;
		$fp |= (($owner & 07) << 6) | (($group & 07) << 3) | ($other & 07);

		return ($fp << 16) | ($isFile ? self::S_DOS_A : self::S_DOS_D);
	}

	/**
	 * Get the file permissions for a file or directory, for use in the extFileAttr parameters.
	 *
	 * @param string $filename
	 * @return external ref field, or FALSE if the file is not found.
	 */
	public static function getFileExtAttr($filename) {
		if (file_exists($filename)) {
			$fp = fileperms($filename) << 16;
			return $fp | (is_dir($filename) ? self::S_DOS_D : self::S_DOS_A);
		}
		return FALSE;
	}
	/**
	 * Returns the path to a temporary file.
	 * @return string
	 */
	private static function getTemporaryFile() {
		if(is_callable(self::$temp)) {
			$temporaryFile = @call_user_func(self::$temp);
			if(is_string($temporaryFile) && strlen($temporaryFile) && is_writable($temporaryFile)) {
				return $temporaryFile;
			}
		}
		$temporaryDirectory = (is_string(self::$temp) && strlen(self::$temp)) ? self::$temp : sys_get_temp_dir();
		return tempnam($temporaryDirectory, 'Zip');
	}
}

/**
 * UnZip Class
 *
 * This class is based on a library I found at PHPClasses:
 * http://phpclasses.org/package/2495-PHP-Pack-and-unpack-files-packed-in-ZIP-archives.html
 *
 * The original library is a little rough around the edges so I
 * refactored it and added several additional methods -- Phil Sturgeon
 *
 * This class requires extension ZLib Enabled.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Encryption
 * @author		Alexandre Tedeschi
 * @author		Phil Sturgeon
 * @author		Don Myers
 * @link		http://bitbucket.org/philsturgeon/codeigniter-unzip
 * @license
 * @version     1.0.0
 */
class messify_Unzip {

	private $compressed_list = array();

	// List of files in the ZIP
	private $central_dir_list = array();

	// Central dir list... It's a kind of 'extra attributes' for a set of files
	private $end_of_central = array();

	// End of central dir, contains ZIP Comments
	private $info = array();
	private $error = array();
	private $_zip_file = '';
	private $_target_dir = FALSE;
	private $apply_chmod = 0777;
	private $fh;
	private $zip_signature = "\x50\x4b\x03\x04";

	// local file header signature
	private $dir_signature = "\x50\x4b\x01\x02";

	// central dir header signature
	private $central_signature_end = "\x50\x4b\x05\x06";

	// ignore these directories (useless meta data)
	private $_skip_dirs = array('__MACOSX');

	private $_allow_extensions = NULL; // What is allowed out of the zip

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access    Public
	 * @param     string
	 * @return    none
	 */
	function __construct()
	{

	}

	// --------------------------------------------------------------------

	/**
	 * re inizilize all variables
	 * @access	Private
	 * @param		none
	 * @return	none
	 */
	private function _reinit()
	{
		$this->compressed_list = array();
		$this->central_dir_list = array();
		$this->end_of_central = array();
		$this->info = array();
		$this->error = array();
	}

	/**
	 * Unzip all files in archive.
	 *
	 * @access    Public
	 * @param     none
	 * @return    none
	 */
	public function extract($zip_file, $target_dir = NULL, $preserve_filepath = TRUE)
	{
		$this->_reinit();
		$this->_zip_file = $zip_file;
		$this->_target_dir = $target_dir ? $target_dir : dirname($this->_zip_file);

		if ( ! $files = $this->_list_files())
		{
			$this->set_error('ZIP folder was empty.');
			return FALSE;
		}

		$file_locations = array();
		foreach ($files as $file => $trash)
		{
			$dirname = pathinfo($file, PATHINFO_DIRNAME);
			$extension = pathinfo($file, PATHINFO_EXTENSION);

			$folders = explode('/', $dirname);
			$out_dn = $this->_target_dir . '/' . $dirname;

			// Skip stuff in stupid folders
			if (in_array(current($folders), $this->_skip_dirs))
			{
				continue;
			}

			// Skip any files that are not allowed
			if (is_array($this->_allow_extensions) AND $extension AND ! in_array($extension, $this->_allow_extensions))
			{
				continue;
			}

			if ( ! is_dir($out_dn) AND $preserve_filepath)
			{
				$str = "";
				foreach ($folders as $folder)
				{
					$str = $str ? $str . '/' . $folder : $folder;
					if ( ! is_dir($this->_target_dir . '/' . $str))
					{
						$this->set_debug('Creating folder: ' . $this->_target_dir . '/' . $str);

						if ( ! @mkdir($this->_target_dir . '/' . $str))
						{
							$this->set_error('Desitnation path is not writable.');
							return FALSE;
						}

						// Apply chmod if configured to do so
						$this->apply_chmod AND chmod($this->_target_dir . '/' . $str, $this->apply_chmod);
					}
				}
			}

			if (substr($file, -1, 1) == '/') continue;

			$file_locations[] = $file_location = $this->_target_dir . '/' . ($preserve_filepath ? $file : basename($file));

			$this->_extract_file($file, $file_location);
		}

		$this->compressed_list = array();

		return $file_locations;
	}

	// --------------------------------------------------------------------

	/**
	 * What extensions do we want out of this ZIP
	 *
	 * @access    Public
	 * @param     none
	 * @return    none
	 */
	public function allow($ext = NULL)
	{
		$this->_allow_extensions = $ext;
	}

	// --------------------------------------------------------------------

	/**
	 * Show error messages
	 *
	 * @access    public
	 * @param    string
	 * @return    string
	 */
	public function error_string($open = '<p>', $close = '</p>')
	{
		return $open . implode($close . $open, $this->error) . $close;
	}

	// --------------------------------------------------------------------

	/**
	 * Show debug messages
	 *
	 * @access    public
	 * @param    string
	 * @return    string
	 */
	public function debug_string($open = '<p>', $close = '</p>')
	{
		return $open . implode($close . $open, $this->info) . $close;
	}

	// --------------------------------------------------------------------

	/**
	 * Save errors
	 *
	 * @access    Private
	 * @param    string
	 * @return    none
	 */
	function set_error($string)
	{
		$this->error[] = $string;
	}

	// --------------------------------------------------------------------

	/**
	 * Save debug data
	 *
	 * @access    Private
	 * @param    string
	 * @return    none
	 */
	function set_debug($string)
	{
		$this->info[] = $string;
	}

	// --------------------------------------------------------------------

	/**
	 * List all files in archive.
	 *
	 * @access    Public
	 * @param     boolean
	 * @return    mixed
	 */
	private function _list_files($stop_on_file = FALSE)
	{
		if (sizeof($this->compressed_list))
		{
			$this->set_debug('Returning already loaded file list.');
			return $this->compressed_list;
		}

		// Open file, and set file handler
		$fh = fopen($this->_zip_file, 'r');
		$this->fh = &$fh;

		if ( ! $fh)
		{
			$this->set_error('Failed to load file: ' . $this->_zip_file);
			return FALSE;
		}

		$this->set_debug('Loading list from "End of Central Dir" index list...');

		if ( ! $this->_load_file_list_by_eof($fh, $stop_on_file))
		{
			$this->set_debug('Failed! Trying to load list looking for signatures...');

			if ( ! $this->_load_files_by_signatures($fh, $stop_on_file))
			{
				$this->set_debug('Failed! Could not find any valid header.');
				$this->set_error('ZIP File is corrupted or empty');

				return FALSE;
			}
		}

		return $this->compressed_list;
	}

	// --------------------------------------------------------------------

	/**
	 * Unzip file in archive.
	 *
	 * @access    Public
	 * @param     string, boolean
	 * @return    Unziped file.
	 */
	private function _extract_file($compressed_file_name, $target_file_name = FALSE)
	{
		if ( ! sizeof($this->compressed_list))
		{
			$this->set_debug('Trying to unzip before loading file list... Loading it!');
			$this->_list_files(FALSE, $compressed_file_name);
		}

		$fdetails = &$this->compressed_list[$compressed_file_name];

		if ( ! isset($this->compressed_list[$compressed_file_name]))
		{
			$this->set_error('File "<strong>$compressed_file_name</strong>" is not compressed in the zip.');
			return FALSE;
		}

		if (substr($compressed_file_name, -1) == '/')
		{
			$this->set_error('Trying to unzip a folder name "<strong>$compressed_file_name</strong>".');
			return FALSE;
		}

		if ( ! $fdetails['uncompressed_size'])
		{
			$this->set_debug('File "<strong>$compressed_file_name</strong>" is empty.');

			return $target_file_name ? file_put_contents($target_file_name, '') : '';
		}

		fseek($this->fh, $fdetails['contents_start_offset']);
		$ret = $this->_uncompress(
			fread($this->fh, $fdetails['compressed_size']),
			$fdetails['compression_method'],
			$fdetails['uncompressed_size'],
			$target_file_name
		);

		if ($this->apply_chmod AND $target_file_name)
		{
			chmod($target_file_name, 0777);
		}

		return $ret;
	}

	// --------------------------------------------------------------------

	/**
	 * Free the file resource.
	 *
	 * @access    Public
	 * @param     none
	 * @return    none
	 */
	public function close()
	{
		// Free the file resource
		if ($this->fh)
		{
			fclose($this->fh);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Free the file resource Automatic destroy.
	 *
	 * @access    Public
	 * @param     none
	 * @return    none
	 */
	public function __destroy()
	{
		$this->close();
	}

	// --------------------------------------------------------------------

	/**
	 * Uncompress file. And save it to the targetFile.
	 *
	 * @access    Private
	 * @param     Filecontent, int, int, boolean
	 * @return    none
	 */
	private function _uncompress($content, $mode, $uncompressed_size, $target_file_name = FALSE)
	{
		switch ($mode)
		{
			case 0:
				return $target_file_name ? file_put_contents($target_file_name, $content) : $content;
			case 1:
				$this->set_error('Shrunk mode is not supported... yet?');
				return FALSE;
			case 2:
			case 3:
			case 4:
			case 5:
				$this->set_error('Compression factor ' . ($mode - 1) . ' is not supported... yet?');
				return FALSE;
			case 6:
				$this->set_error('Implode is not supported... yet?');
				return FALSE;
			case 7:
				$this->set_error('Tokenizing compression algorithm is not supported... yet?');
				return FALSE;
			case 8:
				// Deflate
				return $target_file_name ?
						file_put_contents($target_file_name, gzinflate($content, $uncompressed_size)) :
						gzinflate($content, $uncompressed_size);
			case 9:
				$this->set_error('Enhanced Deflating is not supported... yet?');
				return FALSE;
			case 10:
				$this->set_error('PKWARE Date Compression Library Impoloding is not supported... yet?');
				return FALSE;
			case 12:
				// Bzip2
				return $target_file_name ?
						file_put_contents($target_file_name, bzdecompress($content)) :
						bzdecompress($content);
			case 18:
				$this->set_error('IBM TERSE is not supported... yet?');
				return FALSE;
			default:
				$this->set_error('Unknown uncompress method: $mode');
				return FALSE;
		}
	}

	private function _load_file_list_by_eof(&$fh, $stop_on_file = FALSE)
	{
		// Check if there's a valid Central Dir signature.
		// Let's consider a file comment smaller than 1024 characters...
		// Actually, it length can be 65536.. But we're not going to support it.

		for ($x = 0; $x < 1024; $x++)
		{
			fseek($fh, -22 - $x, SEEK_END);

			$signature = fread($fh, 4);

			if ($signature == $this->central_signature_end)
			{
				// If found EOF Central Dir
				$eodir['disk_number_this'] = unpack("v", fread($fh, 2)); // number of this disk
				$eodir['disk_number'] = unpack("v", fread($fh, 2)); // number of the disk with the start of the central directory
				$eodir['total_entries_this'] = unpack("v", fread($fh, 2)); // total number of entries in the central dir on this disk
				$eodir['total_entries'] = unpack("v", fread($fh, 2)); // total number of entries in
				$eodir['size_of_cd'] = unpack("V", fread($fh, 4)); // size of the central directory
				$eodir['offset_start_cd'] = unpack("V", fread($fh, 4)); // offset of start of central directory with respect to the starting disk number
				$zip_comment_lenght = unpack("v", fread($fh, 2)); // zipfile comment length
				$eodir['zipfile_comment'] = $zip_comment_lenght[1] ? fread($fh, $zip_comment_lenght[1]) : ''; // zipfile comment

				$this->end_of_central = array(
					'disk_number_this' => $eodir['disk_number_this'][1],
					'disk_number' => $eodir['disk_number'][1],
					'total_entries_this' => $eodir['total_entries_this'][1],
					'total_entries' => $eodir['total_entries'][1],
					'size_of_cd' => $eodir['size_of_cd'][1],
					'offset_start_cd' => $eodir['offset_start_cd'][1],
					'zipfile_comment' => $eodir['zipfile_comment'],
				);

				// Then, load file list
				fseek($fh, $this->end_of_central['offset_start_cd']);
				$signature = fread($fh, 4);

				while ($signature == $this->dir_signature)
				{
					$dir['version_madeby'] = unpack("v", fread($fh, 2)); // version made by
					$dir['version_needed'] = unpack("v", fread($fh, 2)); // version needed to extract
					$dir['general_bit_flag'] = unpack("v", fread($fh, 2)); // general purpose bit flag
					$dir['compression_method'] = unpack("v", fread($fh, 2)); // compression method
					$dir['lastmod_time'] = unpack("v", fread($fh, 2)); // last mod file time
					$dir['lastmod_date'] = unpack("v", fread($fh, 2)); // last mod file date
					$dir['crc-32'] = fread($fh, 4);			  // crc-32
					$dir['compressed_size'] = unpack("V", fread($fh, 4)); // compressed size
					$dir['uncompressed_size'] = unpack("V", fread($fh, 4)); // uncompressed size
					$zip_file_length = unpack("v", fread($fh, 2)); // filename length
					$extra_field_length = unpack("v", fread($fh, 2)); // extra field length
					$fileCommentLength = unpack("v", fread($fh, 2)); // file comment length
					$dir['disk_number_start'] = unpack("v", fread($fh, 2)); // disk number start
					$dir['internal_attributes'] = unpack("v", fread($fh, 2)); // internal file attributes-byte1
					$dir['external_attributes1'] = unpack("v", fread($fh, 2)); // external file attributes-byte2
					$dir['external_attributes2'] = unpack("v", fread($fh, 2)); // external file attributes
					$dir['relative_offset'] = unpack("V", fread($fh, 4)); // relative offset of local header
					$dir['file_name'] = fread($fh, $zip_file_length[1]);							 // filename
					$dir['extra_field'] = $extra_field_length[1] ? fread($fh, $extra_field_length[1]) : ''; // extra field
					$dir['file_comment'] = $fileCommentLength[1] ? fread($fh, $fileCommentLength[1]) : ''; // file comment

					// Convert the date and time, from MS-DOS format to UNIX Timestamp
					$binary_mod_date = str_pad(decbin($dir['lastmod_date'][1]), 16, '0', STR_PAD_LEFT);
					$binary_mod_time = str_pad(decbin($dir['lastmod_time'][1]), 16, '0', STR_PAD_LEFT);
					$last_mod_year = bindec(substr($binary_mod_date, 0, 7)) + 1980;
					$last_mod_month = bindec(substr($binary_mod_date, 7, 4));
					$last_mod_day = bindec(substr($binary_mod_date, 11, 5));
					$last_mod_hour = bindec(substr($binary_mod_time, 0, 5));
					$last_mod_minute = bindec(substr($binary_mod_time, 5, 6));
					$last_mod_second = bindec(substr($binary_mod_time, 11, 5));

					$this->central_dir_list[$dir['file_name']] = array(
						'version_madeby' => $dir['version_madeby'][1],
						'version_needed' => $dir['version_needed'][1],
						'general_bit_flag' => str_pad(decbin($dir['general_bit_flag'][1]), 8, '0', STR_PAD_LEFT),
						'compression_method' => $dir['compression_method'][1],
						'lastmod_datetime' => mktime($last_mod_hour, $last_mod_minute, $last_mod_second, $last_mod_month, $last_mod_day, $last_mod_year),
						'crc-32' => str_pad(dechex(ord($dir['crc-32'][3])), 2, '0', STR_PAD_LEFT) .
						str_pad(dechex(ord($dir['crc-32'][2])), 2, '0', STR_PAD_LEFT) .
						str_pad(dechex(ord($dir['crc-32'][1])), 2, '0', STR_PAD_LEFT) .
						str_pad(dechex(ord($dir['crc-32'][0])), 2, '0', STR_PAD_LEFT),
						'compressed_size' => $dir['compressed_size'][1],
						'uncompressed_size' => $dir['uncompressed_size'][1],
						'disk_number_start' => $dir['disk_number_start'][1],
						'internal_attributes' => $dir['internal_attributes'][1],
						'external_attributes1' => $dir['external_attributes1'][1],
						'external_attributes2' => $dir['external_attributes2'][1],
						'relative_offset' => $dir['relative_offset'][1],
						'file_name' => $dir['file_name'],
						'extra_field' => $dir['extra_field'],
						'file_comment' => $dir['file_comment'],
					);

					$signature = fread($fh, 4);
				}

				// If loaded centralDirs, then try to identify the offsetPosition of the compressed data.
				if ($this->central_dir_list)
				{
					foreach ($this->central_dir_list as $filename => $details)
					{
						$i = $this->_get_file_header($fh, $details['relative_offset']);

						$this->compressed_list[$filename]['file_name'] = $filename;
						$this->compressed_list[$filename]['compression_method'] = $details['compression_method'];
						$this->compressed_list[$filename]['version_needed'] = $details['version_needed'];
						$this->compressed_list[$filename]['lastmod_datetime'] = $details['lastmod_datetime'];
						$this->compressed_list[$filename]['crc-32'] = $details['crc-32'];
						$this->compressed_list[$filename]['compressed_size'] = $details['compressed_size'];
						$this->compressed_list[$filename]['uncompressed_size'] = $details['uncompressed_size'];
						$this->compressed_list[$filename]['lastmod_datetime'] = $details['lastmod_datetime'];
						$this->compressed_list[$filename]['extra_field'] = $i['extra_field'];
						$this->compressed_list[$filename]['contents_start_offset'] = $i['contents_start_offset'];

						if (strtolower($stop_on_file) == strtolower($filename))
						{
							break;
						}
					}
				}

				return TRUE;
			}
		}
		return FALSE;
	}

	private function _load_files_by_signatures(&$fh, $stop_on_file = FALSE)
	{
		fseek($fh, 0);

		$return = FALSE;
		for (;;)
		{
			$details = $this->_get_file_header($fh);

			if ( ! $details)
			{
				$this->set_debug('Invalid signature. Trying to verify if is old style Data Descriptor...');
				fseek($fh, 12 - 4, SEEK_CUR); // 12: Data descriptor - 4: Signature (that will be read again)
				$details = $this->_get_file_header($fh);
			}

			if ( ! $details)
			{
				$this->set_debug('Still invalid signature. Probably reached the end of the file.');
				break;
			}

			$filename = $details['file_name'];
			$this->compressed_list[$filename] = $details;
			$return = true;

			if (strtolower($stop_on_file) == strtolower($filename))
			{
				break;
			}
		}

		return $return;
	}

	private function _get_file_header(&$fh, $start_offset = FALSE)
	{
		if ($start_offset !== FALSE)
		{
			fseek($fh, $start_offset);
		}

		$signature = fread($fh, 4);

		if ($signature == $this->zip_signature)
		{
			// Get information about the zipped file
			$file['version_needed'] = unpack("v", fread($fh, 2)); // version needed to extract
			$file['general_bit_flag'] = unpack("v", fread($fh, 2)); // general purpose bit flag
			$file['compression_method'] = unpack("v", fread($fh, 2)); // compression method
			$file['lastmod_time'] = unpack("v", fread($fh, 2)); // last mod file time
			$file['lastmod_date'] = unpack("v", fread($fh, 2)); // last mod file date
			$file['crc-32'] = fread($fh, 4);			  // crc-32
			$file['compressed_size'] = unpack("V", fread($fh, 4)); // compressed size
			$file['uncompressed_size'] = unpack("V", fread($fh, 4)); // uncompressed size
			$zip_file_length = unpack("v", fread($fh, 2)); // filename length
			$extra_field_length = unpack("v", fread($fh, 2)); // extra field length
			$file['file_name'] = fread($fh, $zip_file_length[1]); // filename
			$file['extra_field'] = $extra_field_length[1] ? fread($fh, $extra_field_length[1]) : ''; // extra field
			$file['contents_start_offset'] = ftell($fh);

			// Bypass the whole compressed contents, and look for the next file
			fseek($fh, $file['compressed_size'][1], SEEK_CUR);

			// Convert the date and time, from MS-DOS format to UNIX Timestamp
			$binary_mod_date = str_pad(decbin($file['lastmod_date'][1]), 16, '0', STR_PAD_LEFT);
			$binary_mod_time = str_pad(decbin($file['lastmod_time'][1]), 16, '0', STR_PAD_LEFT);

			$last_mod_year = bindec(substr($binary_mod_date, 0, 7)) + 1980;
			$last_mod_month = bindec(substr($binary_mod_date, 7, 4));
			$last_mod_day = bindec(substr($binary_mod_date, 11, 5));
			$last_mod_hour = bindec(substr($binary_mod_time, 0, 5));
			$last_mod_minute = bindec(substr($binary_mod_time, 5, 6));
			$last_mod_second = bindec(substr($binary_mod_time, 11, 5));

			// Mount file table
			$i = array(
				'file_name' => $file['file_name'],
				'compression_method' => $file['compression_method'][1],
				'version_needed' => $file['version_needed'][1],
				'lastmod_datetime' => mktime($last_mod_hour, $last_mod_minute, $last_mod_second, $last_mod_month, $last_mod_day, $last_mod_year),
				'crc-32' => str_pad(dechex(ord($file['crc-32'][3])), 2, '0', STR_PAD_LEFT) .
				str_pad(dechex(ord($file['crc-32'][2])), 2, '0', STR_PAD_LEFT) .
				str_pad(dechex(ord($file['crc-32'][1])), 2, '0', STR_PAD_LEFT) .
				str_pad(dechex(ord($file['crc-32'][0])), 2, '0', STR_PAD_LEFT),
				'compressed_size' => $file['compressed_size'][1],
				'uncompressed_size' => $file['uncompressed_size'][1],
				'extra_field' => $file['extra_field'],
				'general_bit_flag' => str_pad(decbin($file['general_bit_flag'][1]), 8, '0', STR_PAD_LEFT),
				'contents_start_offset' => $file['contents_start_offset']
			);

			return $i;
		}

		return FALSE;
	}
}
