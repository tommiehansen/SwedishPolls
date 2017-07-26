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
$dbName = 'wikipedia.sqlite';
$db = DATA_DIR . $dbName;
$wiki->createDatabase($db, $table); // returns db handle @ $wiki->db for later use


# get data
$isCli ? $color->header('Fetching and parsing data...') : '';
$file = $config->cache_dir . 'wiki.cache';
$url = "https://en.wikipedia.org/w/api.php?action=parse&format=json&page=Opinion_polling_for_the_Swedish_general_election,_2018&section=3";
$data = curl_cache($url, $file, $config->cache );



# parse
$arr = $wiki->parse($data);


# change sort order
$arr = $wiki->sortParse($arr);

$isCli ? $color->done() : '';


# SQLite insert
#$wiki->writeSQLite($arr);




/* database operations */
$isCli ? $color->header("Writing to '". DATA_DIR ."$dbName'") : '';

$fields = $wiki->fields;

// create insert string
$inserts = "INSERT OR IGNORE INTO $table(";
foreach( $fields as $field => $val ){ $inserts .= "`$field`,"; }
$inserts .= ') VALUES (';
foreach( $fields as $field ){ $inserts .= "?,"; }
$inserts .= ');';
$inserts = str_replace(',)',')', $inserts);


// insert to db
$db = $wiki->db;
$db->beginTransaction();
	foreach($arr as $key => $val ){
		$sql = $db->prepare($inserts);
		$sql->execute($val);
	}

	$db->exec("VACUUM");
$db->commit();


$isCli ? $color->done() : '';




/* create csv */
$selectFields = $fields;
unset($selectFields['id']);
$str = '';
foreach($selectFields as $key => $val ){
	$str .= $key . ',';
}
$selectFields = rtrim($str, ',');

$sql = "
	SELECT $selectFields FROM $table
	ORDER BY collectPeriodTo DESC, Company DESC
";

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
	
	#prp($val);
}

// create the CSV
$arrCSV = arrCSV($data);

// add headers
$arrCSV = $headers . PHP_EOL . $arrCSV;

// write
file_put_contents(DATA_DIR . 'Wikipedia.csv', $arrCSV);


if( !$isCli ){
	echo 'Things written.';
}