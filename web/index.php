<?php
require('../vendor/autoload.php');

// Using Medoo namespace
use Medoo\Medoo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize
$app = new Silex\Application();
$app['debug'] = true;
$app['mail'] = new PHPMailer(true);                              // Passing `true` enables exceptions
$app['db'] = new Medoo([
  'database_type' => 'mysql',
  'database_name' => 'q5r6zfzoeqsnvp5q',
  'server' => 'vvfv20el7sb2enn3.cbetxkdyhwsb.us-east-1.rds.amazonaws.com',
  'username' => 'q7v7t2hzkho69580',
  'password' => 'fifpiwbyfv1gc6xi'
]);

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// Register Session service
$app->register(new Silex\Provider\SessionServiceProvider());

// Our web handlers

if($app['session']->get('user')){
  $app['user'] = $app['session']->get('user');
}

$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');

  return $app['twig']->render('index.twig', ['title' => 'Home']);
});

$app->get('/signup', function() use($app) {
  $app['monolog']->addDebug('logging output.');

  if($app['session']->get('user')){
    return $app->redirect('/');
  }

  return $app['twig']->render('signup.twig', ['title'=> 'Sign Up']);
});

$app->post('/register', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');

  if (ctype_alpha($request->get('firstname')) === false)
  {
          return new Response('First Name should only contain Alphabets!', 500);
  }

  if (ctype_alpha($request->get('lastname')) === false)
  {
    return new Response('Last Name should only contain Alphabets!', 500);
  }

  $userdata = $app['db']->select('aadhartbl', ['firstname', 'lastname', 'aadharno'],[
    'firstname' => $request->get('firstname'),
    'lastname' => $request->get('lastname'),
    'aadharno' => md5($request->get('aadhar'))
    ]);

  if(count($userdata) < 1){
    return new Response('Aadhar does not exists or invalid!', 500);
  }

  $userdata = $app['db']->select('user', ['fname', 'lname', 'aadhar'],[
    'fname' => $request->get('firstname'),
    'lname' => $request->get('lastname'),
    'aadhar' => md5($request->get('aadhar'))
    ]);

  if(count($userdata) > 0){
    return new Response('User already exists!', 500);
  }

  $str = 'abcdefgijklmnopqrstuvwxyz';
  $shuffled = str_shuffle($str);
  $hash = md5($shuffled);

  $app['db']->insert('user', [
    'fname' => $request->get('firstname'),
    'lname' => $request->get('lastname'),
    'uname' => $request->get('email'),
    'email' => $request->get('email'),
    'aadhar' => md5($request->get('aadhar')),
    'state' => $request->get('state'),
    'password' => md5($request->get('pass')),
    'active' => 0,
    'hash' => $hash
  ]);

  try {
    //Server settings
    $app['mail']->SMTPDebug = 2;                                 // Enable verbose debug output
    $app['mail']->isSMTP();                                      // Set mailer to use SMTP
    $app['mail']->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
    $app['mail']->SMTPAuth = true;                               // Enable SMTP authentication
    $app['mail']->Username = 'thewirecoy@gmail.com';                 // SMTP username
    $app['mail']->Password = 'Success@2020';                           // SMTP password
    $app['mail']->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
    $app['mail']->Port = 587;                                    // TCP port to connect to

    //Recipients
    $app['mail']->setFrom('thewirecoy@gmail.com', 'Petition waale');
    $app['mail']->addAddress($request->get('email'), $request->get('firstname') . " " . $request->get('lastname'));     // Add a recipient

    //Content
    $app['mail']->isHTML(true);                                  // Set email format to HTML
    $app['mail']->Subject = 'Pending Action';
    $app['mail']->Body    = 'To complete registration open this verfication link or paste in browser url -> <a href="https://polar-oasis-15100.herokuapp.com/verify/'.md5($request->get('aadhar')).'/'. $hash .'">https://polar-oasis-15100.herokuapp.com/verify/'.md5($request->get('aadhar')).'/'. $hash .'</a>';
    $app['mail']->AltBody = 'To complete registration open this verfication link or paste in browser url -> https://polar-oasis-15100.herokuapp.com/verify/'.md5($request->get('aadhar')).'/'. $hash;

    $app['mail']->send();
    echo 'Message has been sent';
  } catch (Exception $e) {
      echo 'Message could not be sent.';
      echo 'Mailer Error: ' . $app['mail']->ErrorInfo;
      return new Response('Error sending mail', 504);
  }

  return new Response('Done', 200);
});

