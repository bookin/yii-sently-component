<?php

/**
 * Class Sently
 * @property mixed $curlInfo Возвращает curl_getinfo() для последнего запроса
 * @property string $curlError Возвращает curl_error() для последнего запроса
 * @property array $headers Заголовки последнего запроса
 * @property string $body Тело последнего запроса
 * @property string $token Тело последнего запроса
 */
class Sently extends CApplicationComponent{
    public $apiUrl = 'https://apiserver.sent.ly/';//'https://private-anon-b15c7389d-sentlyweb.apiary-mock.com/';
    public $apiKey = '';
    public $secretKey = '';
    public $oauthUrl = 'oauth/token';
    public $phoneFrom = '';

    public $phoneUrl = 'https://phone.sent.ly/command/sendsms';//'http://private-anon-18c5fd1a0-sentlyphone.apiary-mock.com/command/sendsms/';
    public $email = '';
    public $password = '';
    public $deviceId = '';

    protected $_phone_params, $_errors=[];

    public $runtimePath = 'application.runtime.sently';
    public $cookieFile = 'cookie.txt';
    private $_token, $_curlInfo, $_curlError, $_headers = [], $_body;

    const CACHE_TOKEN_ID = 'sently_token';

    /**
     * @return mixed
     */
    public function getToken()
    {
        if(!$this->_token){
            $this->_token = $this->auth();
        }
        return $this->_token;
    }

    public function auth(){
        $url=$this->apiUrl.$this->oauthUrl;
        $header_data = [
            'Content-Type'=>'application/x-www-form-urlencoded;charset=UTF-8',
            'Accept'=>'application/json',
            'Authorization'=>'Basic '.base64_encode($this->apiKey.':'.$this->secretKey),
            'Accept-Encoding'=>'gzip'
        ];
        $this->curlQuery($url, ['grant_type'=>'client_credentials'], 'POST', $header_data);
        $response = json_decode($this->body, true);
        if(!is_array($response) && $this->body == 'Forbidden'){
            $this->setErrors([
                'code'=>'-1',
                'line'=>__LINE__,
                'message'=>'Server rejected a authorization'
            ]);
            return false;
        }elseif(!$this->curlError && isset($response['access_token'])){
            return $response['access_token'];
        }
    }

    /**
     * @return mixed
     */
    public function getCurlInfo()
    {
        return $this->_curlInfo;
    }

    /**
     * @param mixed $curlInfo
     */
    protected function setCurlInfo($curlInfo)
    {
        $this->_curlInfo = $curlInfo;
    }


    /**
     * @return mixed
     */
    public function getCurlError()
    {
        return $this->_curlError;
    }

