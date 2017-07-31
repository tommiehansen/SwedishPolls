<?php
	namespace Cli;

	class Colors {
		
		private $foreground_colors = array();
		private $background_colors = array();

		public function __construct() {

			// Setup shell colors
			$textColors = [
				'white'			=> '1;37',
				'black'			=> '0;30',
				'dark_gray' 	=> '1;30',
				'red'			=> '0;31',
				'light_red'		=> '1;31',
				'green'			=> '0;32',
				'light_green'	=> '1;32',
				'brown'			=> '0;33',
				'yellow'		=> '1;33',
				'blue'			=> '0;34',
				'light_blue'	=> '1;34',
				'purple'		=> '0;35',
				'light_purple'	=> '1;35',
				'cyan'			=> '0;36',
				'light_cyan'	=> '1;36',
				'light_gray'	=> '0;37',
				'magenta'		=> '0;35',
				'light_magenta'	=> '1;95',
			];

			foreach( $textColors as $name => $color ){
				$this->foreground_colors[$name] = $color;
			}

			$this->textColors = $textColors; // make available for later use

			$backgroundColors = [
				'black'			=> '40',
				'red'		 	=> '41',
				'green'			=> '42',
				'yellow'		=> '43',
				'blue'			=> '44',
				'magenta'		=> '35',
				'cyan'			=> '46',
				'light_gray'	=> '47',
			];

			foreach( $backgroundColors as $name => $color ){
				$this->background_colors[$name] = $color;
			}

			$this->backgroundColors = $backgroundColors; // make available for later use

		}

		// Returns colored string
		public function out($string, $foreground_color = null, $background_color = null) {
			$colored_string = "";

			// Check if given foreground color found
			if (isset($this->foreground_colors[$foreground_color])) {
				$colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
			}
			// Check if given background color found
			if (isset($this->background_colors[$background_color])) {
				$colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
			}

			// Add string and end coloring
			$colored_string .=  $string . "\033[0m";
			$colored_string = "  " . $colored_string;

			return $colored_string;
		}

		// Returns all foreground color names
		public function getForegroundColors() {
			return array_keys($this->foreground_colors);
		}

		// Returns all background color names
		public function getBackgroundColors() {
			return array_keys($this->background_colors);
		}

		// small quick functions
		public function done(){
			echo $this->out("Done \n", "light_magenta");
		}

		public function header($str, $color = 'white'){
			echo "\n";
			echo $this->out("$str\n", "$color");
		}
		
		public function error($str, $color = ''){
			$out = "\n";
			$out .= $this->out("ERROR", 'white', 'red');
			$out .= str_replace('  ','', $this->out(" $str\n", "$color"));
			$out .= "\n";
			echo $out;
		}
		
		public function row($str, $color = 'white'){
			$out = "\n";
			$out .= $this->out("$str\n", "$color");
			echo $out;
		}

		// output all colors to screen
		public function testColors(){
			$textColors = $this->textColors;
			foreach( $textColors as $name => $color ){
				echo $this->out( "$name: $color \n", "$name" );
			}

			echo "\n\n";
		}

		public function showColorChart(){
			$stop = 255;
			$start = 0;
			$range = range($start, $stop);

			$this->header("*** TESTING COLOR RANGE: $start-$stop ***", 'white');

			foreach( $range as $key => $r ){
				$color = "0;" . $r;
				$str = "\033[{$color}m";
				$str .= $color . ': Text' . "\033[0m";
				echo $str;

				// 4 columns
				if($key % 4 !== 0) { echo "\t"; }
				else { echo "\n"; }

				// add break to last
				if( !isset($range[$key+1]) ){ echo "\n\n"; }
			}

			echo "\n";
			$this->header("*** 1;XX: $start-$stop ***", 'white');

			foreach( $range as $key => $r ){
				$color = "1;" . $r;
				$str = "\033[{$color}m";
				$str .= $color . ': Text' . "\033[0m";
				echo $str;

				// 4 columns
				if($key % 4 !== 0) { echo "\t"; }
				else { echo "\n"; }

				// add break to last
				if( !isset($range[$key+1]) ){ echo "\n\n"; }
			}

		}

	}

?>
