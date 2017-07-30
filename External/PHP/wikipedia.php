<?php
/**
 *  Get data from Wikipedia + add to database
 *  1. Get data using Wikipedia API
 *  2. Parse and apply fixes
 *  3. Sort
 *  4. Add data to database
 *  5. Check if there was new data or not and write/don't write if
 */
 
require 'core/config.php'; // $config object
require 'core/helpers.php';
require 'core/class.common.php';


# setup
$db = 'Wikipedia.sqlite';
$dbName = $db;
$dbNameNew = $dbName . '.new';
$table = 'polls';
$oldCheck = 50; // number of new-vs-old to check for if difference


# check if old db exists
file_exists(DATA_DIR . $dbName) ? $hasOld = true : $hasOld = false;


# fields for sort + database
$fields = [
	'id' => 'TEXT',
	'Company' => 'TEXT',
	'Date' => 'TEXT',
	'M' => 'NUMERIC',
	'L' => 'NUMERIC',
	'C' => 'NUMERIC',
	'KD' => 'NUMERIC',
	'S' => 'NUMERIC',
	'V' => 'NUMERIC',
	'MP' => 'NUMERIC',
	'SD' => 'NUMERIC',
	'FI' => 'NUMERIC',
	'OTH'  => 'NUMERIC',
	'collectPeriodFrom' => 'NUMERIC',
	'collectPeriodTo' => 'NUMERIC',
];


# init common
$common = new Polls\Common;


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
	From Wikipedia API
*/

# get JSON
$url_base = "https://en.wikipedia.org/w/api.php?action=parse&format=json&page=";
$page = "Opinion_polling_for_the_Swedish_general_election,_2018&section=3";
$file = $config->cache_dir . 'wikipedia.cache';
$data = curl_cache($url_base.$page, $file, $config->cache );




/*
	PARSE
	Uses DOM-document since Wikipedia API
	returns an object with HTML inside ...
*/

$data = json_decode($data, true);
$data = $data['parse']['text']['*'];
$data = strip_tags($data, '<table><thead><tfoot><tbody><td><th><tr>');
$data = utf8_decode($data);

// load DOM
$dom = new \DOMDocument();
$dom->loadHTML($data);
$rows = $dom->getElementsByTagName('tr');

// loop rows
$arr = [];
$len = $rows->length;
for ($i = 0; $i < $len; $i++) {
	
	$cols = $rows->item($i)->getElementsbyTagName("td");
	$jlen = $cols->length;
	
	// checks
	if( !isset($cols[1]) || !isset($cols[0])) continue; // skip bad data
	if (strpos($cols[1]->nodeValue, 'General') !== false) continue; // skip 'General Election'
	
	// loop columns
	for ($j = 0; $j < $jlen; $j++) {
		
		$val = $cols->item($j)->nodeValue;
		
		# Normalize 'SKOP'
		$j == 1 && $val == 'SKOP' ? $val = 'Skop' : '';
		
		# check / add null values
		$val == '' ? $val = null : '';
		
		# get all values
		$j == 0 ? $arr[$i]['Date'] = $val : '';
		$j == 1 ? $arr[$i]['Company'] = $val : '';
		$j == 2 ? $arr[$i]['S'] = $val : '';
		$j == 3 ? $arr[$i]['M'] = $val : '';
		$j == 4 ? $arr[$i]['SD'] = $val : '';
		$j == 5 ? $arr[$i]['MP'] = $val : '';
		$j == 6 ? $arr[$i]['C'] = $val : '';
		$j == 7 ? $arr[$i]['V'] = $val : '';
		$j == 8 ? $arr[$i]['L'] = $val : '';
		$j == 9 ? $arr[$i]['KD'] = $val : '';
		$j == 10 ? $arr[$i]['FI'] = $val : '';
		$j == 11 ? $arr[$i]['OTH'] = $val : '';

		
		# fix poor UTF-8 encoding causing '?'
		# and normalize dates
		if( $j == 0  ) {
			
			$val = str_replace('?','-', $val);
			$arr[$i]['Date'] = $val;
			
			$dates = explode('-', $val);
			
			# fix bad dates ie only 'Jul 2016' (YouGov)
			# set to '15' to just makes this be an average date
			if( count($dates) < 2 ){
				$dates[1] = '15 ' . $dates[0];
				$dates[0] = '15 ' . $dates[0];
			}
			
			$toDate = strtotime($dates[1]);
			$toYMD = date('Y-m-d', $toDate);
			
			// normalize date
			// dates that have < 15 share month name ie 15-17 Jul 2017 >> 15 Jul-17 Jul 2017
			if( strlen($val) < 16 ){

				// normalize Date
				$fromDate = substr($dates[1], -9);
				$fromDate = $dates[0] . ' ' .$fromDate;
				
				// create YMD
				$toArr = explode('-', $toYMD);
				$toMonth = $toArr[1];
				$toYear = $toArr[2];
				$fromYMD = date('Y-m-d', strtotime($fromDate));
				
				// even shorter, $dates[0] will be fudged
				if( strlen($val) < 9 ){
					$dates[0] = $dates[1];
					$fromYMD = $toYMD;
				}
				
				
			}
			else {
				// normalize Date
				$toYear = substr($dates[1], -4);
				
				// create YMD
				$fromYMD = date('Y-m-d', strtotime($dates[0].' ' .$toYear));
			}			
			
			
		} // $j == 0
		
		// set collection periods
		$arr[$i]['collectPeriodFrom'] = $fromYMD;
		$arr[$i]['collectPeriodTo'] = $toYMD;
		
	}
	
} // for()
	



/*
	SORT
	Make sort be more like Polls.csv (ie parties order)
*/

# apply new sort order + generate id
$sort = [];

foreach( $arr as $i => $val ){
			
	$cur = $arr[$i];
	
	# generate id : <collectPeriod><company: 3 first>
	$dateTo = $cur['collectPeriodTo'];
	$company = strtoupper(substr($cur['Company'], 0, 3));
	$id = $dateTo . $company;
	$id = str_replace(['-','.'],['',''], $id);
	$arr[$i]['id'] = $id;
	
	# use fields to set order
	foreach( $fields as $key => $type ){	
		$sort[$i][$key] = $arr[$i][$key];
	}
	
}

# replace $arr array
$arr = $sort;




/*
	ADD TO SQLITE DATABASE
*/


# convert keys in array to numbers
foreach( $arr as $i => $wikiArr ){
	$arr[$i] = array_values($wikiArr);
}


# create database (if not exist)
$db = $common->createDatabase( DATA_DIR . $dbNameNew, $table, $fields ); // returns db-handle for later use


# generate inserts for prepped statements
$inserts = $common->generateInserts( $table, $fields );


# loop pollsArr and apply inserts
$db->beginTransaction();
foreach($arr as $k => $a ){
	$sql = $db->prepare($inserts);
	$sql->execute($a);
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
		echo "No difference from previous, no new data added.\n";
		unlink( DATA_DIR . $dbNameNew ); // remove the new database
	}
	else {
		echo "New data differs from previous, new data was written...\n";
		unlink( DATA_DIR . $dbName ); // remove primary
		rename( DATA_DIR . $dbNameNew, DATA_DIR . $dbName ); // use new as primary
	}
	
}

// no old data, simply rename db file
else {
	echo "Data was written.\n";
	rename( DATA_DIR . $dbNameNew, DATA_DIR . $dbName );
}