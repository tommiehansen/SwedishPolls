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
$html = ""; # add all output to this and save later


# output large header
$colors->large_header(basename(__FILE__), "Check database for things that seem odd");


# shorthand for return sqlTable
function _table($data){
	return sqlTable($data, null, null, false );
}

# setup db table
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

$opts['file'] = $opts['name'] . '.report.html';
$opts = (object) $opts; // arr > object

if( $opts->name != 'Merged.sqlite' ){
	if( !contains('.sqlite', $opts->name) ) {
		$colors->error('Name was not a valid .sqlite file, quitting...', 'red');
		exit();
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
$db = new PDO('sqlite:' . DATA_DIR . $opts->name) or die("Error @ db");
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
$header = "$count 'odd' rows that have NULL PublYearMonth";

echo $colors->out("- $header \n");
$html.= "<h3>$header</h3>";

if( $count > 0 ){	
	$html .= _table( $nullData );
	!$isCli ? sqlTable( $nullData ) : '';
}






/*
	FIND ROWS NEAR NULL DATA
*/


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
			$new[$kplus] = ['Odd' => 'Yes'] + $new[$kplus];
		}
		
	}

	
}






/*
	FIND SIMILAR ROWS ON COMPANY
*/


$count = count($new);
$header = "Odd rows and +/- $numClose rows (if exist)";

echo $colors->out("- $header \n");
$html.= "<h3>$header</h3>";

if( $count > 0 ){	
	$html .= _table( $new );
	!$isCli ? sqlTable( $new ) : '';
}



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
$header = "$count count rows where next row is same company and collectPeriodTo is the same month";

echo $colors->out("- $header \n");
$html.= "<h3>$header</h3>";

if( $count > 0 ){	
	$html .= _table( $same );
	!$isCli ? sqlTable( $same ) : '';
}





/*
	WRITE REPORT TO FILE
*/

