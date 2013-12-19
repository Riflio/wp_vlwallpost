<?php

class Vkapi {
	protected static $_app_id=3563905;
	protected static $_key='jGdC0q69Ba47Tw8PoCG5';
	
	protected static $_client_id = 71074831;
	protected static $_access_token = '551d66fd4df06054ebb6ba23bc8b6963d35f39bbfc28c38ce5ce58170bdef17a9e7e1843a5de241721092';
	
	public static $error = false; //-- запоминаем последнюю ошибку
	
	public static function invoke ($name, array $params = array())	{
		$params['access_token'] = self::$_access_token;	
		
		if (isset($_GET['captcha_sid'])) {
			$params['captcha_sid']=$_GET['captcha_sid'];
			$params['captcha_key']=$_GET['captcha_key'];
		}	
		
		$content = file_get_contents('https://api.vk.com/method/'.$name.'?'.http_build_query($params));
		$result  = json_decode($content);
		
		return Vkapi::isError($result);
	}
 
	public static function auth (array $scopes)	{
		header('Content-type: text/html; charset=windows-1251'); 
		echo file_get_contents('http://oauth.vk.com/authorize?'.http_build_query(array(
			'client_id'     => self::$_app_id,
			'scope'         => implode(',', $scopes),
			'redirect_uri'  => 'http://api.vk.com/blank.html',
			'display'       => 'page',
			'response_type' => 'token'
		)));
	}

	public static function isError($res) {
		
		if ($res->error)  {		
			self::$error=$res->error;	
			echo '<div id="message" class="error">TTTTT</div>';		
		} else {
			self::$error=false;
			return $result->response;
		}			
		
		return false;
	}
	
	public static function uploadFile($uploadUrl, $files) {
		//$files['photo'] = '@'.'path to photo'; 
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uploadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $files);
        $result = curl_exec($ch);
        curl_close($ch);		
		return json_decode($result);
	}
 
}

?>