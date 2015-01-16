<?php

require("vendor/autoload.php");

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;

require("config.php");
$config = new ParameterBag($config);

require("global.php");
require("Proxy.php");

require("exceptions/ProxyException.php");
require("FilterEvent.php");

// load all plugins at once
foreach (glob("plugins/*.php") as $filename){
	require($filename);
}

// constants to be used throughout
define('SCRIPT_BASE', (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']);
define('SCRIPT_DIR', pathinfo(SCRIPT_BASE, PATHINFO_DIRNAME).'/');
define('PLAYER_URL', SCRIPT_DIR.'/flowplayer/flowplayer-3.2.18.swf');


// form submit in progress...
if(isset($_POST['url'])){
	
	$url = $_POST['url'];
	$url = add_http($url);
	
	header("HTTP/1.1 302 Found");
	header('Location: '.SCRIPT_BASE.'?q='.encrypt_url($url));
	exit;
	
} else if(!isset($_GET['q'])){

	// must be at homepage!
	echo render_template("index", array('script_base' => SCRIPT_BASE));
	exit;
}

$url = decrypt_url($_GET['q']);

define('URL', $url);


$request = prepare_from_globals($url);
$proxy = new Proxy($request);



$proxy->addPlugin(new HeaderRewritePlugin());
$proxy->addPlugin(new CookiePlugin());
$proxy->addPlugin(new ProxifyPlugin());
$proxy->addPlugin(new YoutubePlugin());
$proxy->addPlugin(new DailyMotionPlugin());
$proxy->addPlugin(new LogPlugin());
$proxy->addPlugin(new XVideosPlugin());
$proxy->addPlugin(new XHamsterPlugin());
$proxy->addPlugin(new RedTubePlugin());


try {

	$response = $proxy->execute($url);
	
	// if headers were already sent, then this must be a streaming response
	if(!headers_sent()){
	
		// send headers first!
		$response->sendHeaders();
		
		// resource contents
		$output = $response->getContent();
		
		$master_page = is_html($response->headers->get('content-type'));
		
		// if this is the master page, then include URL form
		if($master_page){
			
			$url_form = render_template("url_form", array(
				'url' => $url,
				'script_base' => SCRIPT_BASE
			));
			
			// does the html page contain <body> tag, if so insert our form right after <body> tag starts
			$output = preg_replace('@<body.*?>@is', '$0'.PHP_EOL.$url_form, $output, 1, $count);
			
			// <body> tag was not found, just put the form at the top of the page
			if($count == 0){
				$output = $url_form.$output;
			}
		}
		
		echo $output;
	}
	
} catch (Exception $ex){

	if($config->has("error_redirect")){
	
		$url = render_string($config->get("error_redirect"), array(
			'error_msg' => rawurlencode($ex->getMessage())
		));
		
		header("HTTP/1.1 302 Found");
		header("Location: {$url}");
		
	} else {
	
		echo render_template("index", array(
			'url' => $url,
			'script_base' => SCRIPT_BASE,
			'error_msg' => $ex->getMessage()
		));
		
	}
}


?>