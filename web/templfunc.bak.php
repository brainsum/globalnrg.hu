<?php
    /*
	HTML sablon ertelmezo v2.3b
	Copyright (C)2000 Nagy Balazs <julian7@lsc.hu>, LGPL
	2000-08-07. Nagy Balazs. Written. v1.0.
	2000-08-09. Nagy balazs. RE -> string functions
	2000-08-10. Nagy Balazs. Classify. v1.1.
	2000-08-16. Nagy Balazs. Eliminated directory name inheriting.
	2001-09-07. KARASZI Istvan. don't replace empty [VARS].
	2002-02-23. KARASZI Istvan. recursive parsing! v2.1.
	2002-03-07. KARASZI Istvan. if in iter fix. v2.2.
	2002-03-19. KARASZI Istvan. command in one line only. v2.3.
	2002-03-20. KARASZI Istvan. Pedro's parsee function include for adverticum. v.2.3b.
    */

    class Template {
	var $variables;
	var $flush;

	function Template($variables, $flush=0) {
	    if (is_array($variables))
		$this->variables = $variables;
	    else
		$this->variables = array();
	    $this->flush = $flush;
	}

	function findvar($str, $base = "", $origpos=0) {
	    $varname = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
	    if (strlen($base))
		$base = '['.strtoupper($base)."-";
	    else
		$base = '[';
	    $baselen = strlen($base);
	    while(($pos = strpos($str, $base, $origpos)) || !is_string($pos)) {
		if ($pos < $origpos)
		    break;
		$origpos = $pos + 1;
		if (!($pos2 = strspn(substr($str, $pos+$baselen), $varname)))
		    continue;
		if ($str[$pos+$baselen+$pos2] != ']')
		    continue;
		return array(substr($str, $pos+$baselen, $pos2), $pos, $baselen+$pos2+1);
	    }
	    return false;
	}

	function findcomment($str, $pos = 0, $cmd = 0, $params = 0) {
	    if ($cmd) {
		$cmd = strtolower($cmd);
		$params = strtolower($params);
	    }
	    while(1) {
	        $start = strpos($str, "<!--", $pos);
		if (gettype($start) == "boolean" || is_string($start))
		    return false;
		if (!($stop = strpos($str, "-->", $start)))
		    return false;
		$comment = substr($str, $start+4, $stop-$start-4);
		if (strpos($comment, "\n") !== false) {
			$pos++;
			continue;
		}

		if ($cmd) {
		    if ((list($ncmd, $nparams) = $this->parse_arguments($comment))
		     && !strcasecmp($cmd, $ncmd) && !strcasecmp($params, $nparams)) {
			break;
			} else {
				if ($pos > $stop) return false;
				$pos = $stop;
				continue;
		    	}
		} else break;
	    }
	    return array ($start, $stop+3-$start);
	}

	function get_arrayvar($var, $counts) {
		$thing=false;
		$bases=split("-", $var);
		if (is_array($this->get_var($bases[0]))) {
			$thing=$this->get_var($bases[0]);
			$thing=$thing[$counts[0]];
			if (sizeof($counts) > 1) {
				for($i=1; $i < sizeof($bases) && $i < sizeof($counts); $i++) {
					$thing=$thing[$bases[$i]][$counts[$i]];
				}
			}
			$var=$bases[sizeof($bases)-1];
			$thing=$thing[$var];
		}
		return($thing);
	}

	function get_var($var, $varbase="", $counts=0, $if=0) {
	    if ($varbase && !$if) $var=$varbase.'-'.$var;
	    if (strlen($varbase)) {
		$val=$this->get_arrayvar($var, $counts);
		if (!isset($val)) return(false);
	    	return($val);
	    }
	    if ($pos = strpos($var, '=')) {
		return substr($var, $pos+1);
	    }
	    if (!isset($this->variables[$var]))
		return false;
	    else 
		return $this->variables[$var];
	}

	function parse_arguments($str) {
	    $str = trim($str);
	    $alpha = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
	    if (!($cmdpos = strspn($str, "$alpha")))
		return false;
	    if ($str[$cmdpos] != ' ' and $str[$cmdpos] != '-')
		return false;
	    $cmd = strtolower(substr($str, 0, $cmdpos));
	    $args = strtolower(substr($str, $cmdpos+1));
	    return(array($cmd, $args));
	    // removed the $str -- KARASZI Istvan
	    //return array($cmd, $args, $str);
	}

	function parse_variables($str, $varbase="", $counts=0) {
	    $start=0;
	    while(list($var, $start, $len) = $this->findvar($str, $varbase, $start)) {
		$var = strtolower($var);
		$val = $this->get_var($var, $varbase, $counts);
		if ($val !== false) $str = substr_replace($str, $val, $start, $len);
		elseif (PARSEE==1) $str = substr_replace($str, "", $start, $len);
		$start+=strlen($val)+1;
	    }
	    return $str;
	}

	function parse_string($str, $base="", $varbase="", $counts=0) {
	    $str = $this->parse_variables($str, $varbase, $counts);
	    $prevstart = 0;
	    while (list($start, $len) = $this->findcomment($str, $prevstart)) {
		if (list($cmd, $args) = $this->parse_arguments(substr($str, $start+4, $len-7))) {
		    $invert = 0;
		    $cmd = strtolower($cmd);
		    $parsed = false;
		    switch($cmd) {
			
		    case "include":
			$parsed = $this->parse("$base/$args");
			if (!$parsed)
			    $parsed = "";
			break;
			
		    case "ifnot":
			$invert = 1;
			
		    case "if":
			if ((list($endstart, $endlen) = $this->findcomment($str, $start+$len, "endif", $args))) {
			    if ((list($elsestart, $elselen) = $this->findcomment($str, $start+$len, "else", $args))
			     && $elsestart < $endstart) {
				$yes = substr($str, $start+$len, $elsestart - $start-$len);
				$no = substr($str, $elsestart+$elselen, $endstart - $elsestart-$elselen);
			    } else {
				$yes = substr($str, $start+$len, $endstart - $start-$len);
				$no = "";
			    }
			    $len = $endstart - $start + $endlen;
			    //if ($this->get_var($args, $varbase, $counts, 1)) {
			    if (strpos($args, "-") !== false) $varbase2=$varbase; else $varbase2="";
			    if ($this->get_var($args, $varbase2, $counts, 1)) {
				$parsed=(!$invert)?$yes:$no;
			    } else {
				$parsed=($invert)?$yes:$no;
			    }
			}
			break;
			
		    case "iterstart":
			if (list($endstart, $endlen) = $this->findcomment($str, $start+$len, "iterend", $args)) {
			    $iter = $this->get_var($args, $varbase, $counts);
			    $txt = substr($str, $start+$len, $endstart-$start-$len);
			    $parsed = "";
			    if (count($iter)) {
				if (!is_array($counts)) {
					$counts=array();
				}
				$last=sizeof($counts);
			    	for ($i = 0; $i < count($iter); ++$i) {
					$counts[$last]=$i;
					// a ciklusban ciklusnal bekerult egy \n a valasz elejere, ezt szedi ki ez: (by Pedro)
					$tempret=$this->parse_string($txt, $base, $args, $counts);
					if (substr($tempret,0,1)=="\n") $tempret=substr($tempret,1);
					$parsed .= $tempret;
			    	}
				array_pop($counts);
			    }
			    $len = $endstart - $start + $endlen;
			}
			break;
			
		    case "flush":
			$str = substr_replace($str, "", $start, $len);
			if ($this->flush && $start) {
			    print substr($str, 0, $start - 1);
			    $str = substr($str, $start);
			    $start = 0;
			}
			break;
		    }
		    if (is_string($parsed)) {
			$str = substr_replace($str, $parsed, $start, $len);
			$len = 0;
		    }
		}
		$prevstart = $start + $len;
	    }
	    return $str;
	}

	function parse($fname) {
	    $base = dirname($fname);
	    if (file_exists("$fname")) {
		$file = join("", file("$fname"));
		return $this->parse_string($file, $base);
	    } else { error_log("Template is missing: '$fname'"); }
	}
    }

    /* Own API */
    function parse($fname, $vars=0, $flush=1) {
	$template = new Template($vars, $flush);
	if ($flush)
	    print $template->parse($fname);
	else
	    return $template->parse($fname);
    }

    /* parse empty vars too */
    function parsee($fname, $vars=0, $flush=1) {
	define(PARSEE,1);
	parse($fname,$vars,$flush);
    }


    /* Compatibility function with Prim's API */
    function template($fname, $rvars, $itvars, $tofile="") {
	$vars = array_merge($rvars, $itvars);
	if ($tofile == "str")
	    $flushp = 0;
	else
	    $flushp = 1;
	return parse($fname, $vars, $flushp);
    }
?>
