<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once '../libs/xmpp/core/jaxl.class.php';
$jaxl = new JAXL(array(
                      'user' => 'florin.faspay@gmail.com',
                      'pass' => 'xx',
                      'host' => 'talk.google.com',
                      'domain' => 'google.com',
                      'authType' => 'PLAIN',
                      'logLevel' => 5,
                 ));

// Send message after successful authentication
function postAuth($payload, $jaxl)
{
    global $argv;
    $jaxl->sendMessage($argv[1], $argv[2]);
    $jaxl->shutdown();
}

// Register callback on required hook (callback'd method will always receive 2 params)
$jaxl->addPlugin('jaxl_post_auth', 'postAuth');

// Start Jaxl core
$jaxl->startCore('stream');
