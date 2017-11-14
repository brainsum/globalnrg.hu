<?php


include("templfunc.php");/**/

$lang = $_GET['lang'];
$a = $_GET['a'];
//----------------------------------------------------file valasztas ---------------

if (!isset($lang)) $lang="hu";
if ($lang=="hu") {
		$ix["lang"]="hu";
		$ix["lang_other"]="en";

		$iix["lang"]="hu";
		$iix["lang_other"]="en";
	} else {
		$ix["lang"]="en";
		$ix["lang_other"]="hu";

		$iix["lang"]="en";
		$iix["lang_other"]="hu";
	}

if (!isset($a)) $a="main_page";

$filename_array = array (""=>"main_page",
	"main_page"=>"main_page",
	"bemutatkozas"=>"bemutatkozas",
	"elerhetosegek"=>"elerhetosegek",
	"szolgaltatasok"=>"szolgaltatasok",
	"hirek"=>"hirek",
	"cegadatok"=>"cegadatok",
	"ugyfelszolgalat"=>"ugyfelszolgalat",
	"uzletszabalyzat"=>"uzletszabalyzat",
	"dokumentumok"=>"dokumentumok",
	"2006-evi-beszamolo"=>"2006-evi-beszamolo",
	"galeria-turaauto2017"=>"galeria-turaauto2017",
	"tanusitok"=>"tanusitok",
	"belepes"=>"belepes",
);

//printf ($iix["lang"]);
$ix["content"] = parse ($lang."/".$filename_array[$a].".html",$iix,0);

parse("template.html",$ix);

?>
