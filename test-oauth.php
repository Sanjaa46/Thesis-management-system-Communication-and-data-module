<?php

// test-oauth.php
$clientId = '798563af3a3b4263a3625a5283e288a5';
$redirectUri = 'http://127.0.0.1:4000/auth';
$authUrl = 'https://auth.num.edu.mn/oauth2/oauth/authorize';
$state = bin2hex(random_bytes(16));

$url = $authUrl . '?' . http_build_query([
    'client_id' => $clientId,
    'response_type' => 'code',
    'redirect_uri' => $redirectUri,
    'state' => $state,
]);

echo "Visit this URL to test: <a href='$url'>$url</a>";