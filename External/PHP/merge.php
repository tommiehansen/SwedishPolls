<?php
/**
 *  MERGE Polls <> Wikipedia
 *  Merge data from Polls.csv <> Wikipedia data
 *  1. Get data from databases
 *  2. Loop data from Polls.csv and add data from Wikipedia
 *  3. Loop Wikipedia array, add missing fields from Polls.csv
 *  4. Remove duplicates for the now merged array
 *  5. Add to database
 *  6. Check if there was new data, if -- write
 *  
 *  Strictness
 *  'strict': Use collectPeriodTo (Year + Month) + values for M, L, C, KD, S, V
 *  'half-strict': Use collectPeriodTo (Year) + Company (3 first chars) + values for M, L, C, KD, S, V
 *  'loose': Use collectPeriodTo (Year + Month) + Company (3 first chars)
 *  
 *  Arguments
 *  name ie name=Merged.last20.sqlite
 *  strict ie strict=half-loose (sets half-loose dupe check)
 *  maxmerge ie maxmerge=50	 (limits to max merge of 50 latest from Wikipedia)
 */
 
require 'core/config.php'; // $config object
require 'core/helpers.php';
require 'core/class.common.php';
require 'core/class.cli.colors.php';
$colors = new Cli\Colors;
$common = new Polls\Common;


# output large header
$sub_text = "Merge data from Polls.csv <> Wikipedia
  ---
  Params
  @name Name for database file
  sample: php merge.php name=Merged_last10.sqlite
  
  @strict Strictness for duplicate checking
  'strict': Use Year + Month + values for M, L, C, KD, S, V
  'half-strict': Use Year + Company (3 first chars) + values for M, L, C, KD, S, V
  'loose': Use Year + Month + Company (3 first chars)
  > sample: php merge.php strict=half-loose  
  > default: half-strict
  
  @maxmerge Limits number to merge from Wikipedia
  > sample: php merge.php maxmerge=50
  > default: all
  
  @oldcheck Max entries to compare against
  > sample: php merge.php oldcheck=1000
  > default: 500
  
  Combined params, sample:
  php merge.php name=MyDataBase.sqlite oldcheck=99999 maxmerge=100
  
  Order of params does not matter.";
 

if( isset($argv) ){
	$test = implode('__', $argv);
	if( contains('automaton=true', $test) ){
		$sub_text = explode('---', $sub_text)[0];
		$sub_text = trim($sub_text);
	}
}
 
$colors->large_header(basename(__FILE__), $sub_text);



# setup stuff
$data_dir = DATA_DIR;
$polls_src = $data_dir . 'Polls.sqlite';
$wiki_src = $data_dir . 'Wikipedia.sqlite';
$table = 'polls';


# options => default value
# gets overwritten of params set
$opts = [
	'name' => 'Merged.sqlite',
	'strict' => 'half-strict',
	'maxmerge' => false,
	'oldcheck' => 500
];

# months for conversion
$months = ["jan", "feb", "mar", "apr", "maj", "jun", "jul", "aug", "sep", "okt", "nov", "dec"];



# check if argv set and set opts if
if( isset($argv) ){
	$params = $argv;
	unset($params[0]); // remove first (filename)
	
	$paramString = implode('__', $params);
	
	foreach( $opts as $key => $opt ){
		if( contains($key, $paramString) ){
			
			$pa = explode('__', $paramString);
			foreach($pa as $pk => $pv ){ $pa[$pk] = explode('=', $pv); }
			
			foreach( $pa as $k => $v ){
				if( $key == $v[0] ){
					$opts[$key] = $pa[$k][1];
				}
			}
		}
	}
	
	
	# set vars from opts
	if( !$opts['maxmerge'] ) { $maxMerge = false; }
	else { $maxMerge = $opts['maxmerge']; }
	
	$oldCheck = $opts['oldcheck'];
	
	$dbName = $opts['name'];
	$dbNameNew = $dbName . '.new';
	
	$strict = $opts['strict'];
	if( $strict == 'strict' || $strict == 'half-strict' || $strict == 'loose' ) {} else {
		$colors->error('Strictness has wrong value');
		exit;
	}
	
	$dupeStrictness = $strict;
	
}


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
		<style>* { font-family: monospace; } hr { border:0; border-bottom:1px solid #ccc; margin: 20px 0 21px; }</style>
	";
	
}




