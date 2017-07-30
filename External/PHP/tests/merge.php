<title># merge</title>
<style>
* { font-family: monospace; } .tbl td, .tbl th { text-align: left; }
.tbl { width: 100%; border-collapse: collapse; margin-bottom: 2rem; } td,th { border: 1px solid #ddd; padding: 5px; } tr:hover td { background: #ffc; }
th { background: #ffd }
</style>
<?php
/**
 *  MERGE
 *  Goal is to merge data from Polls.csv >> Wikipedia
 *  TODO: Add 'strict' and 'normal' rules for dupe-check
 */
 
require '../core/config.php'; // $config object
require '../core/helpers.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

#prp( date('Y-m-d / G:i') );


# setup stuff
$data_dir = '../' . DATA_DIR;
$polls_src = $data_dir . 'Polls_Wikipedia.sqlite';
$wiki_src = $data_dir . 'Wikipedia.sqlite';
#$wiki_src = 'merge.test.sqlite';

$limit = -1;
$company = 'Inizio';


$pollsDB = new PDO('sqlite:' . $polls_src) or die("Error @ db");
$wikiDB = new PDO('sqlite:' . $wiki_src) or die("Error @ db");

# compare with pollsData
$sql = "		
	SELECT * from polls a
	-- WHERE a.Company = '$company'
	ORDER BY a.collectPeriodTo DESC, a.Company DESC
	LIMIT $limit
";
$pollsData = $pollsDB->query($sql);
$pollsData = $pollsData->fetchAll(PDO::FETCH_ASSOC);



#echo '<h3>Polls DB</h3>';
#sqlTable($pollsData);


# compare with wikidata
$sql = "		
	SELECT * from polls a
	-- WHERE a.Company = '$company'
	ORDER BY a.collectPeriodTo DESC, a.Company DESC
	LIMIT $limit
";
$wikiData = $wikiDB->query($sql);
$wikiData = $wikiData->fetchAll(PDO::FETCH_ASSOC);


#echo '<h3>Wiki DB</h3>';
#sqlTable($wikiData);








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

if( count($wikiData) > 0 ){
	echo '<br>';
	echo '<h3>' . count($wikiData) . ' missing from Polls.csv, adding...</h3>';
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
	Remove *obvious* duplicates
	Using values for Year + Month + M, L, C, KD, S, V
	
	NOTE: Some duplicates will still occur due to
	the fact that Polls.csv can have errors
	TODO: Add check for strict and loose
*/

# create key for comparison
foreach( $pollsArr as $i => $arr ){
	
	$yearMonth = substr( $arr['collectPeriodTo'], 0, 7 );
	$yearMonth = str_replace('-', '', $yearMonth);
	$key = $yearMonth . $arr['M'] . $arr['L'] . $arr['C'] . $arr['KD'] . $arr['S'] . $arr['V'];
	$key = str_replace('.', '', $key);
	$pollsArr[$i]['key'] = $key;
	
}

# Remove dupes
# note: since wikiData is added last in array, data from pollsData is used if there's a duplicate
function array_key_unique($arr, $key) {
    $uniquekeys = array();
    $output     = array();
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
	unset($pollsArr[$i]['Date']);
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