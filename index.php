<!DOCTYPE html>
<html>
<head>
	<title>Heart Internet Status Page</title>
	<link rel='icon' type='image/ico' href='favicon.ico'>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="css/bootstrap.min.css" rel="stylesheet" media="screen">
	<style type="text/css">
	body { width: 800px; margin: 0 auto; text-align: center; background-color: #fefefe;}
	table { margin: 0 auto; }
	.btn { cursor: default; }
	h1 { margin: 30px inherit; vertical-align: bottom; }
	h1 a { color: inherit; vertical-align: inherit; }
	h1 a:hover { text-decoration: none; color: inherit; }
	h1 a img { vertical-align: top; }
	th { font-size: 16px; padding: 10px; }
	tr span { font-size: 14px; color: silver; font-weight:normal; }
	p { margin: 20px;}
	tr p, th p { margin: 0; }
	</style>
</head>
<?php flush(); ?>
<body>

	<h1><a href=""><img src="img/hi_logo.png" alt="Heart Internet"> Unofficial Status Page</a></h1>
	<p><a href="http://www.webhostingstatus.com/">Go to the official Status and Maintenance Page</a></p>

<?php
$DB_HOST='';
$DB_NAME='';
$DB_USER='';
$DB_PWD='';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PWD, $DB_NAME);

