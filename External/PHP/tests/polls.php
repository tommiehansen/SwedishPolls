<title># temp</title>
<style>
* { font-family: monospace; } .tbl td, .tbl th { text-align: left; }
.tbl { width: 100%; border-collapse: collapse; margin-bottom: 2rem; } td,th { border: 1px solid #ddd; padding: 5px; } tr:hover td { background: #ffc; }
th { background: #ffd }
</style>
<?php
/**
 *  Get Polls.csv + standardize format + add to database
 *  1. Apply fixes
 *  2. Add data to database
 *  3. Check if there was new data or not and write/don't write if
 */
 
require '../core/config.php'; // $config object
require '../core/helpers.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


# setup
$db = 'Polls.sqlite';
$dbName = $db;
$dbNameNew = $dbName . '.new';
$table = 'polls';
$dir_append = '../'; # tmp for /test/ -folder
$oldCheck = 50; // number of new-vs-old to check for if difference


# get data
$url = 'https://raw.githubusercontent.com/MansMeg/SwedishPolls/master/Data/Polls.csv';
$file = $dir_append . $config->cache_dir . 'polls.cache';
$data = curl_cache($url, $file, $config->cache);


# create array from CSV
$pollsArr = csvArr($data);
$pollsHeader = $pollsArr[0]; // save header for later use
unset($pollsArr[0]); // remove header


# check if old db exists
file_exists($dbName) ? $hasOld = true : $hasOld = false;





/*
	Apply fixes
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
	Add to SQLite database
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
	'collectPeriodFrom' => 'TEXT',
	'collectPeriodTo' => 'TEXT',
	'approxPeriod' => 'TEXT',
	'house' => 'TEXT',
];

$db = "sqlite:" . $dbNameNew;
$db	= new \PDO($db) or die("Error @ db");

$db->beginTransaction();

	# SQLite settings
	$db->exec("PRAGMA synchronous=OFF");
	$db->exec('PRAGMA journal_mode=MEMORY');
	$db->exec('PRAGMA temp_store=MEMORY');
	$db->exec('PRAGMA count_changes=OFF');
	$db->exec("DELETE FROM $table"); // clear table (temp)

	# create all fields
	$sql = "CREATE TABLE IF NOT EXISTS $table (";

	foreach( $fields as $key => $val ){	
		$sql .= " $key $val, ";
	}

	$sql .= " PRIMARY KEY(id) "; // primary
	$sql .= ")"; 

	$db->exec($sql);

$db->commit();




# generate inserts
$inserts = "INSERT OR IGNORE INTO $table(";
foreach( $fields as $field => $val ){ $inserts .= "`$field`,"; }
$inserts .= ') VALUES (';
foreach( $fields as $field ){ $inserts .= "?,"; }
$inserts .= ');';
$inserts = str_replace(',)',')', $inserts);


# loop pollsArr and apply inserts
$db->beginTransaction();
foreach($pollsArr as $key => $arr ){
	
	$sql = $db->prepare($inserts);
	$sql->execute($arr);
	
}
$db->commit();
$db->exec("VACUUM;");






/*
	Check if there was new data
	Reason for this is that we do not want to update files if there is no new data...
*/


if( $hasOld ){
	
	$newDB = $db;
	$oldDB = new \PDO('sqlite:' . $dbName) or die("Error @ db");
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
		echo 'No difference from previous, no new data added.';
		unlink( $dbNameNew ); // remove the new database
	}
	else {
		echo 'New data differs from previous, new data was written...';
		unlink( $dbName ); // remove primary
		rename( $dbNameNew, $dbName ); // use new as primary
	}
	
}