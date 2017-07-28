<title># temp</title>
<style>
* { font-family: monospace; } .tbl td, .tbl th { text-align: left; }
.tbl { width: 100%; border-collapse: collapse; margin-bottom: 2rem; } td,th { border: 1px solid #ddd; padding: 5px; } tr:hover td { background: #ffc; }
th { background: #ffd }
</style>
<?php
/**
 *  Temp
 */
 
require '../core/config.php'; // $config object
require '../core/helpers.php';
require '../core/class.wikipedia.php';
#require '../core/class.cli.colors.php';



header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$wiki = new Polls\Wikipedia;

prp( date('Y-m-d / G:i') );



# setup stuff
$data_dir = '../' . DATA_DIR;
$polls_src = $data_dir . 'Polls_Wikipedia.sqlite';
$wiki_src = $data_dir . 'Wikipedia.sqlite';

$limit = 10;
$company = 'Inizio';








#prp($polls_src);
#prp($wiki_src);


$pollsDB = new PDO('sqlite:' . $polls_src) or die("Error @ db");
$wikiDB = new PDO('sqlite:' . $wiki_src) or die("Error @ db");

$selectFields = "
	a.id,
	a.PublYearMonth,
	a.Company,
	a.M,
	a.L,
	a.C,
	a.KD,
	a.S,
	a.V,
	a.MP,
	a.SD,
	a.FI,
	b.OTH, -- include 'Other' from Wikipedia
	a.Uncertain,
	a.n,
	a.PublDate,
	a.collectPeriodFrom,
	a.collectPeriodTo,
	a.approxPeriod,
	a.house
";

// coalesce possible NULL-returns
$combine = [
	'FI'
];

foreach( $combine as $c ){
	#$selectFields .= " coalesce(a.$c,b.$c) as $c,";
}

#$selectFields = rtrim($selectFields, ',');
#prp( $selectFields );

# merge
# err -- omits data that resides originally
$db = $pollsDB;

$db->exec("ATTACH '$wiki_src' as w");
$db->exec("ATTACH '$polls_src' as p");


# all rows that has conflicting data will not be joined
$sql = "	
	SELECT
	$selectFields
	from polls a
	
	LEFT OUTER JOIN w.polls as b -- Must left-join else non-matched is omitted
	ON b.id = a.id
	
	WHERE a.Company = '$company'
	ORDER BY a.collectPeriodTo DESC, a.Company DESC
	LIMIT $limit
";




/*
$commonFields = "
	id,
	Company,
	M,
	L,
	C,
	KD,
	S,
	V,
	MP,
	SD,
	FI,
	collectPeriodFrom,
	collectPeriodTo
";

$sql = "
	SELECT $commonFields from p.polls
	WHERE Company = '$company'
	
	UNION
	
	SELECT $commonFields from w.polls
	WHERE Company = '$company'
	
	ORDER BY collectPeriodTo DESC, Company ASC
	LIMIT $limit
";


$sql = "
	SELECT * FROM (
		SELECT $commonFields from w.polls
		WHERE Company = '$company'
		
		UNION
		
		SELECT $commonFields from p.polls
		WHERE Company = '$company'
	) as a
	
	
	LEFT JOIN w.polls as b
	ON a.id = b.id
	
	ORDER BY collectPeriodTo DESC, Company ASC
	LIMIT $limit
";




$res = $db->query($sql);
$res = $res->fetchAll(PDO::FETCH_ASSOC);

echo '<h3>Merged</h3>';
sqlTable($res);

*/


# compare with pollsData
$sql = "		
	SELECT * from polls a
	WHERE a.Company = '$company'
	ORDER BY a.collectPeriodTo DESC, a.Company DESC
	LIMIT $limit
";
$pollsData = $pollsDB->query($sql);
$pollsData = $pollsData->fetchAll(PDO::FETCH_ASSOC);



echo '<h3>Polls DB</h3>';
sqlTable($pollsData);


# compare with wikidata
$sql = "		
	SELECT * from polls a
	WHERE a.Company = '$company'
	ORDER BY a.collectPeriodTo DESC, a.Company DESC
	LIMIT $limit
";
$wikiData = $wikiDB->query($sql);
$wikiData = $wikiData->fetchAll(PDO::FETCH_ASSOC);


echo '<h3>Wiki DB</h3>';
sqlTable($wikiData);




/*
	1 - loop polls array and add data from Wikipedia
	
*/

$pollsArr = [];


foreach( $pollsData as $i => $arr ){
	
	// keys to variables
	foreach($arr as $k => $a ){ $$k = $a; }
	
	$hasMatch = false;
	
	# check if id exist in wikiArr
	# and write stuff if true
	foreach( $wikiData as $wikiArr ){
		
		if( $id === $wikiArr['id'] ) {
			$hasMatch = true;
			
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

echo '<h3>Merged</h3>';
sqlTable( $pollsArr );