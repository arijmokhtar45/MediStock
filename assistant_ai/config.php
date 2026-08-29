<?php

define('MEDISTOCK_AI_ENABLED', true);
define('MEDISTOCK_AI_MAX_MESSAGE_LENGTH', 1000);
define('MEDISTOCK_AI_MAX_HISTORY', 8);

define('MEDISTOCK_AI_MODEL', getenv('GROQ_MODEL') ?: 'openai/gpt-oss-20b');
define('MEDISTOCK_AI_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions');

function medistock_ai_api_key(): string
{
    $env = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
    if (is_string($env) && trim($env) !== '') {
        return trim($env);
    }

    $dotenv = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (is_readable($dotenv)) {
        foreach (file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === 'GROQ_API_KEY' && $value !== '') {
                return $value;
            }
        }
    }

    return '';
}
