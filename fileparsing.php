<?php 

/**
 * Parsing functions for reading binary files
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/swtparser
 *
 * @author Gustaf Mossakowski, gustaf@koenige.org
 * @copyright Copyright © 2005, 2012, 2014-2015 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Reads a definition file for a part of the file structure
 *
 * Definition files may have several comment lines starting with # at the start.
 * The following lines must each contain the following values, separated by a
 * tabulator: 
 *		string starting hexadecimal code, 
 *		string ending hexadecimal code,
 *		string type (asc, bin, b2a, boo)
 *		string content = description of what is the data about
 * @param string $part
 * @param string $type (optional, 'fields' or 'replacements')
 * @return array structure of part
 * @see zzparse_interpret()
 */
function zzparse_structure($part, $type = 'fields') {
	static $structure;
	// check if we already have read the structure for this part
	if (!empty($structure[$part])) return $structure[$part];
	
	$dirs = array();
	if (defined('FILEVERSION')) {
		$max = strlen(FILEVERSION);
		for ($i = 0; $i < $max; $i++) {
			$dirs[] = '-v'.substr(FILEVERSION, 0, $max - $i)
				.(str_repeat('x', $i));
		}
	}
	$dirs[] = '';
	foreach ($dirs as $dir) {
		$filename = __DIR__.'/structure'.$dir.'/'.$part.'.csv';
		if (!file_exists($filename)) $filename = '';
		else break;
	}
	if (!$filename) {
		die(sprintf('Structure file for <code>%s</code> does not exist.', $part));
	}
	$defs = file($filename);
	switch ($type) {
		case 'fields': $elements = 4; break;
		case 'replacements': $elements = 2; break;
		default: die('Not a valid choice for a structural file.');
	}
	$structure[$part] = array();
	foreach ($defs as $no => $def) {
		if (substr($def, 0, 1) === '#') continue;
		$def = rtrim($def);
		$line = explode("\t", $def);
		if (!isset($line[$elements-1])) $line[$elements-1] = '';
		if (count($line) != $elements) {
			die(sprintf('Structure file for <code>%s</code> is invalid (line %s)', $part, $no+1));
		}
		switch ($type) {
		case 'fields':
			list($my['begin'], $my['end'], $my['type'], $my['content']) = $line;
			if (!$my['content']) {
				$my['content'] = 'BIN '.$my['begin'].($my['end'] ? '-'.$my['end'] : '');
			}
			$structure[$part][] = $my;
			break;
		case 'replacements':
			list($my['key'], $my['replacement']) = $line;
			$structure[$part][$my['key']] = str_replace('selection', '', $part).$my['replacement'];
			break;
		}
	}
	return $structure[$part];
}

/**
 * Interprets binary data depending on structure
 *
 * @param string $binary binary data
 * @param string $part name of structural file for this part
 * @param int $start (optional, looks at just a part of the data)
 * @param int $end (optional, looks at just a part of the data)
 * @return array data 
 *		array out: field title => value
 *		array bin: begin, end and type (for development)
 */
