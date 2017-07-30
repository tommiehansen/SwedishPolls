<?php
/**
 *  MERGE Polls <> Wikipedia
 *  Merge data from Polls.csv <> Wikipedia data
 *  1. XXX
 *  2. XXX
 *  3. XXX
 *  4. XXX
 *  
 *  TODO: Clean this up, add to merge.php or invent a bette name ie Polls_Wikipedia.sqlite etc
 *  TODO: Add generic output() that differs from CLI / HTML or something ..
 */
 
require 'core/config.php'; // $config object
require 'core/helpers.php';
require 'core/class.common.php';

$common = new Polls\Common;


# setup stuff
$data_dir = DATA_DIR;
$polls_src = $data_dir . 'Polls.sqlite';
$wiki_src = $data_dir . 'Wikipedia.sqlite';
$dupeStrictness = 'strict'; // 'strict' or 'loose'

$dbName = 'merge.test.sqlite';
$dbNameNew = $dbName . '.new';
$table = 'polls';
$oldCheck = 50; // number of new-vs-old to check for if difference


# check if old db exists
file_exists(DATA_DIR . $dbName) ? $hasOld = true : $hasOld = false;


# test values
$limit = -1; // -1 = all data
#$limit = 30;
$company = ''; // val or blank '' (meaning all companies)


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





$pollsDB = new PDO('sqlite:' . $polls_src) or die("Error @ db");
$wikiDB = new PDO('sqlite:' . $wiki_src) or die("Error @ db");

$company ? $companySQL = "WHERE a.Company = '$company'" : $companySQL = '';

# compare with pollsData
$sql = "		
	SELECT * from polls a
	$companySQL
	ORDER BY a.collectPeriodTo DESC, a.Company DESC
	LIMIT $limit
";
$pollsData = $pollsDB->query($sql);
$pollsData = $pollsData->fetchAll(PDO::FETCH_ASSOC);



echo '<h3>Polls DB</h3>';
sqlTable($pollsData);
prp( count($pollsData) . ' rows<hr>');


# compare with wikidata
$sql = "		
	SELECT * from polls a
	$companySQL
	ORDER BY a.collectPeriodTo DESC, a.Company DESC
	LIMIT $limit
";
$wikiData = $wikiDB->query($sql);
$wikiData = $wikiData->fetchAll(PDO::FETCH_ASSOC);




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



echo '<h3>Wiki DB</h3>';
sqlTable($wikiData);
prp( count($wikiData) . ' rows<hr>');





/*
	1 - Loop polls array and add data from $wikiData array
*/

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




/*
	2 - loop wiki array, add fields if  missing
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




/*
	3 -
	REMOVE DUPLICATES
	
	Strict / Loose
	'Strict' = Use Year + Month + values for M, L, C, KD, S, V
	'Loose' = Use Year + Month + Company (3 first letters)
	
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


# remove uneeded keys
$rmKeys = [ 'key', 'Date' ];
foreach( $pollsArr as $i => $arr ){
	foreach( $rmKeys as $key ){
		unset($pollsArr[$i][$key]);
	}
}



# sort by collectPeriodTo DESC
usort($pollsArr, function($a, $b) {
	$a = str_replace('-','', $a['collectPeriodTo']);
	$b = str_replace('-','', $b['collectPeriodTo']);
    return $b - $a; // sort DESC
});

echo '<h3>Merged</h3>';
sqlTable( $pollsArr );
prp( count($pollsArr) . ' rows');

$merged = $pollsArr;
$pollsArr = null;















/*
	4 - Add to database
*/

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
	'OTH' => 'NUMERIC', // from Wikipedia
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







/*
	5 -
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