/*
	1 - GET DATA FROM databases
*/

$colors->header("Fetching data from '$polls_src' and '$wiki_src'");

$pollsDB = new PDO('sqlite:' . $polls_src) or die("Error @ db");
$wikiDB = new PDO('sqlite:' . $wiki_src) or die("Error @ db");


# compare with pollsData
$order = $config->order;
$sql = "		
	SELECT * from polls a
	ORDER BY $order
";
$pollsData = $pollsDB->query($sql);
$pollsData = $pollsData->fetchAll(PDO::FETCH_ASSOC);



#echo '<h3>Polls DB</h3>';
#sqlTable($pollsData);
#prp( count($pollsData) . ' rows<hr>');


# compare with wikidata
$sql = "		
	SELECT * from polls a
	ORDER BY $order
";

if( $maxMerge ) $sql .= " LIMIT $maxMerge";

$wikiData = $wikiDB->query($sql);
$wikiData = $wikiData->fetchAll(PDO::FETCH_ASSOC);


$colors->done();




# add some test data
function testData($num, $company, $curArray){
	
	while($num--){
		$randFrom = rand(30,50);
		$randTo = rand(50,99);
		$randMonth = '0' . rand(5,9);
		
		$arr = [
			'id' => "2097{$randFrom}{$randTo}" . strtoupper(substr($company, 0, 3)),
			'Company' => $company,
			'Date' => "$randFrom-$randTo *TEST* 2097",
			'M' => rand(50,99),
			'L' => rand(50,99),
			'C' => rand(50,99),
			'KD' => rand(50,99),
			'S' => rand(50,99),
			'V' => rand(50,99),
			'MP' => rand(50,99),
			'SD' => rand(50,99),
			'FI' => rand(50,99),
			'OTH' => rand(50,99),
			'collectPeriodFrom' => "2097-$randMonth-$randFrom",
			'collectPeriodTo' => "2097-$randMonth-$randTo",
		];
		
		array_unshift($curArray, $arr);
	}
	
	return $curArray;
}

#$wikiData = testData(10, $company, $wikiData);



#echo '<h3>Wiki DB</h3>';
#sqlTable($wikiData);
#prp( count($wikiData) . ' rows<hr>');





/*
	2 - Loop polls array and add data from $wikiData array
*/

$colors->header("Merging data...");

$pollsArr = [];


foreach( $pollsData as $i => $arr ){
	
	$id = $arr['id'];
	
	# check if id exist in wikiArr
	# and write stuff if true
	foreach( $wikiData as $wikiArr ){
		
		if( $id === $wikiArr['id'] ) {
			
			foreach($wikiArr as $k => $v ){
				
				// only write if data from pollsArr[$KEY] is empty
				if( !isset($arr[$k]) || $arr[$k] == '' ){
					$arr[$k] = $v;
				}
				
			}
		}
		
		// col doesn't exist at all
		else {
			foreach($wikiArr as $k => $v ){
				if( !isset($arr[$k]) ) {
					$arr[$k] = false;
				}
			}
		}
		
	} // foreach( $wikiData )
	
	foreach($arr as $k => $v ){
		$pollsArr[$i][$k] = $v;
	}	
	
}

$pollsData = null; // clear




/*
	3 - Loop Wikipedia array
	- Remove keys that already exists in Polls.csv
	- Add missing data that exists in Wikipedia data
*/


# remove keys from Wikidata that exists in $pollsArr
foreach( $wikiData as $i => $arr ){
	
	$id = $arr['id'];
	
	# check if id exist in pollsArr
	foreach( $pollsArr as $pArr ){
		
		// id exists in pollsArr, remove from wikiData
		if( $id === $pArr['id'] ) {
			unset( $wikiData[$i] );
			continue;
		}
		
	}
	
}

