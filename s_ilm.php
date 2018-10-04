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
$sensor_name = array( 'I-TSL2561f-01', 'I-TSL2561v-01', 'I-TSL2561ir-01', 'I-BH1750FV1-01' );

$tbl_I = '';
$e_cnt = array();
$e_sum = array();
$e_max = array();
$e_min = array();

// for ($sensor_id = 0; $sensor_id < count($sensor_name) ; $sensor_id++) {
foreach ($sensor_name as $sensor) {
	$e_cnt[$sensor] = 0;
	$e_sum[$sensor] = 0.0;
	$e_max[$sensor] = -10000.0;
	$e_min[$sensor] = +10000.0;
}

// query measured Temperature record
//
$query = $datastore->query()
  ->kind('sensDat2')
  ->filter('timestamp', '>', $d1->getTimeStamp())
  ->filter('timestamp', '<', $d2->getTimeStamp())
  ->order('timestamp') ;

$result = $datastore->runQuery($query);

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
    $tbl_I .= '	['.$_time.', ';

    foreach ($sensor_name as $sensor) {

	if (($value = $sensData[$sensor]) != NULL) {
	    $e_sum[$sensor] += $value;
	    $e_cnt[$sensor] ++;
	    $e_max[$sensor] = max($e_max[$sensor], $value);
	    $e_min[$sensor] = min($e_min[$sensor], $value);
	    $tbl_I .= $value .',';
	}
	else
	    $tbl_I .= 'null ,';
    }

    $tbl_I .= '],'.PHP_EOL;
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

	var data_I, opt_I, chart_I, view_I;

	function drawCharts() {
		drawChart_I();
	}

	function drawChart_I() {
		data_I = new google.visualization.DataTable();
		data_I.addColumn('datetime', 'Time');
		<?php
		    foreach ($sensor_name as $sensor) {
			echo "	data_I.addColumn('number', '" .$sensor. "');".PHP_EOL;
		    }
		?>
		data_I.addRows([
			<?php echo $tbl_I; ?>
		]);

		view_I = new google.visualization.DataView(data_I);

		opt_I = { 
			title: 'Illuminance',
			legend: { position: 'in' },
			vAxis: { viewWindow: {min: null, max: null, },
				 minorGridlines: { count: 3, color: '#E6E6FA' } },
			hAxis: { minorGridlines: { count: 3, color: '#E6E6FA' },
				 viewWindow: {min: new Date(<?php echo $d1->format('Y,m,d,H,i,s') ?>),
				 	      max: new Date(<?php echo $d2->format('Y,m,d,H,i,s') ?>), } },
			height: '350',
			width:  '100%'
		};

		chart_I = new google.visualization.LineChart(
					document.getElementById('linechart_I'));

		var sel = document.form_I.sensor_I
		var setC = new Array();
		setC[0] = 0;
		for (var i = 0; i < sel.length ;  i++) {
			if (sel[i].selected) setC.push( i+1 );
		}
		view_I.setColumns(setC);
		chart_I.draw(view_I, opt_I);
	}

	function changeColumn(_sel, _chart, _view, _opt) {
		var setC = new Array();
		setC[0] = 0;
		for (var i = 0; i < _sel.length; i++) {
			if (_sel[i].selected) setC.push( i+1 );
		}
		_view.setColumns(setC);
		_chart.draw(_view, _opt);
	}

	function changeScale(mode, _chart, _view, _opt) {
		if (mode == 'fixed') {
		    if (_chart == chart_I) {
			_opt['vAxis']['viewWindow']['min'] = 0;
			_opt['vAxis']['viewWindow']['max'] = 1000;
		    }
		}
		else {
			_opt['vAxis']['viewWindow']['min'] = null;
			_opt['vAxis']['viewWindow']['max'] = null;
		}
		_chart.draw(_view, _opt);
	}

	function changeSize(mode, _chart, _view, _opt) {
		if (mode == 'small') {
			_opt['height'] = 150;
		}
		else if (mode == 'medium') {
			_opt['height'] = 350;
		}
		else {
			_opt['height'] = 700;
		}
		_chart.draw(_view, _opt);
	}


	function goto_other_page(page, date, span) {
		if (page == '_Home') {
			nexturl = "index.html";
		}
		else {
			nexturl = page+"?_start="+date+"&_span="+span;
		}
		location.href = nexturl;
	}

	</script>

	<style type="text/css">
	    span._static {
		padding-left:12pt;
	    }
	    .header_item {
		height: 18pt;
	    }
	</style>

