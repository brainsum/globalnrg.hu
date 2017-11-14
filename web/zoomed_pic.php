<?php

include("templfunc.php");/**/
$ix["window_title"]="Mahïakova";
$ix["picurl"]=$picurl;
if ($zoomedtype=="movie") {
	header ($picurl);
	}
	else 
	{
	parse("popup_zoomed.htm",$ix);
}

$hello=getdate();
$fw = fopen ( "kep_log.txt","a");
fwrite ($fw,  $REMOTE_ADDR."\t".$hello["year"].".".$hello["mon"].".".$hello["mday"]."\t".$hello["hours"].":".$hello["minutes"]."\t".$kepURL."\r\n");/**/
fclose($fw);

?>