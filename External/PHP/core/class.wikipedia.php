<?php
namespace Polls;

class Wikipedia {
	
	public $db; // db-handle
	public $fields;
	
	function __construct(){
		
		$this->fields = [
			'id' => 'TEXT',
			'Company' => 'TEXT',
			
			'Date' => 'TEXT',
			'collectPeriodFrom' => 'NUMERIC',
			'collectPeriodTo' => 'NUMERIC',

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
		];
		
	}
	
	
	/**
	 *  @brief Create Database and table
	 *  
	 *  @param [str] $db File src
	 *  @param [str] $table Table name
	 *  @return Returns $db handle for further use
	 */
	public function createDatabase($db, $table){

		$dir = "sqlite:" . $db;
		$db	= new \PDO($dir) or die("Error @ db");
		
		$db->beginTransaction();

			$fields = $this->fields;

			// create all fields
			$sql = "CREATE TABLE IF NOT EXISTS $table (";

			foreach( $fields as $key => $val ){	
				$sql .= " $key $val, ";
			}

			$sql .= " PRIMARY KEY(id) "; // primary
			$sql .= ")"; 

			$db->exec($sql);
			
			$db->exec("DELETE FROM $table"); // clear table
			$db->exec("VACUUM");
	
		$db->commit();
		
		// return $db handle for later use
		$this->db = $db;
		
	}
	
	/**
	 *  @brief Parse Wikipedia
	 *  
	 *  @param [obj] $data JSON-object from Wikipedia
	 *  @return array
	 */
	public function parse( $data ){
		
		// clean
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
			if( $i > 0 && $i < 10 ){
				if( $cols[1]->nodeValue === 'General Election' ) continue; // skip 'General Election', but not last one
			}
			
			# check if general election since it has special date data that differ from everything else
			$cols[1]->nodeValue === 'General Election'? $isGeneral = true : $isGeneral = false;
			
			// loop columns
			for ($j = 0; $j < $jlen; $j++) {
				
				$val = $cols->item($j)->nodeValue;
				
				// Normalize 'SKOP'
				$j == 1 && $val == 'SKOP' ? $val = 'Skop' : '';
				
				// check / add null values
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
				if( $j == 0 && !$isGeneral ) {
					
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
					
					
				} // $j == 0 && !$isGeneral
				
				
				if( $j == 0 && $isGeneral ){
					
					// normalize format for 'General Election'
					$date = $val;
					$fromYMD = date('Y-m-d', strtotime($date));
					$toYMD = date('Y-m-d', strtotime($date));
					
				}
				
				// set collection periods
				$arr[$i]['collectPeriodFrom'] = $fromYMD;
				$arr[$i]['collectPeriodTo'] = $toYMD;
				
			}
			
			
		} // for-loop
		
		
		return $arr;
		
	} // parse()
	
	
	
	/**
	 *  @brief Sort
	 *  
	 *  @param [array] $arr Parsed array from wikipedia
	 *  @return Array
	 */
	public function sortParse( $arr ){
		
		$sort = [];
		foreach( $arr as $i => $val ){
			
			$cur = $arr[$i];
			
			// generate id
			$from = substr($cur['collectPeriodFrom'],2);
			$to = substr($cur['collectPeriodTo'],2);
			$id = $to. $from . $cur['M'] . $cur['L'] . $cur['C'] . $cur['KD'];
			$id = str_replace(['-','.'],['',''], $id);
			
			$sort[$i][0] = $id;
			$sort[$i][1] = $arr[$i]['Company'];
			
			$sort[$i][2] = $arr[$i]['Date'];
			$sort[$i][3] = $arr[$i]['collectPeriodFrom'];
			$sort[$i][4] = $arr[$i]['collectPeriodTo'];
			
			$sort[$i][5] = $arr[$i]['M'];
			$sort[$i][6] = $arr[$i]['L'];
			$sort[$i][7] = $arr[$i]['C'];
			$sort[$i][8] = $arr[$i]['KD'];
			$sort[$i][9] = $arr[$i]['S'];
			$sort[$i][10] = $arr[$i]['V'];
			$sort[$i][11] = $arr[$i]['MP'];
			$sort[$i][12] = $arr[$i]['SD'];
			$sort[$i][13] = $arr[$i]['FI'];
			$sort[$i][14] = $arr[$i]['OTH'];
			
		}

		$wikiArr = array_values($sort);
		
		return $wikiArr;
				
		
	} // sort()
	
} // class Wikipedia