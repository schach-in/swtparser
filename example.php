<?php

/**
 * Example usage for SWT parser
 * Beispiel für die Anwendung des SWT-Parsers
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/swtparser
 *
 * @author Gustaf Mossakowski, gustaf@koenige.org
 * @author Jacob Roggon, jacob@koenige.org
 * @copyright Copyright © 2005 Gustaf Mossakowski, Jacob Roggon
 * @copyright Copyright © 2005, 2012 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

?>
<!DOCTYPE html>
<title>Example for SWT Parser / Beispiel für SWT-Parser</title>

<style type="text/css">
body		{ font-family: sans-serif; background: white; }
dt			{ font-weight: bold; }
th			{ text-align: left; padding-right: .75em; vertical-align: top; }

.nullbyte	{ color: #AAA; }
.code		{ font-family: monospace; }
.code th	{ text-align: right; }
.code td	{ padding-right: .75em; }
.code .bin	{ background: #c96b3d; }
.code .asc	{ background: #db9255; }
.code .b2a	{ background: #edc27c; }
.code .int	{ background: #f2dca9; }
.code .boo	{ background: #a8a889; }
.code .sel	{ background: #bbbbbb; }
.code em	{ font-style: normal; }

.data		{ border-collapse: collapse; }
.data thead th
			{ font-size: 80%; border-bottom: 1px solid #999;
			vertical-align: bottom; }
.data td	{ padding: .2em .75em .2em .2em; vertical-align: top; 
			white-space: nowrap; }
.data th	{ padding: .2em; }
.data tr.uneven td
			{ background-color: #DDD; }
.data tr.even td
			{ background-color: #EEE; }

</style>

<h1>Example for SWT Parser / Beispiel für SWT-Parser</h1>

<?php 

$dir = 'data';
$files = swtparser_files($dir);
$filename = '';
if (!empty($_GET['file']) AND in_array($_GET['file'], $files)) {
	$filename = $_GET['file'];
}

if (!$filename) {
	// choose a file from existing files
	// Datei aus den bestehenden Dateien auswählen
	if ($files) {
?>

<p>Please choose from one of the following files: / Bitte wählen Sie eine
der folgenden Dateien aus: </p>
<ul>
<?php

		foreach ($files as $file) {
			echo '<li><a href="example.php?file='.urlencode($file).'">'
				.htmlspecialchars($file).'<a/></li>'."\n";
		}

?>
</ul>
<?php

	} else {

?>
<p>There are no files in the <code><?php echo $dir; ?></code>-directory to parse. /
In dem Verzeichnis <code><?php echo $dir; ?></code> gibt es keine SWT-Dateien zum auswerten.</p>
<?php

	}
} else {

?>

<p>Current file is: <?php echo htmlspecialchars($filename); ?> / 
Die aktuelle Datei ist: <?php echo htmlspecialchars($filename); ?></p>
<p><a href="example.php">Choose a different file / Eine andere Datei auswählen</a></p>

<ul>
<li><a href="example.php?file=<?php echo htmlspecialchars($filename); ?>&amp;view=data">Data view / Datenansicht</a></li>
<li><a href="example.php?file=<?php echo htmlspecialchars($filename); ?>&amp;view=binary">Binary view / Binäransicht</a></li>
</ul>

<?php

}

if (!empty($_GET['view'])) {
	require_once 'swtparser.php';
	$tournament = swtparser($dir.'/'.$filename);

	switch ($_GET['view']) {
	case 'binary':
?>
<h2>Binary view / Binäransicht</h2>
<?php

		require_once 'filebinary.php';
		echo filebinary($dir.'/'.$filename, $tournament['bin']);
		break;

	case 'data':
?>
<h2>Data view / Datenansicht</h2>

<ul>
<li><a href="#teams">Teams</a></li>
<li><a href="#mm-paarungen">Team fixtures / Mannschaftspaarungen</a></li>
<li><a href="#spieler">Players / Spieler</a></li>
<li><a href="#ez-paarungen">Player fixtures / Einzelpaarungen</a></li>
</ul>

<h2>Common information / Allgemeine Information</h2>

<?php

		echo swtparser_out_tabular($tournament['out']);
		
		if ($tournament['out']['Mannschaftsturnier']) {
			echo '<h2 id="teams">Teams</h2>';
			echo swtparser_out_info($tournament['out']['Teams']);
			echo swtparser_out_fixtures($tournament['out']['Mannschaftspaarungen'], 'Mannschaftspaarungen', 'mm-paarungen');
		}
		echo '<h2 id="spieler">Players / Spieler</h2>';
		echo swtparser_out_info($tournament['out']['Spieler']);
		echo swtparser_out_fixtures($tournament['out']['Einzelpaarungen'], 'Einzelpaarungen', 'ez-paarungen');

		break;
	}
}


//
// Example functions / Beispielfunktionen
//


/**
 * List all files ending .SWT in a given directory
 *
 * @param string $dir directory name
 * @return array list of files
 */
