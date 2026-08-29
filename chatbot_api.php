<?php
require 'config/db.php';
require 'includes/auth.php';
require_login();
require 'config/groq.php';

header('Content-Type: application/json; charset=UTF-8');
chatbot_handle_request($pdo);

// chatbot_handle_request() termine toujours la requête.
http_response_code(500);
echo json_encode(['ok' => false, 'error' => 'Erreur interne.'], JSON_UNESCAPED_UNICODE);


