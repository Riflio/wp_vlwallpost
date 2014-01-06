<?php

class VKapi {
	static $_app_id=3563905;
	static $_key='jGdC0q69Ba47Tw8PoCG5';
	
	static $_client_id = 71074831;
	static $_access_token = '551d66fd4df06054ebb6ba23bc8b6963d35f39bbfc28c38ce5ce58170bdef17a9e7e1843a5de241721092';

	function __construct(){
		
	}
	
	public function invoke ($name, array $params = array())	{
		$params['access_token'] = $this->_access_token;	
		
		if (isset($_REQUEST['captcha_sid'])) {
			$params['captcha_sid']=$_REQUEST['captcha_sid'];
			$params['captcha_key']=$_REQUEST['captcha_key'];
			unset($_REQUEST['captcha_sid']);
		}	
		
		$content = file_get_contents('https://api.vk.com/method/'.$name.'?'.http_build_query($params));
		$result  = json_decode($content);
		
		return $this->isError($result);
	}
 
	public function auth (array $scopes)	{
		header('Content-type: text/html; charset=windows-1251'); 
		echo file_get_contents('http://oauth.vk.com/authorize?'.http_build_query(array(
			'client_id'     => $this->_app_id,
			'scope'         => implode(',', $scopes),
			'redirect_uri'  => 'http://api.vk.com/blank.html',
			'display'       => 'page',
			'response_type' => 'token'
		)));
	}

	public function isError($res) {
		if ($res->error)  {		
			echo '<div class="vkerror">';	
			switch ($res->error->error_code) {
				case 14:
					echo 'VKAPI: '.$res->error->error_msg.'
					<img src="'.$res->error->captcha_img.'" /><br>		
					<form method="POST">			
					<input type="text" size=60 name="captcha_key" id="captcha_key" />
					<input type="hidden"  name="captcha_sid" id="captcha_sid"  value="'.$res->error->captcha_sid.'" />
					<input type="submit" name="captcha_need" value="Send" />
					';
				break;	
				default:
					echo 'VKAPI: '.$res->error->error_msg.'';
				break;		
			}
			echo '</div>';
			return false;
		} else {
			return $res->response;
		}					
		
	}
	
	public function refresh() {
		if (isset($_POST['captcha_sid'])) {					
			wp_redirect( 'http://'.$_SERVER['HTTP_HOST'].$_POST['_wp_http_referer'].'&captcha_sid='.$_POST['captcha_sid'].'&captcha_key='.$_POST['captcha_key']);
			exit;
		}
	}
	
	public function uploadFile($uploadUrl, $files) {
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