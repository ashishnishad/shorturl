<?php
class Shortener
{
    protected static $chars = "abcdfghjkmnpqrstvwxyz|ABCDFGHJKLMNPQRSTVWXYZ|0123456789";
    protected static $table = "short_urls";
    protected static $checkUrlExists = false;
    protected static $codeLength = 7;

    protected $pdo;
    protected $timestamp;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
        $this->timestamp = date("Y-m-d H:i:s");
    }

    public function urlToShortCode($url){
        if(empty($url)){
            throw new Exception("No URL was supplied.");
        }

        if($this->validateUrlFormat($url) == false){
            throw new Exception("URL does not have a valid format.");
        }

        if(self::$checkUrlExists){
            if (!$this->verifyUrlExists($url)){
                throw new Exception("URL does not appear to exist.");
            }
        }

        $shortCode = $this->urlExistsInDB($url);
        //echo '<pre>'; print_r($shortCode); die;
        if($shortCode == false){
            $shortCode = $this->createShortCode($url);
        }else{
            $this->assignUserToUrl($shortCode['id']);
        }

        return $shortCode;
    }

    protected function validateUrlFormat($url){
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    protected function verifyUrlExists($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch,  CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return (!empty($response) && $response != 404);
    }

    protected function urlExistsInDB($url){
        $query = "SELECT * FROM ".self::$table." WHERE long_url = :long_url LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $params = array(
            "long_url" => $url
        );
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_NAMED);
        return (empty($result)) ? false : $result;
    }

    protected function createShortCode($url){
        $shortCode = $this->generateRandomString(self::$codeLength);
        $data = $this->insertUrlInDB($url, $shortCode);
        return $data;
    }
    
    protected function generateRandomString($length = 6){
        $sets = explode('|', self::$chars);
        $all = '';
        $randString = '';
        foreach($sets as $set){
            $randString .= $set[array_rand(str_split($set))];
            $all .= $set;
        }
        $all = str_split($all);
        for($i = 0; $i < $length - count($sets); $i++){
            $randString .= $all[array_rand($all)];
        }
        $randString = str_shuffle($randString);
        return $randString;
    }

    protected function insertUrlInDB($url, $code){
        $query = "INSERT INTO ".self::$table." (long_url, short_code, created) VALUES (:long_url, :short_code, :timestamp)";
        $stmnt = $this->pdo->prepare($query);
        $params = array(
            "long_url" => $url,
            "short_code" => $code,
            "timestamp" => $this->timestamp
        );
        $stmnt->execute($params);
        $insert_id = $this->pdo->lastInsertId();
        $this->assignUserToUrl($insert_id);
        return ['id' => $insert_id, 'short_code' => $code];
    }

    protected function assignUserToUrl($insert_id){

        if(!$this->isAssignedUser($_SESSION['logged_in'],$insert_id)){
            $query = "INSERT INTO user_urls (user_id, url_id, created) VALUES (:user_id, :url_id, :timestamp)";
            $stmnt = $this->pdo->prepare($query);
            $params = array(
                "user_id" => $_SESSION['logged_in'],
                "url_id" => $insert_id,
                "timestamp" => $this->timestamp
            );
            $stmnt->execute($params);
        }
        return true;
    }

    protected function isAssignedUser($user_id, $url_id){
        $query = "SELECT COUNT('*') AS urlCount FROM user_urls WHERE user_id = :user_id AND url_id = :url_id";
        $stmt = $this->pdo->prepare($query);
        $params=array(
            "user_id" => $user_id,
            "url_id" => $url_id
        );
        $stmt->execute($params);

        $result = $stmt->fetch();
        return (empty($result)) ? false : $result['urlCount'];
    }
    
    public function shortCodeToUrl($code, $increment = true){
        if(empty($code)) {
            throw new Exception("No short code was supplied.");
        }

        if($this->validateShortCode($code) == false){
            throw new Exception("Short code does not have a valid format.");
        }

        $urlRow = $this->getUrlFromDB($code);
        if(empty($urlRow)){
            throw new Exception("Short code does not appear to exist.");
        }

        if($increment == true){
            $this->incrementCounter($urlRow["id"]);
        }
        $this->visitorLog($urlRow["id"]);

        return $urlRow["long_url"];
    }

    protected function validateShortCode($code){
        $rawChars = str_replace('|', '', self::$chars);
        return preg_match("|[".$rawChars."]+|", $code);
    }

    protected function getUrlFromDB($code){
        $query = "SELECT id, long_url FROM ".self::$table." WHERE short_code = :short_code LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $params=array(
            "short_code" => $code
        );
        $stmt->execute($params);

        $result = $stmt->fetch();
        return (empty($result)) ? false : $result;
    }

    protected function incrementCounter($id){
        $query = "UPDATE ".self::$table." SET hits = hits + 1 WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $params = array(
            "id" => $id
        );
        $stmt->execute($params);
    }

    public function getNoOfClicks($code){
        $query = "SELECT hits AS clicks FROM ".self::$table." WHERE short_code = :short_code";
        $stmt = $this->pdo->prepare($query);
        $params=array(
            "short_code" => $code
        );
        $stmt->execute($params);

        $result = $stmt->fetch();
        return (empty($result)) ? false : $result['clicks'];
    }
    
    public function getNoOfClicksByIp($ip){
        $user_id = $_SESSION['logged_in'];
        $query = "SELECT COUNT('*') AS clicks FROM statistics s JOIN user_urls u ON s.url_id = u.url_id LEFT JOIN user_urls u1 ON s.url_id = u1.url_id  AND u.id < u1.id WHERE ip_address = :ip AND u1.user_id = :user_id";
        $stmt = $this->pdo->prepare($query);
        $params=array(
            "ip" => $ip,
            "user_id" => $user_id
        );
        $stmt->execute($params);
       // echo '<pre>'; $stmt->debugDumpParams(); echo '</pre>'; die;
        $result = $stmt->fetch();
        return (empty($result)) ? false : $result['clicks'];
    }
    
    public function getNoOfClicksByBrowser($browser){
        $user_id = $_SESSION['logged_in'];
        $query = "SELECT COUNT('*') AS clicks FROM statistics s JOIN user_urls u ON s.url_id = u.url_id LEFT JOIN user_urls u1 ON s.url_id = u1.url_id  AND u.id < u1.id WHERE browser = :browser AND u1.user_id = :user_id";
        $stmt = $this->pdo->prepare($query);
        $params=array(
            "browser" => $browser,
            "user_id" => $user_id
        );
        $stmt->execute($params);

        $result = $stmt->fetch();
        return (empty($result)) ? false : $result['clicks'];
    }

    protected function visitorLog($id){
        
        $query = "INSERT INTO statistics (url_id, ip_address, browser, created) VALUES (:url_id, :ip_address, :browser, :created)";
        $stmnt = $this->pdo->prepare($query);

        $user_ip_address = $this->getUserIP(); 
        $user_agent = $this->getBrowser();
        //echo '<pre>'; print_r($user_agent); die;
        $params = array(
            "url_id" => $id,
            "ip_address" => $user_ip_address,
            "browser" => $user_agent['name'],
            "created" => $this->timestamp
        );
        $stmnt->execute($params);

        return $this->pdo->lastInsertId();
    }

    protected function getUserIP()
    {
        // Get real visitor IP behind CloudFlare network
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
                  $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
                  $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        $client  = @$_SERVER['HTTP_CLIENT_IP'];
        $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote  = $_SERVER['REMOTE_ADDR'];

        if(filter_var($client, FILTER_VALIDATE_IP))
        {
            $ip = $client;
        }
        elseif(filter_var($forward, FILTER_VALIDATE_IP))
        {
            $ip = $forward;
        }
        else
        {
            $ip = $remote;
        }

        return $ip;
    }

    protected function getBrowser() 
    { 
        $u_agent = $_SERVER['HTTP_USER_AGENT']; 
        $bname = 'Unknown';
        $platform = 'Unknown';
        $version= "";

        //First get the platform?
        if (preg_match('/linux/i', $u_agent)) {
            $platform = 'linux';
        }
        elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
            $platform = 'mac';
        }
        elseif (preg_match('/windows|win32/i', $u_agent)) {
            $platform = 'windows';
        }
        
        // Next get the name of the useragent yes seperately and for good reason
        if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) 
        { 
            $bname = 'Internet Explorer'; 
            $ub = "MSIE"; 
        } 
        elseif(preg_match('/Firefox/i',$u_agent)) 
        { 
            $bname = 'Mozilla Firefox'; 
            $ub = "Firefox"; 
        } 
        elseif(preg_match('/Chrome/i',$u_agent)) 
        { 
            $bname = 'Google Chrome'; 
            $ub = "Chrome"; 
        } 
        elseif(preg_match('/Safari/i',$u_agent)) 
        { 
            $bname = 'Apple Safari'; 
            $ub = "Safari"; 
        } 
        elseif(preg_match('/Opera/i',$u_agent)) 
        { 
            $bname = 'Opera'; 
            $ub = "Opera"; 
        } 
        elseif(preg_match('/Netscape/i',$u_agent)) 
        { 
            $bname = 'Netscape'; 
            $ub = "Netscape"; 
        } 
        
        // finally get the correct version number
        $known = array('Version', $ub, 'other');
        $pattern = '#(?<browser>' . join('|', $known) .
        ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if (!preg_match_all($pattern, $u_agent, $matches)) {
            // we have no matching number just continue
        }
        
        // see how many we have
        $i = count($matches['browser']);
        if ($i != 1) {
            //we will have two since we are not using 'other' argument yet
            //see if version is before or after the name
            if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
                $version= $matches['version'][0];
            }
            else {
                $version= $matches['version'][1];
            }
        }
        else {
            $version= $matches['version'][0];
        }
        
        // check if we have a number
        if ($version==null || $version=="") {$version="?";}
        
        return array(
            'userAgent' => $u_agent,
            'name'      => $bname,
            'version'   => $version,
            'platform'  => $platform,
            'pattern'    => $pattern
        );
    }

    private function userExist(){
        $email = $_POST['email'];
        $query = "SELECT COUNT('*') AS emailCount FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($query);
        $params=array(
            "email" => $email
        );
        $stmt->execute($params);

        $result = $stmt->fetch();
        return (empty($result)) ? false : $result['emailCount'];
    }

    public function register(){
        if($this->userExist()){
            return "User Already Exist";
        }
        
        $query = "INSERT INTO users (first_name, last_name, email, password, created) VALUES (:first_name, :last_name, :email, :password, :created)";
        $stmnt = $this->pdo->prepare($query);
        $params = array(
            "first_name" => $_POST['first_name'],
            "last_name" => $_POST['last_name'],
            "email" => $_POST['email'],
            "password" => md5($_POST['password']),
            "created" => $this->timestamp
        );
        $stmnt->execute($params);
         return "User Registered.";
    }

    public function login(){
       // echo '<pre>'; print_r($this->userExist()); die;
        if(!$this->userExist()){
            return false;
        }
        
        $query = "SELECT * FROM users WHERE email = :email AND password = :password";
        $stmt  = $this->pdo->prepare($query);
        $params = array(
            "email" => $_POST['email'],
            "password" => md5($_POST['password'])
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_NAMED);
        return $row;
    }
}