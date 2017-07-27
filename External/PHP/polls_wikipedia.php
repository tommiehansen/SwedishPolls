<?php
/**
 *  Merge data from Polls.csv <> Wikipedia
 *  Never touches old data *IF* it exists
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


# write some message
if($isCli){
	
	$out = "\n";
	$out .= $color->out('******************************************', 'light_blue');
	$out .= "\n\n";
	$out .= $color->out('Append data from Wikipedia >> Polls.csv', 'white');
	$out .= "\n";
	$out .= $color->out('Never touches old data *IF* it exists', 'white');
	$out .= "\n\n";
	$out .= $color->out('******************************************', 'light_blue');
	
	echo $out . "\n";
}


# setup database
$table = 'polls';
$dbName = 'Polls_Wikipedia.sqlite';
$dbSrc = DATA_DIR . $dbName;
$csvName = 'Polls_Wikipedia.csv';
$csvFile = DATA_DIR . $csvName;
$db = DATA_DIR . $dbName;
$fields = $wiki->pollFields;

$fields['id'] = 'TEXT UNIQUE';

# create database
$wiki->createDatabase($db, $table, $fields); // returns db handle @ $wiki->db for later use



/*
	GET data from mansmeg/polls
*/

$isCli ? $color->header('Fetching Polls.csv') : '';

$url = 'https://raw.githubusercontent.com/MansMeg/SwedishPolls/master/Data/Polls.csv';
$file = $config->cache_dir . 'polls.cache';
$data = curl_cache($url, $file, $config->cache);

$isCli ? $color->done() : '';
$isCli ? $color->header("Writing to $dbSrc") : '';


# create array
$pollsArr = csvArr($data);
$pollsHeader = $pollsArr[0]; // save header for later use
unset($pollsArr[0]); // remove headers



# generate inserts
$inserts = "INSERT OR IGNORE INTO $table(";
foreach( $fields as $field => $val ){ $inserts .= "`$field`,"; }
$inserts .= ') VALUES (';
foreach( $fields as $field ){ $inserts .= "?,"; }
$inserts .= ');';
$inserts = str_replace(',)',')', $inserts);



# begin db stuff
$db = $wiki->db; // re-use old
$months = ["jan", "feb", "mar", "apr", "maj", "jun", "jul", "aug", "sep", "okt", "nov", "dec"]; // ugly
$db->beginTransaction();

# loop and write
$pollsOld = [];
foreach($pollsArr as $key => $arr ){
	
	if( !isset($arr[2]) ) continue;
	
	// fix publYearMonth
	$arr[0] =  substr($arr[0],0,8);
	
	// month-name to number
	$date = explode('-', $arr[0]);
	$month = array_search ($date[1], $months);
	$month++; // $months is zero-based index, add 1
	$month < 10 ? $month = '0'. $month : '';
	$arr[0] = $date[0] . '-' . $month;
	
	// generate id + prepend to array
	// NOTE: This can cause duplicate data entries; omit all Wikipedia-data later on that somehow clashes
	$year = $date[0];
	$id = $year . strtoupper(substr($arr[1], 0, 3)) . $arr[2] . $arr[3] . $arr[4] . $arr[5] . $arr[6] . $arr[7]; // <YEAR><Company:first 3 chars><M><L><C><KD><S><V>
	$id = str_replace('.','', $id);
	$id = str_replace('-','', $id);
	
	array_unshift($arr , $id); // add to array
	
	
	// loop through, add proper null values for SQLite
	foreach( $arr as $k => $a ){
		if( $a === 'NA' ){
			$arr[$k] = null;
		}	
	}
	
	$pollsOld[] = $arr;
	
	if( isset($arr[1]) ){		
		$sql = $db->prepare($inserts);
		$sql->execute($arr);
	}

}
#$db->commit();

$isCli ? $color->done() : '';



/*
	GET data from Wikipedia
	
	Will be 'NA'/null:
	PublYearMonth
	PublDate
	Uncertain
	n
	
	Will be just 'FALSE':
	approxPeriod
	
	..but only if data doesn't exist @ Polls.csv
	
*/


$isCli ? $color->header('Fetching data from to Wikipedia') : '';

