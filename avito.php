<?php
require 'vendor/autoload.php';
require 'tools/CurlClient.php';

use Goutte\Client;
use tools\CurlClient;
use Jitsu\RegexUtil;
use NekoWeb\AntigateClient;
use Intervention\Image\ImageManager;
use Simplon\Mysql\Mysql;



$url = 'https://www.avito.ru/moskva/nedvizhimost?user=1';
for (;;){

	
	$list_item = parseList($url,null,null,null,'https://www.avito.ru/moskva/');
	if (empty($list_item)) {
		error('List of items is null');
		return;
	}
	foreach ($list_item as $item) {
		info('Parse item: '.$item['url']);
		if (empty($item['id'])) {
			error('ID of item is null');
		}else{
			if (connectDb()->fetchColumn('SELECT id FROM post WHERE item_id = :id',[':id' => $item['id']]) === null) {
				parseSinglePage($item['url'],false);
				
				$rand_sec = rand(5,10);
				info('sleep '.$rand_sec.' sec');
				sleep($rand_sec);
			}else{
				info('Post with this ID is already saved');
			}	
		}
		
		
	}
	$rand_sleep = rand(600,900);
	var_dump($rand_sleep);
	info('Sleep '.($rand_sleep/60).' min');
	sleep($rand_sleep);

}

function parseList($url)
{
	$out = [];
	$client = new CurlClient();
	$content = $client->parsePage($url);
	$content = checkCaptcha($client,$content,$url);
	
	if (empty($content)) {
		error('Content is null');
		return;
	}
	$urls = $client->parseProperty($content,'link','h3.item-description-title a.item-description-title-link',$url,$attr = null);

	$urls_id = $client->parseProperty($content,'attribute','a.favorites-add__link',$url,$attr = 'href');

	if (empty($urls) === false  && empty($urls_id) === false) {
		foreach ($urls as $n => $url) {
			$out[$n]['url'] = $url;
			$out[$n]['id'] = (empty($urls_id[$n]) === false) ? str_replace('/favorites/add/','',$urls_id[$n]) : null;
		}
	}
	return $out;
}

function parseSinglePage($url,$antigate = false)
{
	$client = new CurlClient();
	$content = $client->parsePage($url);
	if (empty($content)) {
		error('Content is null');
		return;
	}

	$item_id = itemId($client,$content);
	if (empty($item_id)) {
		error('Item is null');
		return;
	}else{
		info('Item ID: '.$item_id);
	}

	if (connectDb()->fetchColumn('SELECT id FROM post WHERE item_id = :id',[':id' => $item_id]) !== null) {
		info('Post with this ID is already saved');
		return;
	}

	$title = getTitle($client,$content);
	if (empty($title)) {
		error('Title is null');
		return;
	}else{
		info('Title: '.$title);
	}
	
	$image = getImage($client,$content,$item_id);

	if (empty($image)) {
		info('Image is null');
	}
	
	$price = getPrice($client,$content);
	if (empty($price)) {
		error('Price is null');
		return;
	}else{
		info('Price: '.$price);
	}

	$saler_name = getSalerName($client,$content);
	if (empty($saler_name)) {
		error('Name is null');
		return;
	}else{
		info('Name: '.$saler_name);
	}

	$city = getCity($client,$content);
	if (empty($city)) {
		error('City is null');
		return;
	}else{
		info('City: '.$city);
	}

	$category = getCategory($client,$content);
	if (empty($category)) {
		error('Category is not foind');
	}else{
		info('Category: '.$category);
	}

	$metro = getMetro($client,$content);
	if (empty($metro) === false) {
		info('Metro: '.$metro);
	}

	$address = getAddress($client,$content);
	if (empty($address) === false) {
		info('Address: '.$address);
	}

	$lat = getLat($client,$content);
	if (empty($lat) === false) {
		info('Lat: '.$lat);
	}

	$lon = getLon($client,$content);
	if (empty($lon) === false) {
		info('Lon: '.$lon);
	}

	$description = getDescription($client,$content);
	if (empty($description) === false) {
		info('Description: '.$description);
	}

	$category = getCategory($client,$content);
	if (empty($category) === false) {
		info('Category: '.$category);
	}

	$number = getNumber($url,$client,$content,$antigate);
	if (empty($number['base64'])) {
		error('Number is null');
		return;
	}else{
		info('Number is parsed');
	}
	if ($antigate) {
		if (empty($number['number'])) {
			error('Number is not get from ANTIGATE');
		}else{
			info($number['number']);
		}
	}
	
	$data[0] = [
		'item_id' => $item_id,
		'title' => $title,
		'saler_name' => $saler_name,
		'saler_phone' => $number['base64'],
		'price' => $price,
		'city' => $city,
		'metro' => $metro,
		'lat' => $lat,
		'lon' => $lon,
		'address' => $address,
		'description' => $description,
		'category' => $category,
		'created_at' => time(),
		'updated_at' => time(),
	];
	insertTable('post',$data);
}

