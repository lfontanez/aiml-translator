<?php
/*
 * AIML Translation Demo
 * Author: Leamsi Fontánez - lfontanez@r1software.com
 * Copyright (c) 2017 R1 Software
 */

// Report all errors except E_NOTICE
error_reporting(E_ALL & ~E_NOTICE);

// Process inputs
if (count($_GET) > 0) {
    foreach ($_GET as $k => $v) {
        
        switch($k) {
            case "filename":
                $filename = $uploads_path . $_GET['filename'];
                break;
            case "sl":
                $sl = $_GET['sl'];
                break;
            case "tl":
                $tl = $_GET['tl'];
                break;
            case "dl":
                $dl = $_GET['dl'];
                break;
                
        }
    }
}

// Set Defaults ?filename=test.aiml&sl=en&tl=es&dl=1
if (!$_GET['filename']) $filename = $uploads_path . 'test.aiml';
if (!$_GET['sl']) $sl = 'en';
if (!$_GET['tl']) $tl = 'es';
if (!$_GET['dl']) $dl = 1;

$googleTranslateApiKey = '[YOUR-API-kEY]'; // Your Google Translate API Key

require_once('./libs/aiml-translate.class.php'); // Get our lib

$trans = new Translate($googleTranslateApiKey); // Instantiate object
$trans->set_parser('xml');
//$trans->set_format('download');
//$trans->set_uploads('./uploads/');
//$trans->set_downloads('./downloads/');
//$trans->set_save(true);
$result = $trans->translateAIML($filename, $sl, $tl); // Translate the file

$trans->debug($result); // Get debug output

//$trans->output($result); // Get output


