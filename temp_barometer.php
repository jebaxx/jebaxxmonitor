<?php
require 'vendor/autoload.php';
use Google\Cloud\Datastore\DatastoreClient;
putenv('GOOGLE_APPLICATION_CREDENTIALS=jebaxxMonitor-be540bce3cfb.json');
$projectId = 'jebaxxmonitor';
$datastore = new DataStoreClient(['projectId' => $projectId]);

date_default_timezone_set('Asia/Tokyo');

if (isset($_GET['_start'])) {
	$d1 = new DateTime($_GET['_start']);
	$d2 = clone $d1;
	$d2->add(new DateInterval('P0'.$_GET['_span'].'D'));
	$d2->sub(new DateInterval('PT1S'));
	$_span = intval($_GET['_span']);
}
else {
	$d1 = new DateTime("today");
	$d2 = new DateTime("tomorrow");
	$d2->sub(new DateInterval('PT1S'));
	$_span = 1;
}

// initialize tabulation data
//
$tbl_T = $tbl_P = '';
$e_cnt = array();
$e_sum = array();
$e_max = array();
$e_min = array();

for ($sensor_id = 0; $sensor_id < 2; $sensor_id++) {
	$e_cnt[$sensor_id] = 0;
	$e_sum[$sensor_id] = 0.0;
	$e_max[$sensor_id] = -10000.0;
	$e_min[$sensor_id] = +10000.0;
}

$sensor_name = array( 'T-ADT7410-02', 'P-BMP180-01');

// query measured Temperature record
//
$query = $datastore->query()
  ->kind('sensDat2')
  ->filter('timestamp', '>', $d1->getTimeStamp())
  ->filter('timestamp', '<', $d2->getTimeStamp())
  ->order('timestamp')
  ->projection(['timestamp', $sensor_name[0], $sensor_name[1]]);

$result = $datastore->runQuery($query);

/************************** TEST DATA *********************
$result = array(
	array('timestamp' => 1507728303, 'T-ADT7410-02'=>27.6, 'P-BMP180-01'=>999.8),
	array('timestamp' => 1507728363, 'T-ADT7410-02'=>27.2, 'P-BMP180-01'=>1000.0),
	array('timestamp' => 1507728423, 'T-ADT7410-02'=>27.3, 'P-BMP180-01'=>1000.2),
	array('timestamp' => 1507728483, 'T-ADT7410-02'=>27.8, 'P-BMP180-01'=>1000.4),
	array('timestamp' => 1507728543, 'T-ADT7410-02'=>27.7, 'P-BMP180-01'=>1001.0),
		);

**************************/

$g0 = 24*60*$_span;
$g = 0;

// create javascript dataset
foreach ($result as $sensData) {
	if (($g -= 1000) > 0) continue;
	$g += $g0;

	$timestamp = $sensData['timestamp'];
	$logTime = new DateTime();
	$logTime->setTimeStamp($timestamp);
	$_time = 'new Date('.$logTime->format('Y,m,d,H,i'). ',0)';

	$value = $sensData[$sensor_name[0]];
	$e_sum[0] += $value;
	$e_cnt[0] ++;
	$e_max[0] = max($e_max[0], $value);
	$e_min[0] = min($e_min[0], $value);
	$tbl_T .= '	['.$_time.', '.$value.'],'.PHP_EOL;

	$value = $sensData[$sensor_name[1]];
	$e_sum[1] += $value;
	$e_cnt[1] ++;
	$e_max[1] = max($e_max[1], $value);
	$e_min[1] = min($e_min[1], $value);
	$tbl_P .= '	['.$_time.', '.$value.'],'.PHP_EOL;

}
?>

