<?php
require_once 'config.php';
if(isset($_SESSION['logged_in'])){
$clicksbyip = 0;
$clicksbybowser = 0;

if(isset($_POST['type']) && !empty($_POST['type']) && ($_POST['type'] == 'sts')){
	// Include database configuration file

	// Include URL Shortener library file
	require_once 'Shortener.class.php';

	// Initialize Shortener class and pass PDO object
	$shortener = new Shortener($db);
	if(!empty($_POST['ip']))
	$clicksbyip = $shortener->getNoOfClicksByIp($_POST['ip']);
	if(!empty($_POST['browser']))
	$clicksbybowser = $shortener->getNoOfClicksByBrowser($_POST['browser']);
}
?>

<html>
    <head>
        <title>
            Short URL
        </title>
    </head>
    <body>
    	
        <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
        	<input type="hidden" name="type" value="sts">
            <label>Click By IP<input type="text" name="ip" value="<?php echo $_POST['ip']??''; ?>"> <?php echo $clicksbyip; ?></label><br>
            <label>Click By Browser<input type="text" name="browser" value="<?php echo $_POST['browser']??''; ?>"> <?php echo $clicksbybowser; ?></label><br>
            <button type="submit">Submit</button>
        </form>
        <a href="<?php echo "http://" . $_SERVER['SERVER_NAME']; ?>/url-short/create.php">Create Short Url</a> | <a href="<?php echo "http://" . $_SERVER['SERVER_NAME']; ?>/url-short/logout.php">Logout</a>
    </body>
</html>
<?php
}else{
$msg = '';
if(isset($_POST['type']) && !empty($_POST['type']) && ($_POST['type'] == 'login')){
	// Include URL Shortener library file
	require_once 'Shortener.class.php';

	// Initialize Shortener class and pass PDO object
	$shortener = new Shortener($db);
	$isLoggedIn = $shortener->login();
	if(!$isLoggedIn){
		$msg = 'Unable to login';
	}else{
		$_SESSION['userinfo'] = $isLoggedIn;
		$_SESSION['logged_in'] = $isLoggedIn['id'];
		header('Location: '.$_SERVER['PHP_SELF']); die;
	}
}
if(isset($_POST['type']) && !empty($_POST['type']) && ($_POST['type'] == 'register')){
	// Include URL Shortener library file
	require_once 'Shortener.class.php';
	// Initialize Shortener class and pass PDO object
	$shortener = new Shortener($db);
	$msg = $shortener->register();
	$_POST = [];
}
?>
<html>
    <head>
        <title>
            Short URL-Login
        </title>
    </head>
    <body>
    	<h3>Login</h3>
        <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
        	<input type="hidden" name="type" value="login">
            <label>Email<input type="email" name="email" value="<?php echo $_POST['email']??''; ?>"> </label><br>
            <label>Password<input type="password" name="password" value=""> </label><br>
            <button type="submit">Login</button>
        </form>
        <h3>Register</h3>
        <h4 style="color: green;"><?php echo $msg??''; ?></h4>
        <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
        	<input type="hidden" name="type" value="register">
            <label>First Name<input type="text" name="first_name" value="<?php echo $_POST['first_name']??''; ?>" required> </label><br>
            <label>Last Name<input type="text" name="last_name" value="<?php echo $_POST['last_name']??''; ?>" required> </label><br>
            <label>Email<input type="email" name="email" value="<?php echo $_POST['email']??''; ?>" required> </label><br>
            <label>Password<input type="password" name="password" value="" required> </label><br>
            <button type="submit">Register</button>
        </form>
    </body>
</html>

<?php } ?>