<?php

require dirname(__DIR__) . '/config/db.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/config/groq.php';
require __DIR__ . '/config.php';
require __DIR__ . '/guard.php';
require __DIR__ . '/db_context.php';

function medistock_ai_system_prompt(): string
{
    return <<<'PROMPT'
Tu es MediStock Assistant, l’assistant interne de MediStock. Tu réponds uniquement en français.
Tu dois te baser exclusivement sur le contexte SQL fourni par le serveur, en lecture seule.
Tu ne dois pas inventer des chiffres, des stocks, des fournisseurs, des lots, des alertes ou des montants.
Si l’information n’est pas dans le contexte, dis clairement que tu ne peux pas la confirmer.
Tu peux aider sur les médicaments, catégories, lots, fournisseurs, commandes, réceptions, ventes, tickets, alertes, stock et prévisions.
Tu refuses toute demande hors sujet, médicale, diagnostic, prescription, posologie ou conseil médical.
PROMPT;
}

function medistock_ai_call_groq(PDO $pdo, string $question, string $page, array $history = []): string
{
    require_login();
    medistock_ai_require_session();

    $apiKey = medistock_ai_api_key();
    if ($apiKey === '') {
        throw new RuntimeException('Clé Groq absente.');
    }

    $context = medistock_ai_fetch_context($pdo, $question, $page);
    $messages = [['role' => 'system', 'content' => medistock_ai_system_prompt()]];

    foreach (array_slice($history, -6) as $entry) {
        if (is_array($entry) && isset($entry['role'], $entry['content'])) {
            $messages[] = ['role' => $entry['role'], 'content' => (string) $entry['content']];
        }
    }

    $messages[] = ['role' => 'user', 'content' => "Contexte MediStock en temps réel :\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n\nQuestion :\n" . $question];

    $payload = json_encode([
        'model' => MEDISTOCK_AI_MODEL,
        'messages' => $messages,
        'temperature' => 0.15,
        'max_completion_tokens' => 700,
        'stream' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init(MEDISTOCK_AI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Erreur de connexion au service Groq.');
    }

    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300) {
        $msg = $data['error']['message'] ?? 'Erreur Groq.';
        throw new RuntimeException($msg);
    }

    $content = $data['choices'][0]['message']['content'] ?? '';
    if (!is_string($content) || trim($content) === '') {
        throw new RuntimeException('Réponse vide du modèle.');
    }

    return trim($content);
}

function medistock_ai_handle_request(PDO $pdo): void
{
    header('Content-Type: application/json; charset=UTF-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée.']);
        return;
    }

    if (!medistock_ai_validate_csrf((string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'Session expirée. Rechargez la page.']);
        return;
    }

    try {
        $question = medistock_ai_validate_question((string) ($_POST['message'] ?? ''));

        if (preg_match('/(diagnostic|prescription|posologie|dose|dosage|traitement|patient|symptom|interaction|médecin|medecin)/i', $question)) {
            echo json_encode(['ok' => true, 'answer' => 'Je suis dédié à MediStock et je peux uniquement aider concernant la gestion de la pharmacie, du stock, des commandes, des ventes, des alertes et des prévisions.']);
            return;
        }

        if (!medistock_ai_is_medi_stock_question($question) && !preg_match('/^(bonjour|salut|bonsoir|merci|hello|help|aide)\b/i', $question)) {
            echo json_encode(['ok' => true, 'answer' => 'Je suis dédié à MediStock et je peux uniquement aider concernant la gestion de la pharmacie, du stock, des commandes, des ventes, des alertes et des prévisions.']);
            return;
        }

        $history = json_decode((string) ($_POST['history'] ?? '[]'), true);
        $history = is_array($history) ? $history : [];
        $page = (string) ($_POST['page'] ?? '');
        $answer = medistock_ai_call_groq($pdo, $question, $page, $history);
        echo json_encode(['ok' => true, 'answer' => nl2br(htmlspecialchars($answer, ENT_QUOTES, 'UTF-8'))], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('MediStock AI: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