<!--
/////////////////////////////////////////////////////////////////////////////////////////
//	HTML
/////////////////////////////////////////////////////////////////////////////////////////
-->
<html>
<head>
	<script type = "text/javascript" src="https://www.google.com/jsapi"></script>
	<script type = "text/javascript">
		google.load('visualization', '1.1', {packages: ['corechart']});
		google.setOnLoadCallback(drawCharts);

	var data_T, opt_T, chart_T, view_T;
	var data_P, opt_P, chart_P, viwe_P;

	function drawCharts() {
		drawChart_T();
		drawChart_P();
	}

	function drawChart_T() {
		data_T = new google.visualization.DataTable();
		data_T.addColumn('datetime', 'Time');
		<?php
		    echo "data_T.addColumn('number', '" .$sensor_name[0]. "');".PHP_EOL;
		?>
		data_T.addRows([
			<?php echo $tbl_T; ?>
		]);

		view_T = new google.visualization.DataView(data_T);

		opt_T = { 
			title: 'Temperature (C)',
			legend: { position: 'in' },
			vAxis: { minorGridlines: { count: 4, color: '#E6E6FA' }},
			hAxis: { minorGridlines: { count: 3, color: '#E6E6FA' },
				 viewWindow: {min: new Date(<?php echo $d1->format('Y,m,d,H,i,s') ?>),
				 	      max: new Date(<?php echo $d2->format('Y,m,d,H,i,s') ?>), } },
			height: '350',
			width:  '100%'
		};

		chart_T = new google.visualization.LineChart(
					document.getElementById('linechart_temp'));

		var setC = new Array();
		setC[0] = 0;
		for (i = j = 0; i < 1 ;  i++) {
			if (document.visibleData[i].checked == true) {
				setC[++j] = i+1;
			}
		}
		view_T.setColumns(setC);

		chart_T.draw(view_T, opt_T);
	}

	function drawChart_P() {
		data_P = new google.visualization.DataTable();
		data_P.addColumn('datetime', 'Time');
		<?php
		  echo "data_P.addColumn('number', '" .$sensor_name[1]. "');".PHP_EOL;
		?>
		data_P.addRows([
			<?php echo $tbl_P; ?>
		]);

		view_P = new google.visualization.DataView(data_P);

		opt_P = {
 			title: 'Atomospheric Pressure (hP)',
			legend: { position: 'in' },
			vAxis: { viewWindow: {min: null, max: null, },
				 minorGridlines: { count: 3, color: '#E6E6FA' } },
			hAxis: { minorGridlines: { count: 3, color: '#E6E6FA' },
				 viewWindow: {min: new Date(<?php echo $d1->format('Y,m,d,H,i,s') ?>),
				 	      max: new Date(<?php echo $d2->format('Y,m,d,H,i,s') ?>), } },
			height: '350',
			width:  '100%'
		};

		chart_P = new google.visualization.LineChart(
					document.getElementById('linechart_P'));

		chart_P.draw(view_P, opt_P);
	}

	function changeColumn(index, checked) {
		var setC = new Array();
		setC[0] = 0;
		for (i = j = 0; i < 1 ;  i++) {
			if (document.visibleData[i].checked == true) {
				setC[++j] = i+1;
			}
		}

		view_T.setColumns(setC);
		chart_T.draw(view_T, opt_T);
	}

	function changeScale(mode) {
		if (mode == 'FIXED') {
			opt_P['vAxis']['viewWindow']['min'] = 1020;
			opt_P['vAxis']['viewWindow']['max'] = 960;
		}
		else {
			opt_P['vAxis']['viewWindow']['min'] = null;
			opt_P['vAxis']['viewWindow']['max'] = null;
		}
		chart_P.draw(view_P, opt_P);
	}

	function changeSize_T(mode) {
		if (mode == 'small') {
			opt_T['height'] = 150;
		}
		else if (mode == 'medium') {
			opt_T['height'] = 350;
		}
		else {
			opt_T['height'] = 700;
		}
		chart_T.draw(view_T, opt_T);
	}

	function changeSize_P(mode) {
		if (mode == 'small') {
			opt_P['height'] = 150;
		}
		else if (mode == 'medium') {
			opt_P['height'] = 350;
		}
		else {
			opt_P['height'] = 700;
		}
		chart_P.draw(view_P, opt_P);
	}

	function goto_other_page(page, span) {
		if (page == '_Home') {
			nexturl = "index.html";
		}
		else {
			nexturl = "temp_barometer.php?_start="+page+"&_span="+span;
		}
		location.href = nexturl;
	}

	</script>

</head>

