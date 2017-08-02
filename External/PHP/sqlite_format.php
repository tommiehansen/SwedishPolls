<?php
/**
 *  Format existing data from SQLite to:
 *  CSV, TSV or JSON
 *  NOTE: CSV and TSV adds 'NA' values for 'null', JSON does not.
 */

 
require 'core/config.php'; // $config object
require 'core/helpers.php';
require 'core/class.cli.colors.php';
$colors = new Cli\Colors;


# valid arguments and defaults
$opts = [
	'file' => 'Polls.sqlite',
	'format' => 'csv',
	'file_out' => 'Polls.csv',
	'table' => 'Polls',
];


# check if terminal
$isCli = isCli();

if( !$isCli ) exit('Cannot run this in a browser.');


# get command line arguments
$params = $argv;

# check if arguments
if( count($params) > 1 ){
	
	# get file value
	$file = $params[1];
	
	if( !contains('file=', $file) ) {
		$colors->error('First argument must be file=');
		exit();
	}
	
	$file = explode('=', $file)[1];
	
	if( explode('.', $file)[1] != 'sqlite' ) {
		$colors->error("File must be a sqlite database, you entered '$file'");
		exit();
	}
	
	$opts['file'] = $file;
	
	# get format value if set
	if( isset($params[2]) ){
		$format = $params[2];
		$format = explode('=', $format)[1];
		
		if( $format == 'csv' || $format == 'tsv' || $format == 'json' ){
			$opts['format'] = $format;
		}
		else {
			$colors->error('Format must be csv, tsv or json, quitting...');
			exit();
		}
	}
	
	# set other filename as output
	if( isset($params[3]) ){
		$file = $params[3];
		$file = explode('=', $file)[1];
		$fileCustom = $file;
	}
	
	# get table value if set
	if( isset($params[4]) ){
		$table = $params[4];
		$table = explode('=', $table)[1];
	}
}

$opts = (object) $opts;


# output header
$file = $opts->file;
$format = $opts->format;
$file_out = $opts->file_out;

if( isset( $fileCustom ) ){ $file_out = DATA_DIR . $fileCustom; }
$colors->large_header(basename(__FILE__), "$file >> $file_out");


$file = $opts->file;
$file = DATA_DIR . $file;
$file_out = str_replace('.sqlite', '.'.$opts->format, $file);
if( isset( $fileCustom ) ){
	$file_out = DATA_DIR . $fileCustom;
}


# check if the file exists
if( !file_exists($file) ) {
	$colors->error("The file '$file' does not exist and thus cannot be used, quitting...");
	exit();
}





/*
	CONNECT TO DB
	And query
*/

$colors->row("Fetching database...");

# connect to database
$db = new \PDO('sqlite:' . $file) OR $colors->error("Could not connect to database '$file'");
$order = $config->order;
$sql = "
	SELECT * FROM Polls
	ORDER BY $order
";


$db->beginTransaction();
	$data = $db->query($sql);
	$data = $data->fetchAll(PDO::FETCH_ASSOC);
$db->commit(); $db = null;

$colors->done();



/*
	FORMAT
*/

$format = $opts->format;
$colors->row("Formatting to $format...");

# remove keys
$rmKeys = ['id'];
foreach( $data as $key => $arr ){
	foreach( $rmKeys as $rm ){
		unset( $data[$key][$rm] );
	}
}


# add headers to CSV and TSV
if( $format === 'csv' || $format === 'tsv' ){
	$header = [];
	foreach($data[0] as $key => $arr ){
		$header[] = $key;
	}
	array_unshift($data, $header);
}


# format
switch( $format ){
	case 'csv':
		$formatted = arrCSV($data); break;
	case 'tsv':
		$formatted = arrTSV($data); break;
	case 'json':
		$formatted = json_encode($data);
		break;
	default:
		$formatted = arrCSV($data);
}

$colors->done();


# write to file
$colors->row("Writing to file '$file_out'");
file_put_contents($file_out, $formatted);

$colors->done();
echo "\n";