<?php
if(isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response']))
  {
        $secret ='Api PrivateKey on your server not accesible for usser  bots or exgirfriends';
        $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['g-recaptcha-response']);
        $responseData = json_decode($verifyResponse);
        if($responseData->success)
        {
            $Result = 'Your contact request have submitted successfully.';
        }
        else
        {
            $Result = 'Robot verification failed, please try again.';
        }
   }
   echo("Result: ".$Result." Response ".$_POST['g-recaptcha-response']);
?>
