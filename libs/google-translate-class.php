<?php

class GoogleTranslate {
    var $apikey;
    var $apiendpoint;

    public function __construct($apikey, $apiurl=false) {		
		$this->apikey = $apikey;
		($apiurl) ? $this->apiendpoint = $apiurl : $this->apiendpoint = 'https://www.googleapis.com/language/translate/v2';
	}
		
    public function set_apikey($apikey) {
    	$this->apikey = $apikey;
    }
	
	public function get_apikey() {
		return $this->apikey;
	}   
	
	public function set_apiendpoint($apiurl) {
    	$this->apiendpoint = $apiurl;
    }
	
	public function get_apiendpoint() {
		return $this->apiendpoint;
	}   

    
    public function translateThis ($sl, $tl, $text) {
            
        $url =  $this->apiendpoint . '?key=' . $this->apikey . '&q=' . rawurlencode($text) . "&source=$sl&target=$tl";
        
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($handle);
        curl_close($handle);
        
        return $response;
    }
    
}