    /**
     * @param mixed $curlError
     */
    protected function setCurlError($curlError)
    {
        $this->_curlError = $curlError;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * @param array $headers
     */
    protected function setHeaders($headers)
    {
        $this->_headers = $headers;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->_body;
    }

    /**
     * @param mixed $body
     */
    protected function setBody($body)
    {
        $this->_body = $body;
    }


    protected function getCookiesFilePath(){
        $folder = $this->getRuntimePath();
        $filePath = $folder.DIRECTORY_SEPARATOR.$this->cookieFile;
        if(!is_file($filePath)){
            $f = fopen($filePath, 'w');
            fclose($f);
        }
        return $filePath;
    }

    protected function getRuntimePath(){
        $folder = Yii::getPathOfAlias($this->runtimePath);
        if(!is_dir($folder)){
            @mkdir($folder, 0755);
        }
        return $folder;
    }

    public function curlQuery($url, $params=[], $method='GET', $headers=[], $options=[]){
        if($params&&is_array($params)){
            array_walk($params, function(&$val, $key){
                if($val === null)
                    $val = '';
            });
        }

        $curlParams = [
            CURLOPT_URL => $url,
            CURLOPT_REFERER => $url,
            CURLOPT_VERBOSE => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0",
            CURLOPT_HEADER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_RETURNTRANSFER => true, //возврата результата передачи в качестве строки из curl_exec() вместо прямого вывода в браузер.,
            CURLOPT_COOKIEJAR => $this->getCookiesFilePath(),//Yii::getAlias('@runtime').'/jober/cookie.txt',
            CURLOPT_COOKIEFILE => $this->getCookiesFilePath(),//Yii::getAlias('@runtime').'/jober/cookie.txt',
            CURLOPT_COOKIESESSION => true
        ];

        $url_info = parse_url($url);
        $headers_data = [];
        $headers_options = CMap::mergeArray([
            "Host"=>$url_info['host'],
            "Accept"=>"text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language"=>"ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3",
            "Connection"=>"keep-alive"
        ],$headers);
        foreach($headers_options as $key=>$val){
            $headers_data[]=$key.': '.$val;
        }

        switch(strtoupper($method)){
            case 'POST':
                $curlParams[CURLOPT_POST]=true;
                if($params)
                    $curlParams[CURLOPT_POSTFIELDS]=(is_array($params)?http_build_query($params):$params);
                break;
            case 'PUT':
                $curlParams[CURLOPT_CUSTOMREQUEST]="PUT";
                if($params)
                    $curlParams[CURLOPT_POSTFIELDS]=(is_array($params)?http_build_query($params):$params);
                break;
            case 'DELETE':
                $curlParams[CURLOPT_CUSTOMREQUEST]="DELETE";
                if($params)
                    $curlParams[CURLOPT_URL]=$url.'?'.(is_array($params)?http_build_query($params):$params);
                break;
            default:
                $curlParams[CURLOPT_HTTPGET]=true;
                if($params)
                    $curlParams[CURLOPT_URL]=$url.'?'.(is_array($params)?http_build_query($params):$params);
                break;
        }
        $curlParams[CURLOPT_HTTPHEADER] = $headers_data;

        if(strtolower((substr($url,0,5))=='https')) { // если соединяемся с https
            $curlParams[CURLOPT_SSL_VERIFYPEER]=false;
            $curlParams[CURLOPT_SSL_VERIFYHOST]=false;
        }

        $ch = curl_init();
        if($options)
            $curlParams = CMap::mergeArray($curlParams, $options);
        curl_setopt_array($ch, $curlParams);
        $result=curl_exec($ch);

        $this->curlInfo = curl_getinfo($ch);
        $this->curlError = curl_error($ch);
        if($this->curlError){
            $this->setErrors([
                'code'=>curl_errno($ch),
                'line'=>__LINE__,
                'message'=>$this->curlError
            ]);
        }

        $this->headers = explode("\r\n\r\n", trim(substr($result, 0, $this->curlInfo['header_size'])));
        $body = substr($result, $this->curlInfo['header_size']);
        $this->body = $body;

        curl_close($ch);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @param mixed $errors
     */
    protected function setErrors($errors)
    {
        $this->_errors[] = $errors;
    }

    /**
     * Sent.ly PHONE API
     */

    public function createMessage($to, $message){
        $params=[
            'username'=>$this->email,
            'password'=>$this->password,
            //'hid'=>$this->deviceId //Игнорирует ошибки в коде
        ];
        if(!empty($to)) {
            $params['to'] = ($to);
        }
        if(!empty($message)) {
            $params['text'] = ($message);
        }
        $this->_phone_params = $params;
        return $this;
    }

    /**
     * @return bool|int
     */
    public function send(){
        $params = $this->_phone_params;

        /* Check correct params */
        if(!isset($params['to']) || !isset($params['text'])){

        }

        $this->curlQuery($this->phoneUrl, $params, 'GET', [
            'Content-Type'=>'application/text'
        ]);

        if($this->curlError){
            return false;
        }

        $response = explode(':', $this->body);
        //If response doesn't have two parts then something is wrong
        if(count($response) != 2) {
            $this->setErrors([
                'code'=>'-1',
                'line'=>__LINE__,
                'message'=>'Something is wrong'
            ]);
            return false;
        }

        //If it's an error response the first element of returned array has the error code
        //Id is 0 as message was not sent
        if(strcmp($response[0], 'Error') == 0){
            $code = intval($response[1]);
            $this->setErrors([
                'code'=>$code,
                'line'=>__LINE__,
                'message'=>self::getSentlyTextError($code)
            ]);
            return false;
        }

        //Otherwise the first element is 0 as error code is 0
        //second element has the id of the message
        if(strcmp($response[0],'Id')==0){
            return intval($response[1]);
        }

        return false;
    }

    public static function getSentlyTextError($code){
        $text = '';
        switch($code){
            case 0:
                $text = 'The authentication parameters (username / password) were not correct.';
                break;
            case 1:
                $text = 'The parameters supplied were malformed.';
                break;
            case 2:
                $text = '<Reserved>';
                break;
            case 3:
                $text = 'There was no appropriate device to send the SMS as requested (If the number is an international number, and the phone is set up for it)';
                break;
            case 4:
                $text = ' Not enough Sent.ly credits for this operation. Please top-up.';
                break;
            default:
                $text = 'I don`t now this error';
        }
        return $text;
    }


    /**
     * Sent.ly API
     */

    /**
     * @param $response
     * @return bool
     */
    protected function processingApiResponse($response){
        $decode = json_decode($response, true);
//        var_dump(debug_backtrace());
        if(empty($decode)&&$response){
            if(strcmp($response,'Unauthorized')==0){
                Yii::app()->cache->delete(self::CACHE_TOKEN_ID);
                /*$steck = debug_backtrace();
                call_user_func_array(array(self, $steck[1]['function']), $steck[1]['args']);*/
                $this->setErrors([
                    'code'=>'-1',
                    'line'=>__LINE__,
                    'message'=>'Error authorization'
                ]);
            }
        }elseif(!is_array($decode)){
            $this->setErrors([
                'code'=>'-1',
                'line'=>__LINE__,
                'message'=>'Something wrong'
            ]);
            return false;
        }elseif($decode['error']['error_code'] != 0){
            $this->setErrors([
                'code'=>$decode['error']['error_code'],
                'line'=>__LINE__,
                'message'=>$decode['error']['error_category'].' - '.$decode['error']['error_message']
            ]);
            return false;
        }else{
            return $decode['response_data'];
        }
    }

    /**
     * @return array
     */
    protected function getApiCurlParams(){
        return [
            'Authorization'=>'Bearer '.$this->token,
            'Accept-Encoding'=>'gzip',
            'Accept'=> 'application/json'
        ];
    }

    /**
     * @param $method
     * @param null $id
     * @return array|bool
     */
    public function apiGET($method, $id=null){
        self::checkMethod($method);
        $url = $this->apiUrl.'api/'.$method.(!empty($id)?'/'.$id:'');
        $this->curlQuery($url, false, 'GET', $this->getApiCurlParams());
        return $this->processingApiResponse($this->body);
    }

    /**
     * @param $method
     * @param $id
     * @param $data
     * @return array|bool
     */
    public function apiPUT($method, $id, $data){
        self::checkMethod($method);
        $url = $this->apiUrl.'api/'.$method.'/'.(int)$id;
        $this->curlQuery($url, json_encode($data), 'PUT', $this->getApiCurlParams()+['Content-Type'=>'application/json']);
        return $this->processingApiResponse($this->body);
    }

    /**
     * @param $method
     * @param $data
     * @return array|bool
     */
    public function apiPOST($method, $data){
        self::checkMethod($method);
        $url = $this->apiUrl.'api/'.$method;
        $this->curlQuery($url, json_encode($data), 'POST', $this->getApiCurlParams()+['Content-Type'=>'application/json']);
        return $this->processingApiResponse($this->body);
    }

    /**
     * @param $method
     * @param $id
     * @return array|bool
     */
    public function apiDELETE($method, $id){
        self::checkMethod($method);
        $url = $this->apiUrl.'api/'.$method.'/'.(int)$id;
        $this->curlQuery($url, false, 'DELETE', $this->getApiCurlParams());
        return $this->processingApiResponse($this->body);
    }

    /**
     * @param $method
     * @throws CHttpException
     */
    private static function checkMethod($method){
        $methods = [
            'outboundmessage',
            'tags',
            'customfields',
            'contacts',
            'identities',
            'campaigns',
            'campaignjobs',
            'channels',
            'notifications'
        ];
        if(!in_array($method, $methods)){
            throw new CHttpException(405, 'Wrong method, you can use only these methods - '.implode(', ',$methods).'. See documentation - http://docs.sentlyweb.apiary.io/');
        }
    }
}