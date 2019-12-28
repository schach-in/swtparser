<?php

/**
 * Output of a binary file in hexadecimal and ASCII representation alongside
 * Optionally, known areas may be marked
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/swtparser
 *
 * @author Gustaf Mossakowski, gustaf@koenige.org
 * @copyright Copyright © 2012, 2014, 2019 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

// required files
require_once 'fileparsing.php';
ini_set('max_execution_time', 960);

/**
 * Output of a binary file in hexadecimal and ASCII representation alongside
 * Optionally, known areas may be marked
 *
 * @param string $filename
 * @param array $markings (optional)
 *		indexed list, int begin, int end, string type (will be used for class),
 *		string content (will be used for title-attribute)
 * @return string HTML output
 */
function filebinary($filename, $markings = [], $lang = false) {
	if (!$filename) {
		echo '<p>Please choose a filename! / Bitte wählen Sie einen Dateinamen aus!</p>';
		return false;
	}
	$content = file_get_contents($filename);
	if (!$content) return false;
	
	$field_names = $lang ? swtparser_get_field_names($lang) : [];

	if ($markings) {
		foreach ($markings as $data) {
			$bin_markings[$data['begin']] = $data;
		}
		asort($bin_markings);
	}

	$stop = -1;
	$class = [];
	$class['byte'] = '';
	$class['char'] = '';
	$title = '';
	$areas = ['char', 'byte'];

	$len = strlen($content);
	for ($pos = 0; $pos < $len; $pos++) {
		$byte = strtoupper(bin2hex($content[$pos]));
		if (strlen($byte) > 2) $byte = '0'.$byte;
		$char = chr(hexdec($byte));
		$line = floor($pos/16);

		$class_last['byte'] = $class['byte'] ? $class['byte'] : '';
		$class_last['char'] = $class['char'] ? $class['char'] : '';
		$title_last = $title ? $title : '';

		$class['byte'] = '';
		$class['char'] = '';
		$title = '';

		// don't try to show unprintable characters
		if ($byte === '00') {
			$class['byte'] = 'nullbyte';
			$class['char'] = 'nullbyte';
			$char = '.';
		} elseif (hexdec($byte) < 32) {
			$char = '.';
		} elseif ($byte === '7F') {
			$char = '.';
		} elseif ($byte === '20') {
			$char = '&nbsp;';
		}

		// mark binary data we have info about
		if (in_array($pos, array_keys($bin_markings))) {
			$class['byte'] = $bin_markings[$pos]['type'];
			$title = $bin_markings[$pos]['content'];
			if ($field_names) {
				$title = $title.' '.$field_names[$title];
			}
			// show until stop mark
			$stop = $bin_markings[$pos]['end'];
		} elseif ($stop >= $pos) {
			$class['byte'] = $class_last['byte'];
			$title = $title_last;
		}
		if ($pos === $stop) {
			$stop = -1;
		}

		// output classes
		if (floor(($pos - 1)/16) < $line) {
			// beginning of line
			if ($class['char']) $char = '<em class="'.$class['char'].'">'.$char;
			if ($class['byte']) $byte = '<em class="'.$class['byte'].'"'
				.($title ? ' title="'.$title.'"' : '').'>'.$byte;
		} else {
			// middle or end of line
			foreach ($areas as $area) {
				if ($class[$area]) {
					if (!$class_last[$area]) {
						// there's no previous class
						$$area = '<em class="'.$class[$area]
							.(($area == 'byte' AND $title) ? '" title="'.$title : '')
							.'">'.$$area;
					} elseif ($class_last[$area] !== $class[$area]
						OR ($area === 'byte' AND $title_last != $title)) {
						// previous class is different
						$$area = '</em><em class="'.$class[$area]
							.(($area == 'byte' AND $title) ? '" title="'.$title : '')
							.'">'.$$area;
					}
				} elseif ($class_last[$area]) {
					$$area = '</em>'.$$area;
				}
			}
		}
		if (($stop AND floor(($pos + 1)/16) > $line)
			OR $pos === count($content)-1) {
			// end of line
			if ($class['char']) $char .= '</em>';
			if ($class['byte']) $byte .= '</em>';
		}

		if (empty($lines[$line]['chars'])) {
			$lines[$line]['chars'] = '';
			$lines[$line]['bytes'] = '';
		}

		$lines[$line]['chars'] .= $char;
		$lines[$line]['bytes'] .= $byte.' ';
	}
	$output = '<pre class="code">';
	$tpl = "<span class='head'>%s0:</span>&nbsp; %s&nbsp; %s \n";
	foreach ($lines as $pos => $values) {
		$pos = strtoupper(dechex($pos));
		$output .= sprintf($tpl, $pos, $values['bytes'], $values['chars']);
	}
	$output .= '</pre>';
	return $output;
}