$file = $config->cache_dir . 'wikipedia.cache';
$url = "https://en.wikipedia.org/w/api.php?action=parse&format=json&page=Opinion_polling_for_the_Swedish_general_election,_2018&section=3";
$data = curl_cache($url, $file, $config->cache );

$isCli ? $color->done() : '';

# parse
$wikiArr = $wiki->parse($data);

# change sort order
$wikiArr = $wiki->sortParse($wikiArr);

# re-order array and add columns etc to comply with Polls.csv data
$new = [];
$limit = 10; // limit to last X
foreach( $wikiArr as $key => $val ){
	
	if( $key >= $limit ) continue; // just do this for first X; don't want to mess with old valid Polls.csv data
	
	// skip 'General Election'
	if($key < 3){
		if (strpos($val[1], 'General') !== false) {
			continue;
		}
	}
	
	// generate id 'compatible' with Polls.csv
	// used later on for INSERT OR IGNORE query
	$cur = $wikiArr[$key];
	$id = substr($cur[4], 0, 4); // year
	$id .= substr(strtoupper($cur[1]), 0, 3); // Company: 3 first chars
	$id .= $cur[5] . $cur[6] . $cur[7] . $cur[8] . $cur[9] . $cur[10]; // M-L-C-KD-S-V values
	$id = str_replace(['-','.'],['',''], $id);

	$new[$key]['id'] = $id;
	$new[$key]['PublYearMonth'] = null;
	$new[$key]['Company'] = $cur[1];
	
	foreach($val as $k => $v ){
		
		$k == 5 ? $new[$key]['M'] = $v : '';
		$k == 6 ? $new[$key]['L'] = $v : '';
		$k == 7 ? $new[$key]['C'] = $v : '';
		$k == 8 ? $new[$key]['KD'] = $v : '';
		$k == 9 ? $new[$key]['S'] = $v : '';
		$k == 10 ? $new[$key]['V'] = $v : '';
		$k == 11 ? $new[$key]['MP'] = $v : '';
		$k == 12 ? $new[$key]['SD'] = $v : '';
		$k == 13 ? $new[$key]['FI'] = $v : '';
		
	}
	
	$new[$key]['Uncertain'] = null;
	$new[$key]['n'] = null;
	$new[$key]['PublDate'] = null;
	$new[$key]['collectPeriodFrom'] = $cur[3];
	$new[$key]['collectPeriodTo'] = $cur[4];
	$new[$key]['approxPeriod'] = null;
	$new[$key]['house'] = $cur[1];
	
}

$isCli ? $color->header("Writing to $dbSrc") : '';

// insert to db
foreach($new as $k => $a ){	
	$a = array_values($a);
	$sql = $db->prepare($inserts);
	$sql->execute($a);
}

// commit all
$db->commit();



$isCli ? $color->done() : '';





/*
	GET all data from db
	and turn into CSV
*/


$isCli ? $color->header("Writing to $csvFile") : '';

$sql = "
	SELECT * FROM $table
	ORDER BY collectPeriodTo DESC, house DESC
";

$res = $db->query($sql);
$res = $res->fetchAll(PDO::FETCH_ASSOC);

// make compatible with 'mansmeg/poll'
$header = [];
foreach( $res as $key => $arr ){
	
	foreach( $arr as $k => $v ){
		
		if( $k == 'id' ) continue; // ignore id
		
		if( $k == 'PublYearMonth' ){
			$date = explode('-', $v);
			$year = $date[0];
			$date = $date[1];
			$date = preg_replace("/0/", '', $date, 1);
			$month = $months[$date-1]; // 0 based index; jan = 0 etc
			$res[$key]['PublYearMonth'] = $year . '-' . $month;
			
		}
		
		// add 'NA' values
		if( $v == '' ) $res[$key][$k] = 'NA';
		
		// add header
		if( $key == 0 ){
			$header[] = $k;
		}
		
	}
	
	// remove id
	unset($res[$key]['id']);
	
}

// add header
array_unshift($res, $header);

// convert to CSV
$csv = arrCSV($res);

// ...and write
file_put_contents( $csvFile, $csv );

$isCli ? $color->done() : '';
echo "\n";


if( !$isCli ){
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