if ($mysqli->connect_error == 0) {
?>
	<table class="table-striped">
		<tbody>
			<thead>
				<tr><th colspan="6">Last updated <?php echo date("H:ia"); ?><br><span>This page will update itself in 5 minutes.</span></th></tr>
			</thead>
<?php

	$minute = date("i");
	$hour = date("H");
	$day = date("d");
	$month = date("m");
	$year = date("Y");

	$time_diff = strtotime("-6 days");

	$start_year = date("Y", $time_diff);
	$start_month = date("m", $time_diff);
	$start_day = date("d", $time_diff);
	$end_year = date("Y");
	$end_month = date("m");
	$end_day = date("d");


	if ($end_month == $start_month) {
		$where = "year = $start_year AND month = $start_month AND day >= $start_day";
	} else {
		$where = "(year = $start_year AND month = $start_month AND day >= $start_day) OR (year = $end_year AND month = $end_month AND day <= $end_day)";
	}


	// 3 days, by hour
	$_3hours = $mysqli->query("SELECT MAX(errors) as errors, MAX(web) as web, MAX(mail) as mail, MAX(mysql) as mysql,
								GROUP_CONCAT(DISTINCT IF(description!='',description,null) SEPARATOR '|') as info,
								year, month, day, hour
								FROM uptime
								WHERE $where
								GROUP BY year DESC, month DESC, day DESC, hour DESC");

	$cur_day = "0000-00-00";
	while ($row = $_3hours->fetch_assoc()){
		$date = $row['year'] . "-" . $row['month'] ."-" . $row['day'];
		if ($cur_day != $date) {

			if ($cur_day != "0000-00-00") {
				$up_year = date("Y", strtotime($cur_day));
				$up_month = date("m", strtotime($cur_day));
				$up_day = date("d", strtotime($cur_day));
				$uptime_calculator = $mysqli->query("SELECT COUNT(*) AS minutes, SUM(errors>0) as errors,
														SUM(web/248) as web, SUM(mail/83) as mail, SUM(mysql/248) as mysql
													FROM uptime
													WHERE year = $up_year AND month = $up_month AND day = $up_day");
				if ($uptime_calculator){
					$uptime_row = $uptime_calculator->fetch_assoc();
?>
			<tr>
				<td>Up:</td>
				<td><span class="btn btn-info"><?php echo number_format(100 - ($uptime_row['errors'] / $uptime_row['minutes'] * 100), 2); ?>%</span></td>
				<td><span class="btn btn-info"><?php echo number_format(100 - ($uptime_row['web'] / $uptime_row['minutes'] * 100), 2); ?>%</span></td>
				<td><span class="btn btn-info"><?php echo number_format(100 - ($uptime_row['mail'] / $uptime_row['minutes'] * 100), 2); ?>%</span></td>
				<td><span class="btn btn-info"><?php echo number_format(100 - ($uptime_row['mysql'] / $uptime_row['minutes'] * 100), 2); ?>%</span></td>
				<td>Based on 83 mail servers and<br>248 web + database servers</td>
			</tr>
			<tr>
				<td colspan="6">&nbsp;</td>
			</tr>
<?php
				}
			}

			$cur_day = $date;
?>
			<tr>
				<th colspan="6"><?php echo date("D jS M Y", strtotime($date)); ?></th>
			</tr>
			<tr>
				<th>Hour</th>
				<th>Total</th>
				<th>Web</th>
				<th>Email</th>
				<th>Database</th>
				<th>Information</th>
			</tr>
<?php
		}

		$ok_msgs = array("All systems are go!", "It's all good...", "All systems online!", "Nothing to report.",
						"It's all good...", "Nothing to report.", "All systems are go!",
						"Systems are operational.", "Everything is fine.", "Ready for action.", "Nothing is broken.");

		$info = array("<span>" . $ok_msgs[ ($row['day'] + $row['hour']) % count($ok_msgs) ] . "</span>");
		if ($row['info'] != null) {
			$info = explode("|", $row['info']);
			$info = array_unique($info);
			// If there are more individual errors then concurrent
			// update our error count
			if (count($info) > $row['errors']) {
				$row['errors'] = count($info);
			}
		}

		$class = ($row['errors'] > 0) ? "btn-danger" : "btn-success";
		$web_class = ($row['web'] > 0) ? "btn-warning" : "btn-success";
		$mail_class = ($row['mail'] > 0) ? "btn-warning" : "btn-success";
		$db_class = ($row['mysql'] > 0) ? "btn-warning" : "btn-success";
?>
			<tr>
				<td><?php echo sprintf("%02d:00", $row['hour']); ?></td>
				<td><span class="btn <?php echo $class; ?>"><?php echo $row['errors']; ?></span></td>
				<td><span class="btn <?php echo $web_class; ?>"><?php echo $row['web']; ?></span></td>
				<td><span class="btn <?php echo $mail_class; ?>"><?php echo $row['mail']; ?></span></td>
				<td><span class="btn <?php echo $db_class; ?>"><?php echo $row['mysql']; ?></span></td>
				<td><?php echo implode(", ", $info); ?></td>
			</tr>
<?php
	}
	if ($cur_day != "0000-00-00") {
		$up_year = date("Y", strtotime($cur_day));
		$up_month = date("m", strtotime($cur_day));
		$up_day = date("d", strtotime($cur_day));
		$uptime_calculator = $mysqli->query("SELECT COUNT(*) AS minutes, SUM(errors>0) as errors,
												SUM(web/248) as web, SUM(mail/83) as mail, SUM(mysql/248) as mysql
											FROM uptime
											WHERE year = $up_year AND month = $up_month AND day = $up_day");
		if ($uptime_calculator){
			$uptime_row = $uptime_calculator->fetch_assoc();
	?>
	<tr>
		<td>Up:</td>
		<td><span class="btn btn-info"><?php echo number_format(100 - ($uptime_row['errors'] / $uptime_row['minutes'] * 100), 2); ?>%</span></td>
		<td><span class="btn btn-info"><?php echo number_format(100 - ($uptime_row['web'] / $uptime_row['minutes'] * 100), 2); ?>%</span></td>
		<td><span class="btn btn-info"><?php echo number_format(100 - ($uptime_row['mail'] / $uptime_row['minutes'] * 100), 2); ?>%</span></td>
		<td><span class="btn btn-info"><?php echo number_format(100 - ($uptime_row['mysql'] / $uptime_row['minutes'] * 100), 2); ?>%</span></td>
		<td>Based on 83 mail servers and<br>248 web + database servers</td>
	</tr>
	<?php
		}
	}

}
?>
		</tbody>
	</table>

	<p>Information crawled once per minute from <a href="http://www.webhostingstatus.com/">Heart Internet's official status and maintenance page</a>.</p>
	<p>Built for you by <a href="https://github.com/jdbevan">Jon Bevan</a> using <a href="http://pages.github.com/">Github Pages</a>, <a href="http://twitter.github.io/bootstrap/">Bootstrap</a> <strike>and <a href="http://developer.yahoo.com/yql/">YQL</a></strike>. No affiliation with <a href="http://www.heartinternet.co.uk">Heart Internet</a>.</p>

	<script src="http://code.jquery.com/jquery.js"></script>
	<script>
	$(function() {
		function updateTimer (t) {
			var msg = config.update_msg,
				intMin = parseInt( $("table thead span").text().replace(/[^0-9]/g, '') );
			if (t != undefined) {
				msg += t;
				msg += (t == 1) ? " minute." : " minutes.";
				$("table thead span").text(msg);
			} else if (intMin !== NaN) {
				msg += --intMin;
				msg += (intMin == 1) ? " minute." : " minutes.";
				$("table thead span").text(msg);
			}
		}
		function updatePage () {
			// var q = encodeURIComponent("SELECT * FROM html WHERE url='https://raw.github.com/jdbevan/heartinternetstatus/gh-pages/index.html'");

			// Prevent the 0 minutes displaying
			clearInterval(config.countdown_timer);

			/*
			 * Github added a robots.txt to prevent scraping content
			 * Maybe I'll get round to using their API sometime
			 *
			$.ajax({
				timeout: 5*1000,	// Required to throw an error when script doesn't load
				dataType: "json",
				url: "http://query.yahooapis.com/v1/public/yql?q=" + q + "&format=xml&callback=?",
				success: function(data) {
					if (data.results.length > 0) {
						var html = $.parseHTML(data.results[0]);
						for(var i=0, m=html.length; i<m; i++){
							if (html[i].nodeName.toLowerCase() === "table") {
								$("table").replaceWith(html[i]);
								// Make sure HTML displaying correct countdown time to next refresh
								updateTimer(config.update_interval);
								// Reset the countdown timer
								config.countdown_timer = setInterval(updateTimer, config.countdown_interval*60*1000);
								break;
							}
						}
						ga('send', 'event', 'YQL', 'load-success', {'nonInteraction':1});
					}
					config.update_timer = setTimeout(updatePage, config.update_interval*60*1000);
				},
				error: function(){
					$("table thead span").text("Uh-oh, spaghetti hoops... Let's try that again!");
					setTimeout(updatePage, 10*1000);
					ga('send', 'event', 'YQL', 'load-fail', {'nonInteraction':1});
				}
			});
			*/
			window.location.reload(true);
		}
		function start () {
			//updatePage();
			config.update_timer = setTimeout(updatePage, config.update_interval*60*1000);
			config.countdown_timer = setInterval(updateTimer, config.countdown_interval*60*1000);
		}
		var config = {
			update_msg: "This page will update itself in ",
			update_timer: null,
			update_interval: 5,
			countdown_timer: null,
			countdown_interval: 1,
		};
		start();
	});
	</script>
</body>
</html>