function zzparse_interpret($binary, $part, $start = 0, $end = false) {
	if ($end) $binary = substr($binary, $start, $end);
	$data = array();
	$data['out'] = array();
	$data['bin'] = array();
	$structure = zzparse_structure($part);

	foreach ($structure as $line) {
		$substring = zzparse_binpos($binary, $line['begin'], $line['end']);
		$data['bin'][] = array(
			'begin' => hexdec($line['begin']) + $start, 
			'end' => ($line['end'] ? hexdec($line['end']) : hexdec($line['begin'])) + $start,
			'type' => $line['type'],
			'content' => $line['content']
		);
		switch (substr($line['type'], 0, 3)) {
		case 'asc':
			// Content is in ASCII format
			// cuts starting byte with value 00 which marks the end of string, 
			// rest is junk data
			$data['out'][$line['content']] = zzparse_tonullbyte($substring);
			break;
			
		case 'bin':
			// Content is binary value
			$data['out'][$line['content']] = zzparse_binary($substring);
			break;

		case 'bib':
			// Content is binary value, big endian
			$data['out'][$line['content']] = zzparse_binary(strrev($substring));
			break;

		case 'b2a':
			// Content is hexadecimal value
			$data['out'][$line['content']] = hexdec(zzparse_binary($substring));
			break;

		case 'int':
			// Content is integer value, little endian
			$data['out'][$line['content']] = hexdec(bin2hex(($substring)));
			break;

		case 'inb':
			// Content is integer value, big endian
			$data['out'][$line['content']] = hexdec(bin2hex(strrev($substring)));
			break;

		case 'boo':
			// Content is boolean
			$substring = chop(zzparse_binary($substring));
			switch ($substring) {
				case 'FF': $data['out'][$line['content']] = 1; break;
				case '00': $data['out'][$line['content']] = 0; break;
				default: $data['out'][$line['content']] = NULL; break;
			}
			break;

		case 'dat':
			// Content is date
			// Days since 12/30/1899, big endian
			$days = hexdec(bin2hex(strrev($substring)));
			if ($days > 0) {
				$date = date_create('1899-12-30');
				date_modify($date, '+'.$days.' days');
				$data['out'][$line['content']] = date_format($date, 'd.m.Y');
			}
			break;

		case 'tim':
			// Content is time
			if (zzparse_binary($substring) != '00') {
				$hours = str_pad(hexdec(bin2hex($substring[0])), 2, '0', STR_PAD_LEFT);
				$minutes = str_pad(hexdec(bin2hex($substring[1])), 2, '0', STR_PAD_LEFT);
				$data['out'][$line['content']] = $hours.':'.$minutes;
			}
			break;
		
		case 'sel':
			$area = strtolower($line['content']);
			if (preg_match('/^sel\:\d+/', $line['type'])) $area = str_replace('sel:', '', $line['type']);
			$area .= '-selection';
			$selection = zzparse_structure($area, 'replacements');
			$value = zzparse_binary($substring);
			if (!in_array($value, array_keys($selection))) {
				$data['out'][$line['content']] = 'UNKNOWN: '.$value;
			} else {
				$data['out'][$line['content']] = $selection[$value];
			}
			break;
		}
	}
	return $data;
}

/**
 * Get binary substring from file contents
 *
 * @param string $val string that is searched
 * @param string $start hex value of first position of substring
 * @param string $end hex value of last position of substring; optional: start
 *		value will be used if substring should be only one byte long
 * @param int $length (optional)
 * @return string
 */
function zzparse_binpos($val, $start, $end = false, $length = false) {
	// if it's only one byte long, end = start
	if (!$end) $end = $start;
	if ($length) {
		$output = substr($val, hexdec($start), $length);
	} else {
		$output = substr($val, hexdec($start), (hexdec($end)-hexdec($start)+1));
	}
	return $output;
}

/**
 * Returns hex value for each byte of a string, separated by spaces
 *
 * @param string $val
 * @return string
 */
function zzparse_binary($val) {
	$output = '';
	$len = strlen($val);
	for ($a = 0; $a < $len; $a++)
		$bytes[] = bin2hex($val[$a]);
	if (empty($bytes)) {
		// @todo: error, no value was given
		return 'XX';
	}
	foreach ($bytes as $byte) {
		if (strlen($byte) == 1) $byte = '0'.$byte;
		$output[] = strtoupper($byte);
	}
	// remove beginning 00
	while (reset($output) === '00') {
		if (count($output) < 2) break;
		array_shift($output);
	}
	$output = implode(' ', $output);
	return $output;
}

/**
 * Returns substring from begin of string to first occurence of a null byte
 *
 * @param string $val
 * @return string
 */
function zzparse_tonullbyte($val) {
	if (strstr($val, chr('00'))) {
		$output = substr($val, 0, strpos($val, chr('00')));
	} else {
		$output = $val;
	}
	return $output;
}