</head>

<!--
/////////////////////////////////////////////////////////////////////////////////////////
//	BODY
/////////////////////////////////////////////////////////////////////////////////////////
-->
<body onresize="chart_I.draw(view_I, opt_I);">
	<h2>Illuminance Sensors (<?php 
	$date = $d1->format('Y/m/d');
	if ($_span > 1) $date .= " - ".$d2->format('Y/m/d');
	echo $date;
	?>)</h2>

	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	header form
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	<form name="HeaderForm">
	<table>
	<tr>
	  <td width=100>
	      <input type='date' class='header_item' name='Goto_Date' value='<?php
	    	echo $d1->format('Y-m-d') ?>'>
	  </td>
	  <td>
	    <select name='dispatch_page' class='header_item' id='dispatch_page' onchange='set_submit_target(this.value)'>
	      <option value='primary_sensors.php'>Primary sensors</option>
	      <option value='node_emily.php'>Node Emily</option>
	      <option value='s_temp.php'>Temperature Sensors</option>
	      <option value='s_ilm.php' selected >Illminance Sensors</option>
	      <option value='temp_barometer.php'>(old) temp & barometer</option>
	    </select>
	  </td>
	  <td>
	    <span style="padding-right: 25pt">
	      <button type = "button" class='header_item' onclick="goto_other_page(form.dispatch_page.value, form.Goto_Date.value, form.Span.value)">
	      GOTO
	      </button>
	    </span>
	  </td>
	  <td>
	    <button type = "button" class='header_item' onclick="goto_other_page(form.dispatch_page.value, '<?php
	    	$d3 = clone $d1;
	    	$d3->sub(new DateInterval('P01D'));
	    	echo $d3->format('Y-m-d H:i');  ?>', form.Span.value)" >Previous day</button>
	  </td>
	  <td>
	    <select class='header_item' name="Span">
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
	    <button type = "button" class='header_item' onclick="goto_other_page(form.dispatch_page.value, '<?php
	      $d3 = clone $d1;
	      $d3->add(new DateInterval('P01D'));
	      echo $d3->format('Y-m-d H:i');  ?>', form.Span.value)" >next day</button>
	  </td>
  	  <td>
	    <span style="padding-left: 30pt">
	    <A href="index.html">home</A>
	    </span>
	  </td>
	</tr>
	</table>
	</form>

	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	form I
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	<form name="form_I" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span class="_static">Sensors :</span>
	  <select name="sensor_I" multiple size=4 onchange="changeColumn(this, chart_I, view_I, opt_I)">
	    <?php
		foreach($sensor_name as $sensor) {
		    echo "<option value='".$sensor."' selected>".$sensor."</option>" . PHP_EOL;
		}
	     ?>
	  </select>
	  <span class="_static">Scaling :</span>
	  <select name="scale_I" onchange="changeScale(this.value, chart_I, view_I, opt_I)">
	    <option value="auto" selected>AUTO</option>
	    <option value="fixed">FIXED</option>
	  </select>
	  <span class="_static">Size :</span>
	  <select name="size_I" onchange="changeSize(this.value, chart_I, view_I, opt_I)">
	    <option value="small">small</option>
	    <option value="medium" selected >medium</option>
	    <option value="large">large</option>
	  </select>
	</form>

	<div id="linechart_I"></div>

	<hr>
	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	Summary table
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	//////////////////////////
	<?php echo "<h3>*** Record of the day (".$date.") ***</h3>\n"; ?>
	<table border>
	<thead>
	<tr>
	<th width=170>Illuminance</th>
	<th width=100>min</th>
	<th width=100>average</th>
	<th width=100>max</th>
	</tr>
	</thead>
	<tbody>
	<?php
	    foreach($sensor_name as $sensor) {
		echo '<tr><td>'.$sensor.'</td><td>';
		printf("%7.2f", $e_min[$sensor]);
		echo '</td><td>';
		printf("%7.2f", $e_sum[$sensor] / $e_cnt[$sensor]);
		echo '</td><td>';
		printf("%7.2f", $e_max[$sensor]);
		echo '</td></tr>'.PHP_EOL;
	    }
	?>
	</tbody>
	</table>
	<br>
	//////////////////////////
</body>
</html>