# add some simple html/css
$class = "table";
$prepend = "
	<!doctype html>
	<html>
	<head>
		<title>Report for ". $opts->name . "</title>
		<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
		<meta name='viewport' content='width=device-width, initial-scale=1'>
		<style>
			body { background: rgb(20,20,50); padding: 1%; }
			* { color: rgb(150,150,250); }
			table { width: 100%; border-collapse: collapse; border-spacing: 0; max-width: 1600px; }
			body, $class { font-family: monospace; }
			$class th { font-size: 13px; }
			$class td, $class th { text-align: left; border: 1px solid rgb(50,50,80); padding: 10px; }
			$class { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
			$class th { background: rgba(0,0,0,0.3); }
			$class tbody tr:nth-child(even) td { background: rgba(255,255,255,0.02); }
			$class tbody tr:hover td { background: rgba(255,255,255,0.1); color: rgb(180,180,255); }
			table[data-sortable] th{vertical-align:bottom} table[data-sortable] td,table[data-sortable] th{text-align:left}table[data-sortable] th:not([data-sortable=false]){-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;-o-user-select:none;user-select:none;-webkit-tap-highlight-color:transparent;-webkit-touch-callout:none;cursor:pointer}table[data-sortable] th:after{content:'';visibility:hidden;display:inline-block;vertical-align:inherit;height:0;width:0;border-width:5px;border-style:solid;border-color:transparent;margin-right:1px;margin-left:10px;float:right}table[data-sortable] th[data-sorted=true]:after{visibility:visible}table[data-sortable] th[data-sorted-direction=descending]:after{border-top-color:inherit;margin-top:8px}table[data-sortable] th[data-sorted-direction=ascending]:after{border-bottom-color:inherit;margin-top:3px}
			th { transition: all .15s ease; }
			th:hover { background: rgba(0,0,0,0.5); color: #fff; }
			th { position: relative; }
			th[data-sorted='true'] { background: rgba(0,0,0,0.8); color: #fff; }
			th:after { position: absolute; right: 5px; top:0; }
			h1 { margin-bottom:0; color: hotpink; font-weight: normal }
			h1 + p { margin-bottom: 2rem; }
			h3 { color: white; font-weight: normal }
			table + h3 { margin-top: 4rem; }
		</style>
	</head>
	<body>
	<h1>Report for ". $opts->name ."</h1>
	<p>Click table headers to sort ASC/DESC</p>
";

$append = '<script>(function(){var a,b,c,d,e,f,g;a="table[data-sortable]",d=/^-?[£$¤]?[\d,.]+%?$/,g=/^\s+|\s+$/g,c=["click"],f="ontouchstart"in document.documentElement,f&&c.push("touchstart"),b=function(a,b,c){return null!=a.addEventListener?a.addEventListener(b,c,!1):a.attachEvent("on"+b,c)},e={init:function(b){var c,d,f,g,h;for(null==b&&(b={}),null==b.selector&&(b.selector=a),d=document.querySelectorAll(b.selector),h=[],f=0,g=d.length;g>f;f++)c=d[f],h.push(e.initTable(c));return h},initTable:function(a){var b,c,d,f,g,h;if(1===(null!=(h=a.tHead)?h.rows.length:void 0)&&"true"!==a.getAttribute("data-sortable-initialized")){for(a.setAttribute("data-sortable-initialized","true"),d=a.querySelectorAll("th"),b=f=0,g=d.length;g>f;b=++f)c=d[b],"false"!==c.getAttribute("data-sortable")&&e.setupClickableTH(a,c,b);return a}},setupClickableTH:function(a,d,f){var g,h,i,j,k,l;for(i=e.getColumnType(a,f),h=function(b){var c,g,h,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z,A,B,C,D;if(b.handled===!0)return!1;for(b.handled=!0,m="true"===this.getAttribute("data-sorted"),n=this.getAttribute("data-sorted-direction"),h=m?"ascending"===n?"descending":"ascending":i.defaultSortDirection,p=this.parentNode.querySelectorAll("th"),s=0,w=p.length;w>s;s++)d=p[s],d.setAttribute("data-sorted","false"),d.removeAttribute("data-sorted-direction");if(this.setAttribute("data-sorted","true"),this.setAttribute("data-sorted-direction",h),o=a.tBodies[0],l=[],m){for(D=o.rows,v=0,z=D.length;z>v;v++)g=D[v],l.push(g);for(l.reverse(),B=0,A=l.length;A>B;B++)k=l[B],o.appendChild(k)}else{for(r=null!=i.compare?i.compare:function(a,b){return b-a},c=function(a,b){return a[0]===b[0]?a[2]-b[2]:i.reverse?r(b[0],a[0]):r(a[0],b[0])},C=o.rows,j=t=0,x=C.length;x>t;j=++t)k=C[j],q=e.getNodeValue(k.cells[f]),null!=i.comparator&&(q=i.comparator(q)),l.push([q,k,j]);for(l.sort(c),u=0,y=l.length;y>u;u++)k=l[u],o.appendChild(k[1])}return"function"==typeof window.CustomEvent&&"function"==typeof a.dispatchEvent?a.dispatchEvent(new CustomEvent("Sortable.sorted",{bubbles:!0})):void 0},l=[],j=0,k=c.length;k>j;j++)g=c[j],l.push(b(d,g,h));return l},getColumnType:function(a,b){var c,d,f,g,h,i,j,k,l,m,n;if(d=null!=(l=a.querySelectorAll("th")[b])?l.getAttribute("data-sortable-type"):void 0,null!=d)return e.typesObject[d];for(m=a.tBodies[0].rows,h=0,j=m.length;j>h;h++)for(c=m[h],f=e.getNodeValue(c.cells[b]),n=e.types,i=0,k=n.length;k>i;i++)if(g=n[i],g.match(f))return g;return e.typesObject.alpha},getNodeValue:function(a){var b;return a?(b=a.getAttribute("data-value"),null!==b?b:"undefined"!=typeof a.innerText?a.innerText.replace(g,""):a.textContent.replace(g,"")):""},setupTypes:function(a){var b,c,d,f;for(e.types=a,e.typesObject={},f=[],c=0,d=a.length;d>c;c++)b=a[c],f.push(e.typesObject[b.name]=b);return f}},e.setupTypes([{name:"numeric",defaultSortDirection:"descending",match:function(a){return a.match(d)},comparator:function(a){return parseFloat(a.replace(/[^0-9.-]/g,""),10)||0}},{name:"date",defaultSortDirection:"ascending",reverse:!0,match:function(a){return!isNaN(Date.parse(a))},comparator:function(a){return Date.parse(a)||0}},{name:"alpha",defaultSortDirection:"ascending",match:function(){return!0},compare:function(a,b){return a.localeCompare(b)}}]),setTimeout(e.init,0),"function"==typeof define&&define.amd?define(function(){return e}):"undefined"!=typeof exports?module.exports=e:window.Sortable=e}).call(this);</script>';

$append .= "
	</body>
	</html>
";

$html = $prepend . $html . $append;

$file = $opts->file;
$file_src = $config->cache_dir . $file;

file_put_contents($file_src, $html);

$colors->row("Report with details saved to $file_src");
echo "\n";

