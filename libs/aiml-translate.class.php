<?php
/*
 * AIMLTranslate with Google Translate API
 * 
 * Author: Leamsi Fontánez - lfontanez@r1software.com
 * Copyright (c) 2017 R1 Software
 *
 * Requires google-translate-class.php
 */
class Translate
{
    var $googleTranslate;
    var $format;
    var $parser;
    var $uploads;
    var $downloads;
    var $save;
    
    public function __construct($apikey, $apiurl=false) {
        
        // Some default settings
        $this->format = 'xml';
        $this->parser = 'basic';
        $this->uploads = './uploads/';
        $this->downloads = './downloads/';
        $this->save = true;
        
		if (!$apiurl) $apiurl = 'https://www.googleapis.com/language/translate/v2';
		
		require_once('google-translate-class.php');
        $this->googleTranslate = new GoogleTranslate($apikey, $apiurl);
        
	}

	public function set_format($format) {
    	$this->format = $format;
    }
	
	public function get_format() {
		return $this->format;
	}  
	
	public function set_parser($parser) {
    	$this->parser = $parser;
    }
	
	public function get_parser() {
		return $this->parser;
	}  

	public function set_uploads($ul) {
    	$this->uploads = $ul;
    }
	
	public function get_uploads() {
		return $this->uploads;
	}  
	
	public function set_downloads($dl) {
    	$this->downloads = $dl;
    }
	
	public function get_downloads() {
		return $this->downloads;
	} 
	
	public function set_save($save) {
    	$this->save = $save;
    }
	
	public function get_save() {
		return $this->save;
	} 
	
    /*
     *
     * @param string $file AIML Filename
     * @param string $sl Source Language (2 letters)
     * @param string $tl Target Language (2 letters)
     * @param bool $save Save New AIML File
     */
    public function translateAIML ($file, $sl, $tl) {

        $rustart = getrusage();
    
        $original = array();
        $translated = array();
        $falsefails=0;
        
        $xml = file_get_contents($this->uploads . $file);
        $xml = $this->prepareXML($xml);

        //$this->debug($xml,false);
        //exit;
     
        switch ($this->parser) {
            case "xml":

                $xmlDoc = new DOMDocument();
                $xmlDoc->loadXML($xml);
                $xml2 = $xmlDoc->documentElement;
        
                $this->xmlParser($xml2, $sl, $tl, $original, $translated, $falsefails);               
                break;
                
            default:

                $this->basicParser($xml, $sl, $tl, $original, $translated, $falsefails);
        }

        $results = $this->compare($original, $translated, $falsefails);

        $xml = str_replace($original, $translated, $xml);
        $results['xml'] = $this->formatXML($xml);
        
        if ($this->save) {
            $file = explode('.aiml',$file);
            $new_file = $file[0]."-".$tl.".aiml";
            file_put_contents($this->downloads . $new_file, $xml);
            $results['filename'] = $new_file;
            $results['download_link'] = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}{$this->downloads}$new_file";
        }

        $ru = getrusage();
        
        $bench  = "This process used " . $this->rutime($ru, $rustart, "utime") .
            " ms for its computations. ";
        $bench .=  "It spent " . $this->rutime($ru, $rustart, "stime") .
            " ms in system calls.";
            
        $results['stats']['benchmark'] = $bench;
        