# add missing data values to $pollsArr (ie 'OTH')
foreach( $wikiData as $i => $arr ){
	
	// add missing keys to wikiData with 'null' data
	foreach( $pollsArr as $pArr ){
		foreach($pArr as $k => $v ){
			if( !isset($arr[$k]) ){
				$arr[$k] = null;
			}
		}
	}
	
	// use Company for key 'house'
	$arr['house'] = $arr['Company'];
	
	// add to pollsArr
	$pollsArr[] = $arr;
	
}


$colors->done();




/*
	4 - REMOVE DUPLICATES
	
	Strict / Loose
	'strict' = Use Year + Month + values for M, L, C, KD, S, V
	'half-strict' = Use Year + Company (3 first chars) + values for M, L, C, KD, S, V
	'loose' = Use Year + Month + Company (3 first letters)
	
	NOTE:
	We're using Year + Month due to the fact that
	value for DAY can differ quite a lot between Polls.csv
	and data from Wikipedia (which would make everything not duplicate)
	
	If using 'loose' only Year + Month + Company is used
	causing multiple polls in one month from the same Company
	to be considered duplicate. Therefor there is a specific check
	for month of september (09) when using 'loose'. Do note
	that other months where 'collectPeriodTo' is in the same month
	will be considered dupe and thus be removed.
	
	Example of entry that will be removed with 'loose':
	Company 'Inizio' with 'collectPeriodTo' 2016-06-29
	
	This since the same company has 2x collectPeriodTo within
	that same specific month.
*/

$colors->header("Removing duplicates using strictness '$dupeStrictness'");

# create key for comparison
foreach( $pollsArr as $i => $arr ){
	
	$key = '';
	
	// year + month
	$yearMonth = substr( $arr['collectPeriodTo'], 0, 7 );
	$key .= $yearMonth;
	
	if( $dupeStrictness === 'loose' ) {
		$key .= substr( strtoupper($arr['Company']), 0, 3 );
		
		# month of september cannot use 'loose' since there are always multiple polls from each company that month
		$month = explode('-', $yearMonth)[1];
		if( $month === '09' ){
			$key .= $arr['M'] . $arr['S']; // ..so add values for M and S
		}
		
	}
	if( $dupeStrictness === 'half-strict' ) {
		$key = substr( $arr['collectPeriodTo'], 0, 4 );
		$key .= substr( strtoupper($arr['Company']), 0, 3 );
		$key .= $arr['M'] . $arr['L'] . $arr['C'] . $arr['KD'] . $arr['S'] . $arr['V'];
	}
	else {
		$key .= $arr['M'] . $arr['L'] . $arr['C'] . $arr['KD'] . $arr['S'] . $arr['V'];
	}
	
	# filter
	$key = str_replace(['-','.'], ['',''], $key);
	
	$pollsArr[$i]['key'] = $key;
	
}

# Remove dupes
# note: since wikiData is added last in array, data from pollsData is used if there's a duplicate
function array_key_unique($arr, $key) {
    $uniquekeys = [];
    $output = [];
	
    foreach ($arr as $item) {
        if (!in_array($item[$key], $uniquekeys)) {
            $uniquekeys[] = $item[$key];
            $output[]     = $item;
        }
    }
    return $output;
}

$pollsArr = array_key_unique( $pollsArr, 'key' );

$colors->done();


$colors->header("Remove uneeded keys, fix null values, fix Year-month and sort array");

# remove uneeded keys
$rmKeys = [ 'key', 'Date' ];
foreach( $pollsArr as $i => $arr ){
	foreach( $rmKeys as $key ){
		unset($pollsArr[$i][$key]);
	}
}


# loop through everything again and add proper 'null' values
# and convert ie 2017-07 >> 2017-jul