function swtparser_files($dir) {
	$files = array();
	$handle = opendir($dir);
	while ($file = readdir($handle)) {
		if (substr($file, 0, 1) === '.') continue;
		if (strtoupper(substr($file, -4)) !== '.SWT') continue; 
		$files[] = $file;
	}
	return $files;
}

/**
 * Shows a list of keys and their values
 * Zeigt eine Liste von Schlüsseln und ihren Werten
 *
 * @param array $tournament (returned array from swtparser())
 * @return string HTML output
 * @see swtparser()
 */
function swtparser_out_tabular($tournament) {
	$output = '<table class="data">';
	$i = 0;
	foreach (array_keys($tournament) as $key) {
		$i++;
		$output .= '<tr class="'.($i & 1 ? 'un' : '').'even"><th>'.$key.'</th><td>';
		if (!is_array($tournament[$key])) {
			$output .= $tournament[$key];
		} else {
			$output .= '(see below / siehe unten)';
		}	
		$output .= '</td></tr>'."\n";
	}
	$output .= '</table>';
	return $output;
}

/**
 * Shows general information about players and teams
 * Zeigt allgemeine Informationen über Spieler und Teams
 *
 * @param array $data (part of returned array from swtparser())
 * @return string HTML output
 * @see swtparser()
 */
function swtparser_out_info($data) {
	if (!$data) return '<p>No data available. / Keine Daten vorhanden.</p>';
	$output = '<table class="data"><thead><th>ID</th>';
	$head = reset($data);
	foreach (array_keys($head) as $th) {
		$output .= '<th><span>'.$th.'</span></th>';
	}
	$output .= '</thead><tbody>';
	$i = 0;
	foreach (array_keys($data) as $id) {
		$i++;
		$output .= '<tr class="'.($i & 1 ? 'un' : '').'even"><th>'.$id.'</th>';
		foreach (array_keys($data[$id]) as $key) {
			$output .= '<td>'.$data[$id][$key].'&nbsp;</td>';
		}
		$output .= '</tr>';
	}
	$output .= '</tbody></table>';
	return $output;
}

/**
 * Shows all fixtures of a tournament
 * Zeigt alle Paarungen eines Turniers
 *
 * @param array $fixtures (part of returned array from swtparser())
 * @param string $title (optional, HTML heading)
 * @param string $id (optional, HTML heading id attribute)
 * @return string HTML output
 */
 function swtparser_out_fixtures($fixtures, $title = 'Paarungen', $id = 'paarungen') {
	if (!$fixtures) return '<p>No data available. / Keine Daten vorhanden.</p>';
	$output = '<h2 id="'.$id.'">'.$title.'</h2>';
	$output .= '<table class="data"><thead><th>Round / Runde</th>';
	$head = reset($fixtures);
	$head = reset($head);
	foreach (array_keys($head) as $th) {
		$output .= '<th>'.$th.'</th>';
	}
	$output .= '</thead>';
	foreach ($fixtures AS $player => $rounds) {
		$i = 0;
		$output .= '<tr><td>'.$player.'</td><td></td></tr>';
		// print first line with keys as head
		foreach ($rounds as $round => $data) {
			$i++;
			$output .= '<tr class="'.($i & 1 ? 'un' : '').'even">';
			$output .= '<th>'.$round.'</th>';
			foreach (array_keys($data) as $key)
				$output .= '<td>'.$data[$key].'</td>';
			$output .= '</tr>';
		}
		$output .= '<tr><td>&nbsp;</td><td></td></tr>';
	}
	$output .= '</table>';
	return $output;
}

?>