        return $results;
    }

    private function basicParser ($xml, $sl, $tl, &$ori, &$tran, &$falsef) {
        
        $gt = $this->googleTranslate;
        $tags = array();
        preg_match_all('|<[^>]+>([^<>]+)<\/[^>]+>|', $xml, $tags, PREG_SET_ORDER);

        foreach ($tags as $tag) {
            
            $text = $this->clean($tag[1]);
            
            if ($text != '') {
                
                $ori[] = trim($tag[0]);
                
                $json = $gt->translateThis($sl, $tl, $text);
                $json = json_decode($json, true);
                $translation = $json['data']['translations'][0]['translatedText'];
                
                if ($translation == $text) $falsef++;
                
                if (strpos($tag[0], '<pattern>') !== false) {
                    $translation = $this->normalizePattern($translation);
                }    
                
                $tran[] = str_replace($text, $translation, trim($tag[0]));
                usleep(500);
            }
        }

    }
    private function xmlParser ($el, $sl, $tl, &$ori, &$tran, &$falsef) {
        
        $gt = $this->googleTranslate;
        
        foreach ($el->childNodes as $item) {

            if ($item->childNodes) {
                
                $this->xmlParser($item, $sl, $tl, $ori, $tran, $falsef);
                
            } elseif ($item->nodeType == 3 ) {
                
                $inner = $item->ownerDocument->saveXML( $item );
                $text = $this->clean( $item->textContent );

                if ($text != '') {

                    $outer = $this->get_outer_xml($item);
                    $ori[] = trim($outer);
                    
                    $json = $gt->translateThis($sl, $tl, $text);
                    $json = json_decode($json, true);
                    $translation = $json['data']['translations'][0]['translatedText'];
                    
                    if ($translation == $text) $falsef++;
                    
                    if (strpos($outer, $text) !== false) $replace = $text;
                        elseif (strpos($outer, $inner) !== false) $replace = $inner;
                            elseif (strpos($outer, $item->nodeValue) !== false) $replace = $item->nodeValue;

                    if (strpos($outer, '<pattern>') !== false) {
                        $translation = $this->normalizePattern($translation);
                    }
                    
                    $tran[] = str_replace($replace, $translation, trim($outer));
                    usleep(500);
                }
            }
        }
    }
    
    private function compare ($original, $translated, $falsefails) {
        
        $comparison = array();
        $success = array();
        $failed = array();
        
        foreach ($original as $k => $v){
            
            if ($v == $translated[$k]) $failed[] = array($v, $translated[$k]);
                else $success[] = array($v, $translated[$k]);
        }
        
        $comparison['stats']['parser'] = $this->parser;
        $comparison['stats']['failed'] = count($failed);
        $comparison['stats']['false_fails'] = $falsefails;
        $comparison['stats']['success'] = count($success);
        $total = count($original);
        $comparison['stats']['total'] = $total;
        $formatter = new NumberFormatter('en_US', NumberFormatter::PERCENT);
        $comparison['stats']['effectivity'] = $formatter->format( (count($success)+$falsefails) / $total);
        $comparison['failed'] = $failed;
        
        return $comparison;
    }
    
    private function prepareXML ($xml) {
        
        $xml = $this->clean($xml);

        return trim($xml);
    }
    
    private function formatXML ($xml) {
        
        $xml = str_replace("> <","><", $xml);
        $xml = preg_replace("/(<[a-zA-Z]+(>|.*?[^?]>))/", PHP_EOL."$1", $xml);
        $xml = str_replace("><" ,">".PHP_EOL."<", $xml);
        $xml = preg_replace ("/>\h+/", ">", $xml);
        $xml = preg_replace ("/\h+</", "<", $xml);
        
        return trim($xml);
    }
    
    private function clean ($str) {
        
        $str = trim(preg_replace('/\s+/', ' ', $str));
        $str = $this->cleanBR($str);
        $str = $this->cleanEOL($str);
        $str = $this->cleanP($str);

        return trim($str);
    }
    
    private function cleanBR ($str) {
        
        $str = str_replace('<br>', '', $str);
        $str = str_replace('<br />', '', $str);
        $str = str_replace('<br/>', '', $str);

        return $str;
    }
    
    private function cleanP ($str) {
        
        $str = preg_replace('/\s\s+/', "", $str);
        $str = str_replace(" . ", '. ', $str);
        $str = str_replace(" .", '.', $str);

        return $str;
    }
    
    private function cleanEOL ($str) {
        
        $str = trim(preg_replace('/\s+/', ' ', $str));
        $str = str_replace(PHP_EOL, '', $str);
        $str = str_replace("\n", "", $str);
        $str = str_replace("\r", "", $str);
        
        return $str;
    }
    
    /*
     * 
     * @param object $node DOM XML Object 
     *
     * This part was harder than I expected. had to use XMLReader
     * on the parent node, to be able to find and output XML of current node
     */
    private function get_outer_xml( $node ) { 

        $outerXML = $node->ownerDocument->saveXML( $node->parentNode );
        $innerXML = $node->ownerDocument->saveXML( $node );
        
        $reader = new XMLReader();
        $reader->xml($outerXML);
        
        while ($reader->read()) {
            if ($reader->readInnerXML() == $innerXML || $reader->readInnerXML() == $node->nodeValue || $reader->readString() == $node->textContent) {
                return $reader->readOuterXML();
            }
        }

    }
    
    public function debug ($var, $pre=true, $html=true) {
        
        if ($pre) echo '<pre>';
            else header('Content-type: application/xml');
        if (is_array($var)) {
            if ($html) array_walk_recursive($var, function(&$v) { $v = htmlspecialchars($v); });
            print_r($var);
        } elseif (is_object($var)) {
            var_dump($var);
        } else {
            echo $var;
        }
        
        if ($pre) echo '</pre>';
    }
    
    public function output ($var) {
        
        switch($this->format) {
            
            case 'xml':
                header('Content-type: application/xml');
                echo $var['xml'];
                exit();
                break;
                
            case 'download':
                header('Content-disposition: attachment; filename="'.$var['filename'].'"');
                header('Content-type: "text/xml"; charset="utf8"');
                readfile($var['download_link']);
                exit();
                break;
            
            default:
        
                $this->debug($var, true);
        }
    }
    public function rutime($ru, $rus, $index) {
        return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
         -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
    }
    
    /**
     * Normalize an AIML Pattern
     *
     * @param string $str
     * @param array $options
     * @return string
     * 
     *  Modified by Leamsi Fontánez lfontanez@r1software.com - R1 Software
     * 
     * CREDITS:
     * 
     *  Original function was called url_slug
     * 
     *  Create a web friendly URL slug from a string.
     *
     *  @author Sean Murphy <sean@iamseanmurphy.com>
     *  @copyright Copyright 2012 Sean Murphy. All rights reserved.
     *  @license http://creativecommons.org/publicdomain/zero/1.0/
     */
    public function normalizePattern ($str, $options = array()) {
    	// Make sure string is in UTF-8 and strip invalid UTF-8 characters
    	$str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());
    	
    	$defaults = array(
    		'uppercase' => true,
    		'transliterate' => true
    	);
    	
    	// Merge options
    	$options = array_merge($defaults, $options);
    	
    	$char_map = array(
    		// Latin
    		'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 
    		'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 
    		'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O', 
    		'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH', 
    		'ß' => 'ss', 
    		'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 
    		'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 
    		'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o', 
    		'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th', 
    		'ÿ' => 'y',
    		// Latin symbols
    		'©' => '(c)',
    		// Greek
    		'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8',
    		'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',
    		'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
    		'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I',
    		'Ϋ' => 'Y',
    		'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8',
    		'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p',
    		'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w',
    		'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's',
    		'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',
    		// Turkish
    		'Ş' => 'S', 'İ' => 'I', 'Ç' => 'C', 'Ü' => 'U', 'Ö' => 'O', 'Ğ' => 'G',
    		'ş' => 's', 'ı' => 'i', 'ç' => 'c', 'ü' => 'u', 'ö' => 'o', 'ğ' => 'g', 
    		// Russian
    		'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
    		'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
    		'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
    		'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
    		'Я' => 'Ya',
    		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
    		'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
    		'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
    		'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
    		'я' => 'ya',
    		// Ukrainian
    		'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
    		'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',
    		// Czech
    		'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U', 
    		'Ž' => 'Z', 
    		'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
    		'ž' => 'z', 
    		// Polish
    		'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'o', 'Ś' => 'S', 'Ź' => 'Z', 
    		'Ż' => 'Z', 
    		'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
    		'ż' => 'z',
    		// Latvian
    		'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i', 'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N', 
    		'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z',
    		'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
    		'š' => 's', 'ū' => 'u', 'ž' => 'z'
    	);
    	
    	// Make custom replacements
    	//$str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);
    	
    	// Transliterate characters to ASCII
    	if ($options['transliterate']) {
    		$str = str_replace(array_keys($char_map), $char_map, $str);
    	}
    	
    	// Replace non-alphanumeric characters except space, period, underscore and star
    	$str =preg_replace('/[^ \w_.*]+/', '', $str);
    	
    	$str = trim($str);
    	
    	return $options['uppercase'] ? mb_strtoupper($str, 'UTF-8') : $str;
    }
}