foreach( $pollsArr as $i => $arr ){
	
	foreach( $arr as $k => $v ){
		
		if( $v == '' ) $arr[$k] = null;
		if( $k == 'PublYearMonth' && $v != '' ){
			$yearMonth = explode('-', $v);
			$month = $yearMonth[1];
			$month = $months[$month-1]; // zero-based index
			$arr[$k] = $yearMonth[0] . '-' . $month;
		}
		
	}
	
	# add back
	$pollsArr[$i] = $arr;
}



# sort by collectPeriodTo DESC
usort($pollsArr, function($a, $b) {
	$a = str_replace('-','', $a['collectPeriodTo']);
	$b = str_replace('-','', $b['collectPeriodTo']);
    return $b - $a; // sort DESC
});

#echo '<h3>Merged</h3>';
#sqlTable( $pollsArr );
#prp( count($pollsArr) . ' rows');

$merged = $pollsArr;
$pollsArr = null;


$colors->done();





/*
	5 - Add to database
*/

$colors->header("Writing to temporary database '$dbNameNew'");

# specify fields for sort + write
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
	#'OTH' => 'NUMERIC', // from Wikipedia, not needed -- see: https://github.com/MansMeg/SwedishPolls/issues/90
	'Uncertain' => 'NUMERIC',
	'n' => 'NUMERIC',
	'PublDate' => 'TEXT',
	'collectPeriodFrom' => 'NUMERIC',
	'collectPeriodTo' => 'NUMERIC',
	'approxPeriod' => 'TEXT',
	'house' => 'TEXT',
];


# sort arrays according to $fields
$tmp = [];
foreach($merged as $i => $arr){
	foreach( $fields as $key => $field ){
		$tmp[$i][$key] = $merged[$i][$key];
	}
}

$merged = $tmp;
$tmp = null;


# create array index
foreach( $merged as $i => $arr ){
	$merged[$i] = array_values($arr);
}


# create database (if not exist)
$db = $common->createDatabase( DATA_DIR . $dbNameNew, $table, $fields ); // returns db-handle for later use


# generate inserts for prepped statements
$inserts = $common->generateInserts( $table, $fields );


# loop pollsArr and apply inserts
$db->beginTransaction();
foreach($merged as $key => $arr ){
	
	$sql = $db->prepare($inserts);
	$sql->execute($arr);

}
$db->commit();
$db->exec("VACUUM");


$colors->done();







/*
	6 - CHECK IF THERE WAS NEW DATA
	Reason for this is that we do not want to commit files if there is no new data...
	Any change to any database is always considered 'changed'
*/

$colors->header("Comparing $dbNameNew <> $dbName");


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
		
		if( isset($oldData[$key]) ){
			if( $val['id'] != $oldData[$key]['id'] ){
				$hasDiff = true;
			}
		}
		
	}
	
	// no difference
	if( !$hasDiff ){
		$txt = "First $oldCheck entries does not differ, no new data added.";
		echo $colors->out("$txt \n", 'yellow');
		echo $colors->out("> TIP: Force new data by removing file '". DATA_DIR ."$dbName' \n");
		unlink( DATA_DIR . $dbNameNew ); // remove the new database
		echo $colors->out("Removed temporary '". DATA_DIR . "$dbNameNew' \n");
		$colors->done();
	}
	else {
		echo $colors->out("New data differs from previous, new data was written.\n");
		unlink( DATA_DIR . $dbName ); // remove primary
		rename( DATA_DIR . $dbNameNew, DATA_DIR . $dbName ); // use new as primary
		echo $colors->out("Replaced $dbName with $dbNameNew in '". DATA_DIR ."' \n");
		$colors->done();
	}
	
}
// no old data, simply rename db file
else {
	echo $colors->out("No old database, creating and writing. \n");
	echo $colors->out("Data written to '". DATA_DIR ."$dbName' \n");
	rename( DATA_DIR . $dbNameNew, DATA_DIR . $dbName );
	$colors->done();
}

echo "\n";