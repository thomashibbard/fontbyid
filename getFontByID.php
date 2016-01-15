<?php
//11057
header("Access-Control-Allow-Origin: *");
error_reporting(E_ALL);




if(!empty($_GET)){
	$__getFontSearchInput = $_GET["__getFontSearchInput"];
	$__getFontFn = $_GET["__getFontFn"];
}else{

	die(json_encode(
			array(
				"success"	=> false,
				"error"		=> "missing query string parameters"
			)
		)
	);

}



//cast to string
$__getFontSearchInput = (string) $__getFontSearchInput;
function valueFromXMLAttr($attrs){
	$src = json_decode(json_encode( (array) $attrs), TRUE);
	return $src[0];
}

$fontXML = file_get_contents(/*OBFUSCATED*/);
$xml = simplexml_load_string($fontXML); 
//echo var_dump($xml->fonts);
//print_r($xml->children());
$result = array("success" => false);
if ($__getFontFn == "fontID"){
	//search by fontID
	foreach($xml->children() as $parent){
		$parentName = $parent->getName();
		foreach($parent->children() as $font){
			foreach ($font->attributes() as $attributeKey => $attributeVal){
				if (strtolower($attributeKey) == "id" && (string) $attributeVal == $__getFontSearchInput){
					$attrs = $font->attributes();
					$result["success"] = true;
					$result["fontName"] = valueFromXMLAttr($attrs["name"]);
					$result["fontFileName"] = valueFromXMLAttr($attrs["src"]);
					$result["type"] = valueFromXMLAttr($parentName);
					if ($parentName == "fonts"){
						$result["fontStyle"] = valueFromXMLAttr($attrs["style"]);
					}else{
						//get IDs of children font styles
						$result["fontStyle"] = false;
						$result["childCuts"] = array();
						$result["childCuts"]["regID"] = isset($attrs["reg"]) ? valueFromXMLAttr($attrs["reg"]) : false;
						$result["childCuts"]["italicID"] = isset($attrs["italic"]) ? valueFromXMLAttr($attrs["italic"]) : false;
						$result["childCuts"]["boldID"] = isset($attrs["bold"]) ? valueFromXMLAttr($attrs["bold"]) : false;
						$result["childCuts"]["boldItalicID"] = isset($attrs["boldItalic"]) ? valueFromXMLAttr($attrs["boldItalic"]) : false;
					}
				}
			}
		}
	}

}else{
	//search by fontName

	//font weights are stored in a separate attribute than font name
	//store them to give precendence to fonts which match both
	$fontWeightsRe = "/bold|italic|bolditalic|bold-italic|roman|oblique/i";
	//make an array of these same font weights to elimiate them from the string
	//also delete the leading and trailing forward slashes and the `i` flag
	$fontWeightsArr = explode("|", substr($fontWeightsRe, 1, -2));

	preg_match_all($fontWeightsRe, $__getFontSearchInput, $weightsMatches);
	//remove them from the string that will exist in the name attribute
	//str_replace accepts an array
	$__getFontSearchInput = str_ireplace($fontWeightsArr, "", trim($__getFontSearchInput));

	//construct simple OR regex by replacing spaces with pipes
	//TODO delete -> regex not needed. 
	$__getFontSearchInput = preg_replace("/\s+/", "|", trim($__getFontSearchInput));


	//echo var_dump($xml->fonts);
	//print_r($xml->children());
	$result = array("success" => false);
	$resultsSearchFontNameFirstRound = array(
		"success"		=> false, 
		"fonts"			=> array(),
		"font_families"	=>array()
		);

	foreach($xml->children() as $parent){
		$parentName = $parent->getName();
		foreach($parent->children() as $font){
			$attrsArr = array();
			foreach ($font->attributes() as $attributeKey => $attributeVal){
				//associative array of each font or font_family's attibutes (attrs["name"] is the font name)
				//$font->getName() will always be `font` or `font_family`
				$attrsArr[$attributeKey] = valueFromXMLAttr($attributeVal);
			}
			//simple OR regex with provided search terms
			$re = "/.*?" . $__getFontSearchInput . ".*?/i";
			if (preg_match($re, $attrsArr["name"], $matches)){
				$resultsSearchFontNameFirstRound["success"] = true;
				if ($font->getName() == "font"){
					array_unshift($resultsSearchFontNameFirstRound["fonts"], array(
							"type"		=> "font",
							"fontID"	=> $attrsArr["id"],
							"fontName"	=> $attrsArr["name"],
							"fontStyle"	=> $attrsArr["style"],
							"fontSrc"	=> $attrsArr["src"]
						)
					);
				}else{
					array_unshift($resultsSearchFontNameFirstRound["font_families"], array(
							"type"		=> "font_family",
							"fontID"	=> $attrsArr["id"],
							"fontName"	=> $attrsArr["name"]
						)
					);
				}
			}
		}
	}

	//for `font` nodes (not font_families) push those to the to	top of the stack which contain
	//style attributes that are also found in the search term within the array `$weightsMatches 
	//construct simple OR regex by replacing spaces with pipes
	$weightsMatches = implode("|", $weightsMatches[0]);
	//echo sizeof($resultsSearchFontNameFirstRound["fonts"]) . "\n"; //DEBUG is 54
	if ($resultsSearchFontNameFirstRound["success"] == true){
		$resultsSearchFontNameFinal = array(
			"success"		=> true,
			"fonts"			=>array(),
			"font_families" => $resultsSearchFontNameFirstRound["font_families"]
			);
		foreach ($resultsSearchFontNameFirstRound["fonts"] as $fontKey => $fontVal){
			if (preg_match("/^.*?" . $weightsMatches . ".*?$/i", $fontVal["fontStyle"], $matches)){
				array_push($resultsSearchFontNameFinal["fonts"], $fontVal);
				//elminate the matched elements from the original array
				unset($resultsSearchFontNameFirstRound["fonts"][$fontKey]);
			}
		}
		//echo sizeof($resultsSearchFontNameFirstRound["fonts"]) . "\n"; //DEBUG is 47
		//put the elements that didn't match BOTH attributes (name and style) at the end of the final array
		foreach($resultsSearchFontNameFirstRound["fonts"] as $font){
			array_push($resultsSearchFontNameFinal["fonts"], $font);
		}
		//echo "final array size: " . sizeof($resultsSearchFontNameFinal["fonts"]); //DEBUG should be 54

	}

	//echo json_encode($resultsSearchFontNameFinal["fonts"]);
	//just for consistancy
	//TODO update to be more homogenous with statements for font IDs above
	$result = $resultsSearchFontNameFinal;
}

echo json_encode($result);









