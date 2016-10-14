<?php
require 'vendor/autoload.php';
require 'tools/CurlClient.php';

use Goutte\Client;
use tools\CurlClient;
use Jitsu\RegexUtil;
use NekoWeb\AntigateClient;



$url = 'https://www.avito.ru/moskva/predlozheniya_uslug/pereezd_kvartiry_ofisa._gazel_s_gruzchikami_849178956';

parseSinglePage($url,true);



function parseSinglePage($url,$number = false)
{
	$client = new CurlClient();
	$content = $client->parsePage($url);
	if (empty($content)) {
		error('Content is null');
		return;
	}

	if ($number) {
		$path_image = __DIR__.'/runtime/antigate';
		$phone_code = phoneCode($client,$content);

		if (empty($phone_code)) {
			error('The phone code is null');
			return;
		}else{
			info('Get phone code: '.$phone_code);
		}


		$item_id = itemId($client,$content);
		if (empty($item_id)) {
			error('The item id is null');
			return;
		}else{
			info('Get item id: '.$item_id);
		}

		$hash = phoneDemixer($phone_code,$item_id);
		if (empty($hash)) {
			error('The hash is null');
			return;
		}else{
			info('Get hash: '.$hash);
		}
		
		$image = $client->parsePage('https://www.avito.ru/items/phone/'.$item_id.'?pkey='.$hash,null,null,null,$url);
		if (empty($image)) {
			error('Image is null');
			return;
		}
		$base64 = json_decode($image)->image64;
		base64_to_jpeg($base64,$path_image);
		if (!file_exists($path_image)) {
			error($path_image.' is not exists');
			return;
		}
		$number = recognize($path_image);
	}
	
}

function base64_to_jpeg($base64_string, $output_file) {
    $ifp = fopen($output_file, "wb"); 
    $data = explode(',', $base64_string);
    fwrite($ifp, base64_decode($data[1])); 
    fclose($ifp); 
    return $output_file; 
}




function phoneCode($client,$content)
{
	$out = '';
	$pat = 'avito.item.phone = \'(.*)\'';
	$list = $client->parseProperty($content,'string','script',$url = null,$attr = null);
	if (empty($list) === false) {
		foreach ($list as $item) {
			if (empty(match($item)) === false) {
				$out = match($item)->group(1);
			}
		}
	}
	return $out;
}

function phoneDemixer($key,$id) {
	preg_match_all("/[\da-f]+/",$key,$pre);
	$pre = $id%2==0 ? array_reverse($pre[0]) : $pre[0];
	$mixed = join('',$pre);
	$s = strlen($mixed);
	$r='';
	for($k=0; $k<$s; ++$k) {
		if ($k%3==0) {
			$r .= substr($mixed,$k,1);
		}
	}
	return $r;
}


function itemId($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'string','span#item_id',$url = null,$attr = null);
	if (empty($list) === false) {
		foreach ($list as $item) {
			$out = $item;
			break;
		}
	}
	return $out;
}

function match($str)
{
	$regex = RegexUtil::create('avito.item.phone = \'(.*)\'', $flags = '', $start = null, $end = null);
	return RegexUtil::match($regex, $str, $offset = 0);
}

function property($crawler, $type,$pattern)
{
	$out = [];
	if ($type === 'string') {
		$nodes = $crawler->filter($pattern);
		if (empty($nodes) === false) {
			foreach ($nodes as $node) {
				$out[] = $node->textContent;
			}
		}else{
			error('Nodes are null');
		}
	}
	return $out;
}

function recognize($pic)
{
	require 'config/antigate.php';
	if (empty($config['key'])) {
		error('Antigate KEY is not set');
		return;
	}
	$antigate = new AntigateClient();
	$antigate->setApiKey($config['key']);
	info('Balance antigate: '.$antigate->getBalance());
	return $antigate->recognizeByFilename($pic,[
			'numeric' => 1
		]);

}


function error($string)
{
	echo "\033[31m".$string."\033[0m".PHP_EOL;
}

function success($string)
{
	echo "\033[32m".$string."\033[0m".PHP_EOL;
}

function info($string)
{
	echo "\033[33m".$string."\033[0m".PHP_EOL;
}
?>