function getTitle($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'string','h1[itemprop = "name"]',$url = null,$attr = null);
	if (empty($list[0]) === false) {
		$out = $list[0];
	}
	return $out;
}

function getImage($client,$content,$item_id)
{
	$out = '';
	$path_file = __DIR__.'/runtime/'.$item_id.'.jpg';
	$list = $client->parseProperty($content,'attribute','img[itemprop = "image"]',$url = null,$attr = 'src');
	if (empty($list[0])) {
		error('Url image is not found');
		return;
	}
	$url_img = $list[0];
	saveImage('http:'.$url_img,$path_file);
	$manager = new ImageManager(array('driver' => 'imagick'));
	$watermark = $manager->make(__DIR__.'/watermark/pic.png')->opacity(30);
	if (!file_exists($path_file)) {
		error($path_file.' is not exitsts');
		return;
	}
	$image = $manager->make($path_file);
	$image_h = $image->height()-40;
	$image_w = $image->width();
	$image = $image->crop($image_w, $image_h, 0, 0)->resizeCanvas($image_w, $image_h)->insert($watermark,'center')->save($path_file);

	return $image;
	//return imageBase64(__DIR__.'/runtime/itempic.jpg');
}

function getPrice($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'string','span[itemprop = "price"]',$url = null,$attr = null);
	if (empty($list[0]) === false) {
		$out = $list[0];
	}
	return rtrim($out);
}

function getSalerName($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'string','div[itemprop = "seller"] strong[itemprop = "name"]',$url = null,$attr = null);
	if (empty($list[0]) === false) {
		$out = $list[0];
	}
	return rtrim($out);
}

function getNumber($url,$client,$content,$antigate = false)
{
	$out = [];
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
	$out['base64'] = $base64;
	if ($antigate) {
		base64_to_jpeg($base64,$path_image);
		if (!file_exists($path_image)) {
			error($path_image.' is not exists');
			return;
		}
		$out['number'] = recognize($path_image,0,0,1);

	}
	return $out;
}

function getCity($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'string','div[itemprop = "availableAtOrFrom"] span[itemprop = "name"]',$url = null,$attr = null);
	
	if (empty($list[0]) === false) {
		$out = $list[0];
	}
	return $out;
}

function getMetro($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'string','div.metro-list span.metro-item',$url = null,$attr = null);
	$list_km = $client->parseProperty($content,'string','div.metro-list span.metro-item span.c-2',$url = null,$attr = null);
	
	if (empty($list) || empty($list_km)) {
		info('Block metro is not found');
		return;
	}

	foreach (str_replace($list_km, '',  $list) as $n => $single) {
		$km = (empty($list_km[$n]) === false) ? ' - <span class = "single-km">'.$list_km[$n].'</span>' : '';
		$out .= '<span class = "single-metro">'.rtrim($single).'</span>'.$km;
		
	}
	return $out;
}

function getAddress($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'string','div[itemprop = "address"] span[itemprop = "streetAddress"]',$url = null,$attr = null);
	if (empty($list[0]) === false) {
		$out = $list[0];
	}
	return $out;
}

function getLat($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'attribute','div.b-search-map',$url = null,$attr = 'data-map-lat');
	if (empty($list[0]) === false) {
		$out = $list[0];
	}
	return $out;
}

