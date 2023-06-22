<?php
require_once 'config.php';
if(!isset($_SESSION['logged_in'])){
    die('Login Required.');
}
if(isset($_POST) && !empty($_POST)){
// Include database configuration file


// Include URL Shortener library file
require_once 'Shortener.class.php';

// Initialize Shortener class and pass PDO object
$shortener = new Shortener($db);

// Long URL
$longURL = $_POST['url'];

// Prefix of the short URL 
$shortURL_Prefix = 'http://localhost/url-short/'; // with URL rewrite
//$shortURL_Prefix = 'http://localhost/url-short/?c='; // without URL rewrite
$ip = "::1";
$bowser = "Google Chrome";
try{
    // Get short code of the URL
    $shortCode = $shortener->urlToShortCode($longURL);
    //print_r($shortCode);
    $clicks = $shortener->getNoOfClicks($shortCode['short_code']);
    $clicksbyip = $shortener->getNoOfClicksByIp($ip);
    $clicksbybowser = $shortener->getNoOfClicksByBrowser($bowser);
    //echo '<pre>'; print_r($clicks); die;
    // Create short URL
    $shortURL = $shortURL_Prefix.$shortCode['short_code'];
    
    // Display short URL
    echo 'Short URL: '.$shortURL.'<br>'; 
    echo 'Clicks: '.$clicks.'<br>';
    echo 'Clicks IP: '.$clicksbyip.'<br>';
    echo 'Clicks Browser: '.$clicksbybowser.'<br>';
}catch(Exception $e){
    // Display error
    echo $e->getMessage();
}
}
?>
<html>
    <head>
        <title>
            New Short URL
        </title>
    </head>
    <body>
        <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
            <input type="text" name="url" value="" required>
            <button type="submit">Submit</button>
        </form>
        <a href="<?php echo "http://" . $_SERVER['SERVER_NAME']; ?>/url-short">Home</a> | <a href="<?php echo "http://" . $_SERVER['SERVER_NAME']; ?>/url-short/logout.php">Logout</a>
    </body>
</html>