<?php

$url = "http://www.webhostingstatus.com";

// Copied from the internetz
function innerHTML( $contentdiv ) {
	$r = '';
	$elements = $contentdiv->childNodes;
	foreach( $elements as $element ) {
		if ( $element->nodeType == XML_TEXT_NODE ) {
			$text = $element->nodeValue;
			// IIRC the next line was for working around a
			// WordPress bug
			//$text = str_replace( '<', '&lt;', $text );
			$r .= $text;
		}
		// FIXME we should return comments as well
		elseif ( $element->nodeType == XML_COMMENT_NODE ) {
			$r .= '';
		}
		else {
			$r .= '<';
			$r .= $element->nodeName;
			if ( $element->hasAttributes() ) {
				$attributes = $element->attributes;
				foreach ( $attributes as $attribute )
					$r .= " {$attribute->nodeName}='{$attribute->nodeValue}'" ;
			}
			$r .= '>';
			$r .= innerHTML( $element );
			$r .= "</{$element->nodeName}>";
		}
	}
	return $r;
}
class ZeroLengthObject {
	public $length = 0;
}

// HTML email head code
$html_start = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\" \"http://www.w3.org/TR/REC-html40/loose.dtd\">
<html>
<head>
<title>Heart Internet Status Update</title>
</head>
<body style=\"font-family: Arial, Helvetica; font-size: 10pt; color: #666;\">
<div id=\"heartinternet\">
<ul style=\"list-style-type: none;\">\n";

// Get the page using CURL
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
$html = curl_exec($curl);
$ulHTML = '';

if ($html !== false) {

	$doc = new DOMDocument();
	if (@$doc->loadHTML($html)) {

		// Find DIV elements in container
		$container = $doc->getElementById('container');
		$divs = ($container !== null) ? $container->getElementsByTagName('div') : new ZeroLengthObject;
		for($i=0, $m=$divs->length; $i < $m; $i++) {
			$div = $divs->item($i);
			// Check for content
			if ($div->attributes->length > 0 and
				$div->attributes->getNamedItem('class')!=null and
				$div->attributes->getNamedItem('class')->value == "contentbox") {

				// Check for system status content
				$h2s = $div->getElementsByTagName('h2');
				if ($h2s->length > 0) {
					$h2 = $h2s->item(0);
					if ($h2->textContent == "Current System Status"){
						// The first nextSibling is the TEXT element from the H2 tag
						$ul = $h2->nextSibling->nextSibling;
						if ($ul->nodeName == "ul") {
							$ulHTML = innerHTML( $ul );
						}
					}
				}
			}
		}
	}
}

if ($ulHTML !== '') {
	$hash = md5($ulHTML);
	// Cache content and send email
	$seenBefore = false;
	$filename = dirname(__FILE__) . "/cache/$hash"; 
	if (!file_exists($filename)) {
		$fh = fopen($filename, 'w');
		if ($fh !== false) {
			fwrite($fh, $ulHTML);
			fclose($fh);
		}
		// Apply inline CSS
		$ulHTML = str_replace("<li>", "<li style=\"margin: 15px 0;\">", $ulHTML);
		$ulHTML = str_replace("<p class='heading'>", "<p style=\"background-color: #CACACA; width: 690px; padding: 3px 10px; color: #fff; font-weight: bold; margin-bottom: 10px;\">", $ulHTML);
		$ulHTML = str_replace("<p class='contentbox'>", "<p style=\"background-color: #fff; width: 710px; padding: 25px; margin-top: 20px;\">", $ulHTML);
		$ulHTML = str_replace("<p class='date'>", "<p style=\"font-weight:bold;\">", $ulHTML);
		$ulHTML = str_replace("<p class='fixed'>", "<p style=\"background-color: #A3BC4A; border-radius: 5px 5px 5px 5px; color: #FFFFFF; font-weight: bold; margin-bottom: 10px; padding: 3px 10px; width: 690px;\">", $ulHTML);

		if (false and date("H:i:s") <= "18:00:00" and date("H:i:s") >= "08:30:00" and
			date("w") > "0" and date("w") < "6") {
			mail("Jon Bevan <contact@heartinternetstatus.com>",
					"Heart Internet Status Update",
					$html_start . $ulHTML . "</ul></div></body></html>", 
					"From: Heart Internet Status Monitor <hi@heartinternetstatus.com>\nMIME-Version: 1.0\nContent-type: text/html; charset=iso-8859-1\n");
		}
	} else {
		$seenBefore = true;
	}
}

$DB_HOST='';
$DB_NAME='';
$DB_USER='';
$DB_PWD='';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PWD, $DB_NAME);

if ($mysqli->connect_error == 0) {

	$minute = date("i");
	$hour = date("H");
	$day = date("d");
	$month = date("m");
	$year = date("Y");

	$errors = 0;
	$mysql = 0;
	$mail = 0;
	$web = 0;
	$description = '';

	if ($ulHTML !== '' and $ul->nodeName == "ul") {

		$problems = array();
		$problems_index = 0;
		$liNodes = $ul->getElementsByTagName('li');
		foreach ($liNodes as $li) {
			$pNodes = $li->getElementsByTagName('p');
			foreach($pNodes as $p) {
				if ($p->attributes->length > 0 and
					$p->attributes->getNamedItem('class')!=null) {

					if ($p->attributes->getNamedItem('class')->value == "heading") {

						$web_preg = preg_match_all("/(hybrid|web|kvmhost)[0-9]+/i", $p->textContent, $web_matches);
						$mysql_preg = preg_match_all("/MySQL/i", $p->textContent, $mysql_matches);
						$mail_preg = preg_match_all("/(web)?mail[0-9]*/i", $p->textContent, $mail_matches);

						$problems[$problems_index]['description'] = $p->textContent;
						$problems[$problems_index]['mysql'] = count( $mysql_matches[0] ) > 0 ? count( $web_matches[0] ) : 0;
						$problems[$problems_index]['web'] = count( $mysql_matches[0] ) > 0 ? 0 : count( $web_matches[0] );
						$problems[$problems_index]['mail'] = count( $mail_matches[0] );

					} else if ($p->attributes->getNamedItem('class')->value == "fixed") {
						$problems[$problems_index]['fixed'] = true;
					}
				}
			}
			$problems_index++;
		}
		foreach($problems as $problem) {
			if (!isset($problem['fixed'])) {
				$errors += ( $problem['mail'] + $problem['web'] + $problem['mysql']);
				$mail += $problem['mail'];
				$web += $problem['web'];
				$mysql += $problem['mysql'];
				$description .= $problem['description'] . "|";
			}
		}
		$description = $mysqli->real_escape_string(substr($description, 0, -1));

	}

	$mysqli->query("INSERT INTO uptime (errors,web,mail,mysql,description,minute,hour,day,month,year)
					VALUES ($errors,$web,$mail,$mysql,'$description','$minute','$hour','$day','$month','$year')");

}
?>
