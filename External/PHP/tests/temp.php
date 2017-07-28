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

$limit = 20;
$company = 'SCB';








#prp($polls_src);
#prp($wiki_src);


$pollsDB = new PDO('sqlite:' . $polls_src) or die("Error @ db");
$wikiDB = new PDO('sqlite:' . $wiki_src) or die("Error @ db");



/* merge data */
# err -- omits data that resides originally
$db = $pollsDB;

$db->exec("ATTACH '$wiki_src' as w");


$sql = "	
	SELECT * from polls a
	
	JOIN w.polls b
	ON a.id = b.id
	
	WHERE a.Company = '$company'
	ORDER BY a.collectPeriodTo DESC, a.Company DESC
	LIMIT $limit
";


$res = $db->query($sql);
$res = $res->fetchAll(PDO::FETCH_ASSOC);

echo '<h3>Merged</h3>';
sqlTable($res);





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
$pollsData = $wikiDB->query($sql);
$pollsData = $pollsData->fetchAll(PDO::FETCH_ASSOC);


echo '<h3>Wiki DB</h3>';
sqlTable($pollsData);