<?php 
    
/**
 * SWT parser: Parsing binary Swiss Chess Tournament files
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/swtparser
 *
 * @author Falco Nogatz, fnogatz@gmail.com
 * @author Gustaf Mossakowski, gustaf@koenige.org
 * @author Jacob Roggon
 * @copyright Copyright © 2012, 2016 Falco Nogatz
 * @copyright Copyright © 2005, 2012-2014 Gustaf Mossakowski
 * @copyright Copyright © 2005 Jacob Roggon
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

/*

SWT parser: parsing binary Swiss Chess Tournament files
Copyright (C) 2005, 2012 Gustaf Mossakowski, Jacob Roggon, Falco Nogatz

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

// required files
require_once 'fileparsing.php';

/**
 * Parses an SWT file from SwissChess and returns data in an array
 * Parst ein SWT file aus SwissChess und gibt Daten in Liste zurück
 *
 * @param string $filename
 * @return array
 *		array 'out' data for further processing
 *		array 'bin' data for marking up binary output
 */
function swtparser($filename) {
	if (!$filename) {
		echo '<p>Please choose a filename! / Bitte wählen Sie einen Dateinamen aus!</p>';
		return false;
	}
	$contents = file_get_contents($filename);
	if (!$contents) return false;

	define('START_FILEVERSION', 261);
	define('END_FILEVERSION', 262);
	define('FILEVERSION', hexdec(bin2hex(strrev(zzparse_binpos($contents, START_FILEVERSION, END_FILEVERSION)))));

	// read common tournament data
	// Allgemeine Turnierdaten auslesen
	$tournament = zzparse_interpret($contents, 'general');
	$structure = swtparser_get_structure();

	// common data lengths
	// Allgemeine Datenlängen
	define('LEN_PAARUNG', $structure['length:pairing']);
	define('START_PARSING', $structure['start:fixtures_players']);
	define('LEN_SPIELER_KARTEI', $structure['length:player']);
	define('LEN_MANNSCHAFT_KARTEI', $structure['length:team']);

	// index card for teams
	//	Karteikarten Mannschaften
	if ($tournament['out'][35]) {
		list($tournament['out']['Teams'], $bin) = swtparser_records($contents, $tournament['out'], 'Teams');
		$tournament['bin'] = array_merge($tournament['bin'], $bin);
	}

	// index card for players
	//	Karteikarten Spieler
	list($tournament['out']['Spieler'], $bin) = swtparser_records($contents, $tournament['out'], 'Spieler');
	$tournament['bin'] = array_merge($tournament['bin'], $bin);

	// team fixtures, at least one round has to be fixed
	//	Mannschaftspaarungen
	if ($tournament['out'][35] AND $tournament['out'][3]) {
		list($tournament['out']['Mannschaftspaarungen'], $bin) = swtparser_fixtures($contents, $tournament['out'], 'Teams');
		$tournament['bin'] = array_merge($tournament['bin'], $bin);
	} else {
		$tournament['out']['Mannschaftspaarungen'] = array();
	}

	// player fixtures, at least one round has to be fixed
	//	Einzelpaarungen
	if ($tournament['out'][3]) {
		list($tournament['out']['Einzelpaarungen'], $bin) = swtparser_fixtures($contents, $tournament['out'], 'Spieler');
		$tournament['bin'] = array_merge($tournament['bin'], $bin);
	} else {
		$tournament['out']['Einzelpaarungen'] = array();
	}
	return $tournament;
}

/**
 * Gets the structure information as key-value-pairs
 *
 * @return array
 */
function swtparser_get_structure() {
	$filename = zzparse_get_filename('structure.csv');

	$array = array();
	$rows = file($filename);
	for ($i = 0; $i < count($rows); $i++) {
		$row = str_getcsv($rows[$i], "\t");
		if (preg_match('/^\w/', $row[0])) $array[$row[0]] = $row[1];
	}

	return $array;
}

/**
 * Parses record cards for single players and teams
 *
 * @param array $contents
 * @param array $tournament
 * @param string $type ('Spieler', 'Teams')
 * @return array
 */
