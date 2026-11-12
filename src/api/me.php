<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../config/db.php';

require_method('GET');

$user = require_auth(getDB());

json_response(200, true, 'OK', ['user' => $user]);