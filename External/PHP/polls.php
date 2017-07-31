<?php
/**
 *  Get Polls.csv + standardize format + add to database
 *  1. Get data
 *  2. Apply fixes
 *  3. Add data to database
 *  4. Check if there was new data or not and write/don't write if
 */
 
require 'core/config.php'; // $config object
require 'core/helpers.php';
require 'core/class.common.php';
require 'core/class.cli.colors.php';
$colors = new Cli\Colors;


# setup
$db = 'Polls.sqlite';
$dbName = $db;
$dbNameNew = $dbName . '.new';
$table = 'polls';
$oldCheck = 500; // number of new-vs-old to check for if difference


# init common
$common = new Polls\Common;


# check if old db exists
file_exists(DATA_DIR . $dbName) ? $hasOld = true : $hasOld = false;


# check if terminal
$isCli = isCli();

if( !$isCli ){
	
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
	echo "
		<title># ". basename(__FILE__, '.php') ."</title>
		<style>* { font-family: monospace; }</style>
	";
	
}




/*
	GET DATA
*/

# get CSV
$url = 'https://raw.githubusercontent.com/MansMeg/SwedishPolls/master/Data/Polls.csv';
$file = $config->cache_dir . 'polls.cache';
$data = curl_cache($url, $file, $config->cache); // get + cache


# create array from CSV
$pollsArr = csvArr($data);
$pollsHeader = $pollsArr[0]; // save header for later use
unset($pollsArr[0]); // remove header





/*
	APPLY FIXES
	1. Create data-friendly PublYearMonth ie 2017-Jul >> 2017-07
	2. Create faux collectPeriodFrom and collectPeriodTo using PublYearMonth ie NA >> 2017-07-01
	3. Convert all 'NA' to 'null' (for later SQLite insert)
	4. Generate ID
*/

$months = ["jan", "feb", "mar", "apr", "maj", "jun", "jul", "aug", "sep", "okt", "nov", "dec"];

$pollsTemp = [];
foreach( $pollsArr as $i => $arr ){
	
	// CSV can have empty rows, skip those
	if( !isset($arr[2]) ) continue;
	
	// fix publYearMonth (can be wrong)
	$arr[0] =  substr($arr[0],0,8);
	
	// month-name to number
	$date = explode('-', $arr[0]);
	$month = array_search ($date[1], $months);
	$month++; // $months is zero-based index, add 1
	$month < 10 ? $month = '0'. $month : '';
	$arr[0] = $date[0] . '-' . $month;
	
	
	// check if collectPeriod(s) set, else use PublYearMonth + '-01'
	if( $arr[15] === 'NA' ) {
		$arr[15] = $arr[0] . '-01';
		$arr[14] = $arr[15];
	}
	
	// check if PublDate set, else use PublYearMonth + '-01'
	if( $arr[13] === 'NA'){
		$arr[13] = $arr[0] . '-01';
	}
	
	// Convert 'NA' to 'null'
	foreach( $arr as $k => $v ){
		if( $v === 'NA' ){
			$arr[$k] = null;
		}
	}
	
	
	// generate id
	$toDate = $arr[15]; // collectPeriodTo

	$id = $toDate . strtoupper(substr($arr[1], 0, 3)); // <collectPeriodTo><Company:first 3 chars>
	$id = str_replace('.','', $id);
	$id = str_replace('-','', $id);
	
	array_unshift($arr , $id); // add to array
	
	// add to array
	$pollsTemp[] = $arr;
	
}

// replace with fixed array
$pollsArr = $pollsTemp;





/*
	ADD TO SQLITE DATABASE
*/


$fields = [
	'id' => 'TEXT',
	'PublYearMonth' => 'TEXT',
	'Company' => 'TEXT',
	'M' => 'NUMERIC',
	'L' => 'NUMERIC',
	'C' => 'NUMERIC',
	'KD' => 'NUMERIC',
	'S' => 'NUMERIC',
	'V' => 'NUMERIC',
	'MP' => 'NUMERIC',
	'SD' => 'NUMERIC',
	'FI' => 'NUMERIC',
	'Uncertain' => 'NUMERIC',
	'n' => 'NUMERIC',
	'PublDate' => 'TEXT',
	'collectPeriodFrom' => 'NUMERIC',
	'collectPeriodTo' => 'NUMERIC',
	'approxPeriod' => 'TEXT',
	'house' => 'TEXT',
];


# create database (if not exist)
$db = $common->createDatabase( DATA_DIR . $dbNameNew, $table, $fields ); // returns db-handle for later use


# generate inserts for prepped statements
$inserts = $common->generateInserts( $table, $fields );


# loop pollsArr and apply inserts
$db->beginTransaction();
foreach($pollsArr as $key => $arr ){
	
	$sql = $db->prepare($inserts);
	$sql->execute($arr);
	
}
$db->commit();
$db->exec("VACUUM");






/*
	CHECK IF THERE WAS NEW DATA
	Reason for this is that we do not want to commit files if there is no new data...
	Any change to any database is always considered 'changed'
*/


if( $hasOld ){
	
	$newDB = $db;
	$oldDB = new \PDO('sqlite:' . DATA_DIR . $dbName) or die("Error @ db");
	$sql = "SELECT * FROM $table LIMIT $oldCheck";
	
	$newData = $newDB
	->query($sql)
	->fetchAll(PDO::FETCH_ASSOC);
	
	$oldData = $oldDB
	->query($sql)
	->fetchAll(PDO::FETCH_ASSOC);
	
	// check for differences
	$hasDiff = false;
	foreach( $newData as $key => $val ){
		
		if( $val['id'] != $oldData[$key]['id'] ){
			$hasDiff = true;
		}
		
	}
	
	// no difference
	if( !$hasDiff ){
		$colors->row("Polls: No difference from previous, no new data added.\n");
		unlink( DATA_DIR . $dbNameNew ); // remove the new database
	}
	else {
		$colors->row("Polls: New data differs from previous, new data was written...\n");
		unlink( DATA_DIR . $dbName ); // remove primary
		rename( DATA_DIR . $dbNameNew, DATA_DIR . $dbName ); // use new as primary
	}
	
}
// no old data, simply rename db file
else {
	$colors->row("Polls: Data was written.");
	rename( DATA_DIR . $dbNameNew, DATA_DIR . $dbName );
}