$app->get('/verify/{aadhar}/{hash}', function($aadhar, $hash) use($app) {
  $app['monolog']->addDebug('logging output.');

  $data = $app['db']->select('user', ['fname'], [
    'aadhar' => $aadhar,
    'hash'  => $hash,
    'active' => 0
  ]);

  if(count($data) < 1){
    return $app['twig']->render('verify.twig', ['title'=> 'Mail Verification', 'error' => 'The verification link is either expired or invalid!']);
  }

  $app['db']->update('user', ['active' => 1], ['aadhar' => $aadhar]);

  return $app['twig']->render('verify.twig', ['title'=> 'Mail Verification', 'success' => 'Hey ' . $data[0]['fname']. ', your account has been verified! Proceed to login.' ]);

});


$app->get('/login', function() use($app) {
  $app['monolog']->addDebug('logging output.');

  if($app['session']->get('user')){
    return $app->redirect('/');
  }

  return $app['twig']->render('login.twig', ['title' => 'Log In']);
});

$app->get('/create-petition', function() use($app) {
  $app['monolog']->addDebug('logging output.');

  if(!$app['session']->get('user')){
    return $app->redirect('/login');
  }

  return $app['twig']->render('create-petition.twig', ['title' => 'Create Petition']);
});

$app->post('/checklogin', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');

  $data = $app['db']->select('user', '*', [
    'uname' => $request->get('mail'),
    'password' => md5($request->get('pass')),
    'active' => 1
  ]);

  if(count($data) < 1){
    return new Response('Invalid credentials!', 500);
  }

  $app['session']->set('user', array(
    'username' => $request->get('mail'),
    'aadhar' => $data[0]['aadhar'],
    'fname' => $data[0]['fname'],
    'lname' => $data[0]['lname'],
    'state' => $data[0]['state']
    ));

    $app['twig']->addGlobal('user', $app['session']->get('user'));

    return new Response('Authenticated Successfully!');

});

$app->get('/logout', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  $app['user'] = null;
  $app['session']->remove('user');
  return $app->redirect('/');
});



//create petition
$app->get('/create-petition', function(Request $request) use($app){
  $app['monolog']->addDebug('logging output.');

  if($app['session']->get('user')){
    return $app->redirect('/');
  }
  return $app['twig']->render('create-petition.twig', ['title'=> 'Create Petition']);
});

//show single petition
$app->get('/single-petition/{id}', function($id) use($app){
  $app['monolog']->addDebug('logging output.');

  if(!$app['session']->get('user')){
    return $app->redirect('/');
  }

  $data = $app['db']->select('petition', '*', ['id' => $id]);
  if(count($data) < 1){
    return $app->redirect('/');   //TODO : Redirect to 404
  }

  $petition = $data[0];

  return $app['twig']->render('single-petition.twig', ['title'=> 'Petition : ' . $petition->title, 'petition' => $petition]);
});

//show all petitions
$app->get('/petition-listing', function() use($app){
  $app['monolog']->addDebug('logging output.');

  // if($app['session']->get('user')){
  //   return $app->redirect('/');
  // }

  $petitions = $app['db']->select('petition', '*');
  if(count($petitions) < 1){
    return $app->redirect('/');   //TODO : Redirect to 404
  }

  return $app['twig']->render('petition-listing.twig', ['title'=> 'All Petitions', 'petitions' => $petitions]);
});



$app->post('/post-petition', function(Request $request) use($app){

  try{
  $app['db']->insert('petition', [
    'title' => $request->get('petition-title'),
    'description' => $request->get('petition-description'),
    'targetsign' => $request->get('target'),
    'bannerimage' => $request->get('petition-banner'),
    'imagedescription' => $request->get('photo-description'),
    'photos' => $request->get('petition-photo'),
    'letter' => $request->get('petition-letter'),
    'recepient' => $request->get('petition-recipient-name'),
    'recepientdesig' => $request->get('petition-recipient-designation'),
    'createdby' => $app['user']->fname.' '.$app['user']->lname,
    'createdon' => date("F j, Y, g:i a")
  ]);
  }
  catch(Exception $er){
     return new Response("Error occurred", 500);
  }

  return new Response("Petition created", 200);
});

//profile 
$app->get('/profile', function() use($app) {
  $app['monolog']->addDebug('logging output.');

//if($app['session']->get('user')){
  // return $app->redirect('/profile');
// }

 //$user = $app['db']->select('user', '*', ['id' => $id]);

  return $app['twig']->render('profile.twig', ['title' => 'Profile']);
});

//contact
$app->get('/contact', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('contact.twig', ['title' => 'Contact Us']);
});

$app->run();