function getLon($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'attribute','div.b-search-map',$url = null,$attr = 'data-map-lon');
	if (empty($list[0]) === false) {
		$out = $list[0];
	}
	return $out;
}

function getDescription($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'string','div#desc_text p',$url = null,$attr = null);
	if (empty($list[0]) === false) {
		$out .= '<p>'.$list[0].'</p>';
	}
	return $out;
}

function getCategory($client,$content)
{
	$out = '';
	$list = $client->parseProperty($content,'html','select#category',$url = null,$attr = null);
	if (empty($list) === false) {
		$pat = 'selected>(.*)</option>';
		if (empty(match($list,$pat)) === false) {
			$out = match($list,$pat)->group(1);
		}
		else{
			error('Selected category is not found');
			return;
		}
	}else{
		error('Block category is not found');
		return;
	}
	
	return $out;
}

function base64_to_jpeg($base64_string, $output_file) {
    $ifp = fopen($output_file, "wb"); 
    $data = explode(',', $base64_string);
    fwrite($ifp, base64_decode($data[1])); 
    fclose($ifp); 
    return $output_file; 
}

function imageBase64($image)
{
	// Read image path, convert to base64 encoding
	$imgData = base64_encode(file_get_contents($image));
	// Format the image SRC:  data:{mime};base64,{data};
	return 'data: '.mime_content_type($image).';base64,'.$imgData;
}




function phoneCode($client,$content)
{
	$out = '';
	$pat = 'avito.item.phone = \'(.*)\'';
	$list = $client->parseProperty($content,'string','script',$url = null,$attr = null);
	if (empty($list) === false) {
		foreach ($list as $item) {
			if (empty(match($item,$pat)) === false) {
				$out = match($item,$pat)->group(1);
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

function match($str,$pat, $flags = '', $start = null, $end = null)
{
	$regex = RegexUtil::create($pat, $flags, $start, $end);
	return RegexUtil::match($regex, $str, $offset = 0);
}

function matchAll($str,$pat,$flags = '', $start = null, $end = null)
{
	$regex = RegexUtil::create($pat, $flags, $start, $end);
	return RegexUtil::matchAll($regex, $str, $offset = 0);
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

function recognize($pic,$phrase = 0, $regsense = 0,$numeric = 0)
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
			'phrase' => $phrase,
			'numeric' => $numeric,
			'regsense' => $regsense,

		]);

}

function connectDb()
{
	require 'config/db.php';
	
	return new Mysql(
	    $config['host'],
	    $config['user'],
	    $config['password'],
	    $config['database']
	);
}

function insertTable($table,$data)
{
	return connectDb()->insertMany($table, $data);	
}

function checkCaptcha($client,$content,$url)
{
	$firewall_title = $client->parseProperty($content,'string','h2.firewall-title',$url,$attr = null);
	if (empty($firewall_title)) {
		return $content;
	}else{
		error('CAPTCHA, sleep 30 мин');
		sleep(2400);

		/*$image = $client->parseProperty($content,'image','img.form-captcha-image',$url,$attr = null);
		if (empty($image[0])) {
			error('Url of captch is not found');
			return;
		}
		$cap_image = $client->parsePage($image[0],null,null,null,$url);
		if (empty($cap_image)) {
			error('Image of captch is null');
			return;
		}
		$savefile = fopen(__DIR__.'/runtime/captcha.jpg', 'w');
    	fwrite($savefile, $cap_image);
    	fclose($savefile);
    	$catpcha_text = recognize(__DIR__.'/runtime/captcha.jpg');
    	var_dump($catpcha_text);*/
		
	}
	die;
}

function saveImage($url,$path)
{
	if (file_exists($path)) {
		unlink($path);
	}

	$clientImage = new CurlClient();
	$image_curl = $clientImage->parsePage($url);
	if (empty($image_curl)) {
		error('image_curl is null');
		return;
	}
	$savefile = fopen($path, 'w');
    fwrite($savefile, $image_curl);
    fclose($savefile);
    return;
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