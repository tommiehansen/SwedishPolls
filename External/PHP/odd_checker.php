<?php
/**
 *  Odd checker
 *  Find rows that is somehow odd and save as a report (HTML tables)
 *  @param name Set name of sqlite database to check
 */
 
require 'core/config.php'; // $config object
require 'core/helpers.php';
require 'core/class.cli.colors.php';
$colors = new Cli\Colors;
$html = ""; # add all output to this

# output large header
$colors->large_header(basename(__FILE__), "Checki database for things that seem odd");

# setup
$table = 'polls';


# options => default value
# gets overwritten of param set
$opts = [
	'name' => 'Merged.sqlite',
];


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
	
}
	



# cli or not?
$isCli = isCli();

if( !$isCli ){
	echo "
		<style>
		* { font-family: monospace; }
		pre, body { color: #666; }
		</style>
	";
}



# connect
$db = new PDO('sqlite:' . DATA_DIR . $opts['name']) or die("Error @ db");
$order = $config->order;



/*
	FIND NULL VALUES
*/

# query
$sql = "
	SELECT * FROM $table
	WHERE PublYearMonth IS NULL
	ORDER BY $order
";

$nullData = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$count = count($nullData);
$colors->row("$count 'odd' rows that have NULL PublYearMonth");
$count > 0 ? sqlTable($nullData) : '';





# get all rows
$sql = "SELECT * FROM $table ORDER BY $order";
$allData = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);




# find rows from $nullData in $allRows
$new = [];
$numClose = 2; // before/after 
foreach( $nullData as $i => $arr ){

	$id = $arr['id'];
	
	# find match in $allData
	foreach( $allData as $k => $allArr ){
		
		if( $allArr['id'] === $id ){
			
			// add nearby-arrays and matching (since last always is '0')
			$close = $numClose;
			while( $close-- ){
				$kmin = $k-$close;
				$kplus = $k+$close;
				
				if( isset( $allData[$kmin] )) { $new[$kmin] = $allData[$kmin]; $new[$kmin] = ['Odd' => '-'] + $new[$kmin]; }
				if( isset( $allData[$kplus] )) { $new[$kplus] = $allData[$kplus]; $new[$kplus] = ['Odd' => '-'] + $new[$kplus]; }
			}
			
			# mark matched one
			#$new[$k]['isOdd'] = 'YES';
			#array_unshift( $new[$k], ['isOdd' => 'Japp']);
			#$new[$k]['isOdd'] = 'Japp';
			$new[$kplus] = ['Odd' => 'Yes'] + $new[$kplus];
		}
		
	}

	
}

$count = count($new);
$count > 0 ? $colors->row("Odd rows and +/- $numClose rows (if exist)" ) : '';
$count > 0 ? sqlTable($new) : '';



# find rows where next row has same company name
# and has same collectPeriod
$same = [];
foreach( $allData as $i => $arr ){
	
	$company = $arr['Company'];
	$yearMonth = substr( $arr['collectPeriodTo'], 0, 7);
	
	if( isset( $allData[$i+1] ) ){
		$next = $allData[$i+1];
		if( $company == $next['Company'] ){
			$nextYearMonth = substr( $next['collectPeriodTo'], 0, 7);
			if( $yearMonth == $nextYearMonth ){
				$same[$i] = $arr;
				$same[$i+1] = $next;
			}
		}
	}
	
}

$count = count($same);
$colors->row("$count rows where next row is same company and collectPeriodTo is the same month");
$count > 0 ? sqlTable( $same ) : '';