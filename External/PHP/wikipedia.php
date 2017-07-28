<?php
/**
 *  Get data from Wikipedia
 *  + Store in SQLite db
 *  + Normalize data
 */

require 'core/config.php'; // $config object
require 'core/helpers.php';
require 'core/class.wikipedia.php';
require 'core/class.cli.colors.php';


# load classes
$wiki = new Polls\Wikipedia;
$color = new Cli\Colors;


# cli or not
isset($argv) ? $isCli = true : $isCli = false;
$isCli ? system('clear') : ''; // clear terminal


# setup database
$table = 'polls';
$dbName = 'Wikipedia.sqlite';
$db = DATA_DIR . $dbName;
$wiki->createDatabase($db, $table); // returns db handle @ $wiki->db for later use


# get data
$isCli ? $color->header('Fetching and parsing data') : '';

$file = $config->cache_dir . 'wikipedia.cache';
$url = "https://en.wikipedia.org/w/api.php?action=parse&format=json&page=Opinion_polling_for_the_Swedish_general_election,_2018&section=3";
$data = curl_cache($url, $file, $config->cache );


# parse
$arr = $wiki->parse($data);


# change sort order
$arr = $wiki->sortParse($arr);

$isCli ? $color->done() : '';


# SQLite insert
$hasChanges = $wiki->writeSQLite($arr, $table); // write and perform check if there's new data


// no change from previous data, don't write if nothing new
if( !$hasChanges ) {
	
	$isCli ? $color->header("No new data to write, quitting...\n") : '';
	if( !$isCli ){ echo 'No new data to write, quitting...'; }
	
	exit();
}

else {
	$isCli ? $color->header("Writing to $db") : '';
	$isCli ? $color->done() : '';
}



/*
	Create CSV
	TODO: make this a general func, some sort of db_data > csv
*/
$csvFile = DATA_DIR . 'Wikipedia.csv';

$isCli ? $color->header("Writing to $csvFile") : '';

$selectFields = $wiki->fields;
unset($selectFields['id']);
$str = '';
foreach($selectFields as $key => $val ){
	$str .= $key . ',';
}
$selectFields = rtrim($str, ',');

$order = $config->order;
$sql = "
	SELECT $selectFields FROM $table
	ORDER BY $order
";

$db = $wiki->db;
$data = $db->query($sql);
$data = $data->fetchAll(PDO::FETCH_ASSOC);

// get headers
$headers = [];
$fields = $wiki->fields;
foreach($fields as $key => $type ){
	$headers[] = $key;	
}

$headers = implode(',', $headers);
$headers = str_replace('id,', '', $headers); // rm id


// rm id's
foreach($data as $key => $val ){
	unset($data[$key]['id']);
}

// create the CSV
$arrCSV = arrCSV($data);

// add headers
$arrCSV = $headers . PHP_EOL . $arrCSV;

// write

file_put_contents($csvFile, $arrCSV);


$isCli ? $color->done() : '';
echo "\n";




if( !$isCli ){
	$dbSrc = DATA_DIR . $dbName;
	echo "
		<style>* { font-family: monospace, monospace; line-height:1.5; } body { padding: 2%; } h3 { margin:0 0 .2rem; } table,td {border:0;border-spacing:0}</style>
	";
	$html = "
		<h3>Stuff written</h3>
		<table>
			<tr>
				<td>SQLite
				<td>$dbSrc
			</tr>
			<tr>
				<td>CSV
				<td>$csvFile
			</tr>
		</table>
	";
	echo $html;

}