<!--
/////////////////////////////////////////////////////////////////////////////////////////
//	BODY
/////////////////////////////////////////////////////////////////////////////////////////
-->
<body onresize="chart_T.draw(view_T, opt_T);chart_P.draw(view_P, opt_P);">
	<h2>Temperature and Barometer (<?php 
	$date = $d1->format('Y/m/d');
	if ($_span > 1) $date .= " - ".$d2->format('Y/m/d');
	echo $date;
	?>)</h2>

	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	form 1
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	<form name="gotoPage">
	<table>
	<tr>
	  <td width=100>
	    <input type='date' name='Goto_Date' value='<?php
	    	$d1->format('Y-m-d') ?>'>
	  </td>
	  <td>
	    <span style="padding-right: 20pt">
	      <button type = "button" onclick="goto_other_page(form.Goto_Date.value, form.Span.value)">
	      GOTO
	      </button>
	    </span>
	  </td>
	  <td>
	    <button type = "button" onclick="goto_other_page('<?php
	    	$d3 = clone $d1;
	    	$d3->sub(new DateInterval('P01D'));
	    	echo $d3->format('Y-m-d H:i');  ?>', form.Span.value)" >Previous day</button>
	  </td>
	  <td>
	    <select name="Span">
	      <option value='1'<?php
	      	if ($_span == 1) echo " selected";
	      	?>>1 day</option>
	      <option value='2'<?php
	      	if ($_span == 2) echo " selected";
	      	?>>2 days</option>
	      <option value='3'<?php
	      	if ($_span == 3) echo " selected";
	      	?>>3 days</option>
	    </select>
	  </td>
	  <td>
		<button type = "button" onclick="goto_other_page('<?php
		$d3 = clone $d1;
		$d3->add(new DateInterval('P01D'));
		echo $d3->format('Y-m-d H:i');  ?>', form.Span.value)" >next day</button>
	  </td>
  	  <td>
	    <span style="padding-left: 30pt">
	    <A href="index.html">home</A>
	    </span>
	  </td>
	  <td>
	    <span style="padding-left: 40pt">
	    <A href="temp_details.php?_start=<?php
	    	echo $d1->format('Y-m-d H:i'); 
	    	?>&_span=1">Chart for all sensors of temperature</A>
	    </span>
	  </td>
	</tr>
	</table>
	</form>

	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	form 2
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	<form name="visibleData" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span style="padding-right: 10pt">*Data Select:</span>
	<?php
	echo '	<input type="checkbox" name="tmpr" value="1" checked="checked"';
	echo ' onclick="changeColumn(this.value, this.checked)">'.PHP_EOL;
	echo '	  <span style="padding-right: 10pt">'.$sensor_name[0].'</span>'.PHP_EOL;
	?>

	  <span style="padding-right: 8pt">*SIZE :</span>
	  <input type="radio" name="t_size" onclick="changeSize_T('small')">SMALL
	  <input type="radio" name="t_size" onclick="changeSize_T('medium')" checked="checked">MEDIUM
	  <input type="radio" name="t_size" onclick="changeSize_T('large')">LARGE
	</form>

	<div id="linechart_temp"></div>

	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	form 3
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	<form name="scale" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span style="padding-right: 10pt">*SCALE:</span>
	  <input type="radio" name="s_temp" onclick="changeScale('AUTO')" checked="checked">AUTO
	  <input type="radio" name="s_temp" onclick="changeScale('FIXED')">FIXED
	  <span style="padding-left: 50pt; padding-right: 8pt">*SIZE:</span>
	  <input type="radio" name="p_size" onclick="changeSize_P('small')">SMALL
	  <input type="radio" name="p_size" onclick="changeSize_P('medium')" checked="checked">MEDIUM
	  <input type="radio" name="p_size" onclick="changeSize_P('large')">LARGE
	</form>

	<div id="linechart_P"></div>
	<hr>
	//////////////////////////
	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	Summary table
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	<?php echo "<h3>*** Record of the day (".$date.") ***</h3>\n"; ?>
	<table border>
	<thead>
	<tr>
	<th width=170>temperature</th>
	<th width=100>min</th>
	<th width=100>max</th>
	<th width=100>average</th>
	</tr>
	</thead>
	<tbody>
	<?php
	  echo '<tr><td>'.$sensor_name[0].'</td><td>';
	  printf("%7.2f", $e_min[0]);
	  echo '</td><td>';
	  printf("%7.2f", $e_max[0]);
	  echo '</td><td>';
	  printf("%7.2f", $e_sum[0] / $e_cnt[0]);
	  echo '</td></tr>'.PHP_EOL;
	?>
	</tbody>
	</table>
	<br>

	<table border>
	<thead>
	<tr>
	<th width=170> Barometer </span> </th>
	<th width=100> min </span> </th>
	<th width=100> max </span> </th>
	<th width=100> average </span> </th>
	</tr>
	</thead>
	<tbody>
	<?php
	  echo '<tr><td>'.$sensor_name[1].'</td><td>';
	  printf("%7.2f", $e_min[1]);
	  echo '</td><td>';
	  printf("%7.2f", $e_max[1]);
	  echo '</td><td>';
	  printf("%7.2f", $e_sum[1] / $e_cnt[1]);
	  echo '</td></tr>'.PHP_EOL;
	?>
	</tbody>
	</table>
	//////////////////////////
</body>
</html>

