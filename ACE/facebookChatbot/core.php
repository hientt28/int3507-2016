<?php

class Core{
	const MODULES_DIR = './modules';
	const FB_APP_TOKEN = '';
	const VERIFY_TOKEN = '';

	function process($command, $sender){
		$modules = self::listModules();
		$messages = array();

		foreach($modules as $moduleName){
			include(self::MODULES_DIR.'/'.$moduleName.'.php');
			$module = new $moduleName($sender);
            $response = $module->process($command);
            if($response != null)
                $messages[] = $response ;
		}

        $totalMessage = sizeof($messages);

		if($totalMessage > 1){
			$index = 0;
			for($i = 1; $i < $totalMessage; $i++){
				if($messages[$i]['priority'] >= $messages[$index]['priority'])
					$index = $i;
			}
			return $messages[$index];
		} else
			return $messages[0];
	}

	function listModules(){
		$handle = opendir(self::MODULES_DIR);
		$listModule = array();
	    while (false !== ($entry = readdir($handle))) {
	        if ($entry != "." && $entry != "..") {
	        	$filename = explode('.', $entry);
	            if($filename[1] == 'php')
	            	$listModule[] = $filename[0];
	        }
	    }
	    closedir($handle);
	    return $listModule;
	}
}

class FbBotApp{
    /**
     * Request type GET
     */
    const TYPE_GET = "get";
    
    /**
     * Request type POST
     */
    const TYPE_POST = "post";

    /**
     * Request type DELETE
     */
    const TYPE_DELETE = "delete";
    
    /**
     * FB Messenger API Url
     *
     * @var string
     */
    protected $apiUrl = 'https://graph.facebook.com/v2.6/';
    
    /**
     * @var null|string
     */
    protected $token = null;

    /**
     * FbBotApp constructor.
     * @param string $token
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Send Message
     *
     * @param Message $message
     * @return array
     */
    public function send($message)
    {
        return $this->call('me/messages', $message->getData());
    }

    /**
     * Get User Profile Info
     *
     * @param int    $id
     * @param string $fields
     * @return UserProfile
     */
    public function userProfile($id, $fields = 'first_name,last_name,profile_pic,locale,timezone,gender')
    {
        return new UserProfile($this->call($id, [
            'fields' => $fields
        ], self::TYPE_GET));
    }

    /**
     * Set Persistent Menu
     *
     * @see https://developers.facebook.com/docs/messenger-platform/thread-settings/persistent-menu
     * @param MessageButton[] $buttons
     * @return array
     */
    public function setPersistentMenu($buttons)
    {
        $elements = [];

        foreach ($buttons as $btn) {
            $elements[] = $btn->getData();
        }

        return $this->call('me/thread_settings', [
            'setting_type' => 'call_to_actions',
            'thread_state' => 'existing_thread',
            'call_to_actions' => $elements
        ], self::TYPE_POST);
    }

    /**
     * Remove Persistent Menu
     *
     * @see https://developers.facebook.com/docs/messenger-platform/thread-settings/persistent-menu
     * @return array
     */
    public function deletePersistentMenu()
    {
        return $this->call('me/thread_settings', [
            'setting_type' => 'call_to_actions',
            'thread_state' => 'existing_thread'
        ], self::TYPE_DELETE);
    }

    /**
     * Request to API
     *
     * @param string $url
     * @param array  $data
     * @param string $type Type of request (GET|POST|DELETE)
     * @return array
     */
    protected function call($url, $data, $type = self::TYPE_POST)
    {
        $data['access_token'] = $this->token;

        $headers = [
            'Content-Type: application/json',
        ];

        if ($type == self::TYPE_GET) {
            $url .= '?'.http_build_query($data);
        }

        $process = curl_init($this->apiUrl.$url);
        curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($process, CURLOPT_HEADER, false);
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        
        if($type == self::TYPE_POST || $type == self::TYPE_DELETE) {
            curl_setopt($process, CURLOPT_POST, 1);
            curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        if ($type == self::TYPE_DELETE) {
            curl_setopt($process, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($process, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($process, CURLOPT_SSL_VERIFYPEER, 0);
        $return = curl_exec($process);
        curl_close($process);

        return json_decode($return, true);
    }
}

?>