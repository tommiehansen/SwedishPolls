<?php
	
	// CSV to array
	function csvArr( $csv ){
		$csv = explode("\n", $csv);
		foreach( $csv as $key => $row ){
			$csv[$key] = explode(',', $row);
		}
		return $csv;
	}
	
	// array to CSV
	function arrCSV( $arr, $addNA = true ){
		
		$out = '';
			foreach($arr as $key => $a) {
				
				// add 'NA' values
				if( $addNA ){
					foreach($a as $k => $v ){
						if( $v == '' ){ $a[$k] = 'NA'; }
					}
				}
				
				$out .= implode(",", $a) . PHP_EOL;
			}
		return $out;
		
	}
	
	// array to TSV
	function arrTSV( $arr, $addNA = true ){
		
		$out = '';
			foreach($arr as $key => $a) {
				
				// add 'NA' values
				if( $addNA ){
					foreach($a as $k => $v ){
						if( $v == '' ){ $a[$k] = 'NA'; }
					}
				}
				
				$out .= implode("\t", $a) . PHP_EOL;
			}
		return $out;
		
	}
	
	
	// perf-measure
	function timer_start(){
		return microtime(true);
	}
	
	function timer_end($start){
		$end = microtime(true)-$start;
		$end = round($end*1000)/1000;
		return $end . 's';
	}
	
	
	// isCli
	function isCli(){
		isset($GLOBALS['argv']) ? $isCli = true : $isCli = false;
		return $isCli;
	}
	
	// pre
	function pre( $str ){
		print_r($str);
		echo "\n";
	}
	
	// contains
	function contains($needle, $haystack) {
		return strpos($haystack, $needle) !== false;
	}
	
	
	
	/*
		Take WIKI-date and turn it into a bounch of arrays with from/to etc
		Returns all the dates needed
	*/

	function processDate( $str ){
		
		$arr = [];
		$months = ["jan", "feb", "mar", "apr", "maj", "jun", "jul", "aug", "sep", "okt", "nov", "dec"];
		
		$split = explode('-', $str);
		$from = $split[0];
		$to = $split[1];
		
		// get Y-m-d toDate
		$toYMD = date('Y-m-d', strtotime($to));
		
		if( strlen($from) <= 2 ) { // it's in the same month and has no month definition
			$fromYMD = explode(' ', $to);
			$fromYMD = $from . ' ' . $fromYMD[1] . ' ' . $fromYMD[2];
			$fromYMD = date('Y-m-d', strtotime($fromYMD));
		}
		else {
			$fromYMD = date('Y-m-d', strtotime($from));
		}
		
		$toTime = strtotime($toYMD);
		$y = date('Y', $toTime);
		$m = date('m', $toTime);
		$m = str_replace('0','', $m);
		$publYearMonth = $y . '-' . $months[$m-1];
		
		$arr['PublYearMonth'] = $publYearMonth;
		$arr['collectPeriodFrom'] = $fromYMD;
		$arr['collectPeriodTo'] = $toYMD;
		$arr['PublDate'] = 'NA'; // wikipedia doesn't really have this value all the time so 'NA'
		
		return $arr;
		
	}


	# prePrint	
	function prp($str){ echo '<pre>'; print_r($str); echo '</pre>'; }


	# output table from query; requires PDO::FETCHASSOC from query result
	function sqlTable($res, $class = 'tbl', $css = true ){
		
		if( $css ){
			echo "
			<style>
			.$class { font-family: monospace; }
			.$class td, .$class th { text-align: left; border: 1px solid #ddd; padding: 5px; }
			.$class { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
			.$class tr:hover td { background: #ffc; }
			.$class th { background: #ffd }
			</style>
			";
		}
	
		isset( $res[0] ) ? $head = $res[0] : $head = $res[key($res)];
		
		echo "<table class='$class' data-sortable><thead><tr>";
		foreach($head as $k=>$v){
		    echo "<th>$k</th>";
		}
		echo "</tr></thead><tbody>";

		foreach( $res as $key => $val ){
		    echo "<tr>";
		    foreach($head as $i=>$h){
		        echo "<td title='$i'>" . $val[$i] . "</td>";
		    }
		    echo "</tr>";
		}

		echo "</tbody></table>";

	}



	/*-----------------------------------------------------

		SIMPLIFIED CACHE FUNC

	    example
	    curl_cache('http://google.com', 'cache/mycachefile.php', '1 hour');
		
		set $time to 'false' to disable

	-----------------------------------------------------*/

	function curl_cache($src, $file, $time){

	    $exists = file_exists($file);
	    $time = "+" . $time;

		$isExternal = false;
		if (strpos($src, 'http') !== false) { $isExternal = true; }

	    // file does not exist or is over x time
	    if( !$exists || !$time || ( $exists && time() > strtotime("$time", filemtime($file))) ) {

			if( $isExternal ){

		        $curl = curl_init($src);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				$data = curl_exec($curl);
				$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);


				file_put_contents( $file, $data );
				curl_close($curl);

			} // $isExternal

			// not external, is a string of some sort -- so just put it in a file
			else {
				if( $time ){
					file_put_contents( $file, $src );
				}
			}

	    }

	    // file exists, just get it
	    else {
	        $data = file_get_contents($file);
	    }

		// return
		return $data;

	} // curl_cache()
	

?>
