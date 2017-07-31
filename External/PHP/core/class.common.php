<?php
namespace Polls;

class Common {
	
	public $db; // db-handle	
	
	/**
	 *  Create Database and table
	 *  
	 *  @param [str] $db File src
	 *  @param [str] $table Table name
	 *  @param [arr] $fields Database fields and type(s)
	 *  
	 *  @return Returns/sets $db handle for further use
	 */
	public function createDatabase($db, $table, $fields ){

		$db = "sqlite:" . $db;
		$db	= new \PDO($db) or die("Error @ db");
		
		$db->beginTransaction();
		
			# SQLite settings
			$db->exec("PRAGMA synchronous=OFF");
			$db->exec('PRAGMA journal_mode=MEMORY');
			$db->exec('PRAGMA temp_store=MEMORY');
			$db->exec('PRAGMA count_changes=OFF');
			$db->exec("DELETE FROM $table"); // clear table (temp)

			# create all fields
			$sql = "CREATE TABLE IF NOT EXISTS $table (";

			foreach( $fields as $key => $val ){	
				$sql .= " $key $val, ";
			}

			$sql .= " PRIMARY KEY(id) "; // primary
			$sql .= ")"; 

			$db->exec($sql);
	
		$db->commit();
		
		// return $db handle for later use
		$this->db = $db;
		return $db;
	
	}
	
	/**
	 *  Generate inserts
	 *  
	 *  @param [str] $table Table name
	 *  @param [arr] $fields Array of fields
	 *  @return Returns insert string
	 */
	public function generateInserts( $table, $fields ){
		
		# generate inserts
		$inserts = "INSERT OR IGNORE INTO $table(";
		foreach( $fields as $field => $val ){
			$inserts .= "`$field`,";
		}
		
		$inserts .= ') VALUES (';
		foreach( $fields as $field ){
			$inserts .= "?,";
		}
		$inserts .= ');';
		$inserts = str_replace(',)',')', $inserts);
		
		return $inserts;
		
	}
	
} // class Common