function swtparser_records($contents, $tournament, $type = 'Spieler') {
	if ($tournament[3]) {
		// there is at least one round fixed
		if ($tournament[35]) {
			$startval = (START_PARSING 
				+ ($tournament[4] * $tournament[1] * $tournament[33] * LEN_PAARUNG)
				+ ($tournament[80] * $tournament[1] * $tournament[33] * LEN_PAARUNG));
		} else {
			$startval = (START_PARSING 
				+ ($tournament[4] * $tournament[1] * $tournament[33] * LEN_PAARUNG));
		}
	} else {
		$startval = START_PARSING;
	}
	
	switch ($type) {
	case 'Spieler':
		$maxval = $tournament[4];
		$structfile = 'player';
		$len_kartei = LEN_SPIELER_KARTEI;
		break;
	case 'Teams':
		$startval = ($startval + $tournament[4] * LEN_SPIELER_KARTEI);
		$maxval = $tournament[80];
		$structfile = 'team';
		$len_kartei = LEN_MANNSCHAFT_KARTEI;
		break;
	}

	$records = array();
	$bin = array();
	for ($i = 0; $i < $maxval; $i++) {
		$data = zzparse_interpret($contents, $structfile, $startval + $i * $len_kartei, $len_kartei);
		$bin = array_merge($bin, $data['bin']);
		if ($type === 'Teams') {
			$records[$data['out'][1018]] = $data['out'];
		} else {
			$records[$data['out'][2020]] = $data['out'];
		}
	}
	return array($records, $bin);
}

/**
 * Parses fixtures for single players and teams
 *
 * @param array $contents
 * @param array $tournament
 * @param string $type ('Spieler', 'Teams')
 * @return array [player ID][round] = data
 */
function swtparser_fixtures($contents, $tournament, $type = 'Spieler') {
	$fixtures = array();
	$runde = 1;
	$ids = array_keys($tournament[$type]);
	$index = -1;
	$startval = START_PARSING;
	
	switch ($type) {
	case 'Spieler':
		$max_i = $tournament[1] * $tournament[33] * $tournament[4];
		$structfile = 'individual-pairings';
		$name_field = 2000;
		$opponent_field = 4001;
		break;
	case 'Teams':
		$startval += $tournament[1] * $tournament[33] * $tournament[4] * LEN_PAARUNG;
		$max_i = $tournament[1] * $tournament[33] * $tournament[80];
		$structfile = 'team-pairings';
		$name_field = 1000;
		$opponent_field = 3002;
		break;
	}
	
	$bin = array();
	for ($i = 0; $i < $max_i; $i++) {
		// Teams, starting with index 0
		// Mannschaften, beginnend mit Index 0
		if ($runde == 1) $index++;
		if (!isset($ids[$index])) continue;
		$id = $ids[$index];
		$pos = $startval + $i * LEN_PAARUNG;
		$data = zzparse_interpret($contents, $structfile, $pos, LEN_PAARUNG);
		$bin = array_merge($bin, $data['bin']);
		if (isset($tournament[$type][$data['out'][$opponent_field]])) {
			$data['out']['Gegner_lang'] = $tournament[$type][$data['out'][$opponent_field]][$name_field];
		} elseif ($data['out'][$opponent_field] !== '00') {
			$data['out']['Gegner_lang'] = 'UNKNOWN '.$data['out'][$opponent_field];
		} else {
			$data['out']['Gegner_lang'] = '';
		}
		$fixtures[$id][$runde] = $data['out'];
		// increment round, when reaching maximum rounds, start over again
		// Runde einen erhoehen, nach max. Rundenzahl wieder von vorne beginnen
		if ($runde == $tournament[1]) $runde = 1; 
		else $runde++;
	}
	return array($fixtures, $bin);
}

/**
 * Gets a list of field names by a given language
 * Erzeugt eine Liste von Feldbezeichnern zu einer gegebenen Sprache
 *
 * @param array $language (two-letter language code)
 * @return array field names
 */
function swtparser_get_field_names($language) {
	$field_names = array();
	$rows = file(__DIR__.'/definitions/field-names/'.$language.'.csv');
	for ($i = 0; $i < count($rows); $i++) {
		$row = str_getcsv($rows[$i], "\t");
		if (preg_match('/^\d/', $row[0])) {
			$field_names[$row[0]] = $row[1];
		}
	}
	return $field_names;
}
