<?php
/**
 * Configuration Groq côté serveur.
 * La clé doit être définie dans l'environnement Apache/PHP sous XAMPP :
 * GROQ_API_KEY=...
 */

function groq_load_dotenv(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($envFile)) {
        return;
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
            $value = trim($value, "\\\"'");
        }
        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

function groq_api_key(): string
{
    groq_load_dotenv();
    return trim((string) (getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '')));
}

function groq_model(): string
{
    groq_load_dotenv();
    return (string) (getenv('GROQ_MODEL') ?: ($_ENV['GROQ_MODEL'] ?? 'openai/gpt-oss-20b'));
}

function groq_endpoint(): string
{
    return 'https://api.groq.com/openai/v1/chat/completions';
}

function groq_call(array $messages): string
{
    $apiKey = groq_api_key();
    if ($apiKey === '') {
        throw new RuntimeException('Le chatbot n’est pas configuré : GROQ_API_KEY est absente du serveur.');
    }

    $payload = json_encode([
        'model' => groq_model(),
        'messages' => $messages,
        'temperature' => 0.15,
        'max_completion_tokens' => 700,
        'stream' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init(groq_endpoint());
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Impossible de joindre le service du chatbot : ' . $curlError);
    }

    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300) {
        $providerMessage = $data['error']['message'] ?? 'réponse HTTP ' . $status;
        throw new RuntimeException('Le service Groq a refusé la requête : ' . $providerMessage);
    }

    $content = $data['choices'][0]['message']['content'] ?? '';
    if (!is_string($content) || trim($content) === '') {
        throw new RuntimeException('Le chatbot a renvoyé une réponse vide.');
    }

    return trim($content);
}

function chatbot_system_prompt(): string
{
    return <<<'PROMPT'
Tu es MediStock Assistant, l’assistant interne de l’application MediStock. Tu réponds uniquement en français et uniquement dans le périmètre suivant : fonctionnement des interfaces MediStock, médicaments, catégories, fournisseurs, lots, commandes fournisseurs, réceptions, stock, mouvements, ventes, ticket, alertes et prévisions locales. Tu peux expliquer les règles FEFO, les statuts, les contrôles de stock et les procédures d’utilisation.

Règles strictes :
1. Le contexte fourni par le serveur est la seule source de vérité pour les données actuelles. Ne devine jamais une quantité, un prix, un lot, une alerte ou un statut absent du contexte.
2. Tu ne dois jamais produire de SQL, modifier une donnée, exécuter une action, révéler un mot de passe, une clé API ou une donnée personnelle inutile. Tu es un assistant en lecture seule.
3. Si la question est hors MediStock, réponds exactement : « Je suis dédié à MediStock et je peux uniquement aider concernant la gestion de la pharmacie, du stock, des commandes, des ventes, des alertes et des prévisions. »
4. Pour toute demande de diagnostic, prescription, posologie, interaction ou conseil médical destiné à un patient, refuse et précise que MediStock est un outil de gestion et ne fournit pas de conseil médical. Le champ dosage d’un médicament est une donnée de catalogue, pas une recommandation médicale.
5. Si l’information n’est pas présente dans le contexte ou dans les règles fonctionnelles connues, dis clairement que tu ne peux pas la confirmer et indique quelle page permet de la vérifier.
6. Sois concis, professionnel et pédagogique. Distingue toujours une information réellement lue en base d’une explication générale du fonctionnement.
PROMPT;
}

function chatbot_context(PDO $pdo, string $question, string $page): string
{
    $context = [
        'Page actuellement ouverte' => $page !== '' ? $page : 'inconnue',
        'Règles applicatives' => 'Le stock est calculé depuis les lots actifs non expirés. Les ventes consomment les lots en FEFO. Une réception de commande crée les lots et un mouvement d’entrée dans une transaction. Les lots expirés ne sont pas vendables.',
    ];

    $summary = $pdo->query("SELECT
        (SELECT COUNT(*) FROM medicaments WHERE actif = 1) AS medicaments_actifs,
        (SELECT COUNT(*) FROM fournisseurs) AS fournisseurs,
        (SELECT COUNT(*) FROM commandes WHERE statut IN ('en_attente','livree_partiellement')) AS commandes_a_receptionner,
        (SELECT COUNT(*) FROM alertes WHERE statut = 'active') AS alertes_actives,
        (SELECT COALESCE(SUM(montant_total), 0) FROM ventes WHERE DATE(date_vente) = CURDATE()) AS ventes_du_jour")->fetch(PDO::FETCH_ASSOC);
    $context['Résumé actuel'] = $summary ?: [];

    $term = trim(preg_replace('/[^\p{L}\p{N} ._-]/u', ' ', $question));
    $words = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY);
    $stopWords = ['quel', 'quelle', 'quels', 'quelles', 'est', 'sont', 'pour', 'dans', 'avec', 'mon', 'ma', 'mes', 'les', 'des', 'une', 'sur', 'stock', 'medicament', 'médicament'];
    $searchWords = array_values(array_filter($words, static fn($word) => mb_strlen($word) >= 3 && !in_array(mb_strtolower($word), $stopWords, true)));
    if ($searchWords) {
        $conditions = [];
        $params = [];
        foreach (array_slice($searchWords, 0, 5) as $word) {
            $conditions[] = '(m.nom LIKE ? OR m.code_barre LIKE ? OR m.fabricant LIKE ?)';
            $like = '%' . $word . '%';
            array_push($params, $like, $like, $like);
        }
        $stmt = $pdo->prepare("SELECT m.id, m.nom, m.fabricant, m.forme, m.dosage, m.prix_vente,
                                      m.quantite_minimale, m.necessite_ordonnance,
                                      COALESCE(v.quantite_totale, 0) AS stock,
                                      c.nom AS categorie
                               FROM medicaments m
                               LEFT JOIN v_stock_medicaments v ON v.medicament_id = m.id
                               LEFT JOIN categories c ON c.id = m.categorie_id
                               WHERE m.actif = 1 AND (" . implode(' OR ', $conditions) . ")
                               ORDER BY m.nom LIMIT 8");
        $stmt->execute($params);
        $context['Médicaments correspondant à la recherche'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (preg_match('/alerte|stock faible|expiration|rupture|expire/i', $question)) {
        $context['Alertes actives'] = $pdo->query("SELECT a.type_alerte, a.message, a.date_creation, m.nom AS medicament, l.numero_lot
            FROM alertes a
            LEFT JOIN medicaments m ON m.id = a.medicament_id
            LEFT JOIN lots l ON l.id = a.lot_id
            WHERE a.statut = 'active' ORDER BY a.date_creation DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
    }

    if (preg_match('/commande|réception|reception|fournisseur/i', $question)) {
        $context['Commandes récentes'] = $pdo->query("SELECT c.id, c.statut, c.date_commande, c.date_livraison_prevue,
            c.montant_total, f.nom AS fournisseur
            FROM commandes c JOIN fournisseurs f ON f.id = c.fournisseur_id
            ORDER BY c.date_commande DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    }

    if (preg_match('/vente|chiffre|paiement|ticket/i', $question)) {
        $context['Ventes récentes'] = $pdo->query("SELECT id, date_vente, montant_total, mode_paiement, type_document
            FROM ventes ORDER BY date_vente DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    }

    return json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function chatbot_csrf_token(): string
{
    if (empty($_SESSION['chatbot_csrf'])) {
        $_SESSION['chatbot_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['chatbot_csrf'];
}

function chatbot_csrf_valid(string $token): bool
{
    return hash_equals($_SESSION['chatbot_csrf'] ?? '', $token);
}

function chatbot_page_label(string $pageTitle): string
{
    return trim(strip_tags($pageTitle));
}

function chatbot_build_messages(PDO $pdo, string $question, array $history, string $page): array
{
    $messages = [['role' => 'system', 'content' => chatbot_system_prompt()]];
    foreach (array_slice($history, -6) as $item) {
        if (!is_array($item) || !in_array($item['role'] ?? '', ['user', 'assistant'], true)) {
            continue;
        }
        $content = trim((string) ($item['content'] ?? ''));
        if ($content !== '' && mb_strlen($content) <= 1500) {
            $messages[] = ['role' => $item['role'], 'content' => $content];
        }
    }
    $messages[] = ['role' => 'user', 'content' => "Contexte de session MediStock (données en lecture seule) :\n" . chatbot_context($pdo, $question, chatbot_page_label($page)) . "\n\nQuestion de l’utilisateur :\n" . $question];
    return $messages;
}

function chatbot_markdown_to_safe_html(string $text): string
{
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return nl2br($safe, false);
}

function chatbot_scope_refusal(): string
{
    return 'Je suis dédié à MediStock et je peux uniquement aider concernant la gestion de la pharmacie, du stock, des commandes, des ventes, des alertes et des prévisions.';
}

function chatbot_answer(PDO $pdo, string $question, array $history, string $page): string
{
    if (preg_match('/diagnostic|prescription|posologie|dose|dosage pour|interaction|sympt[oô]me|traitement pour un patient/i', $question)) {
        return 'Je suis un assistant de gestion MediStock. Je ne peux pas fournir de diagnostic, de prescription, de posologie ou de conseil médical à un patient.';
    }

    $isInScope = preg_match('/medistock|m[eé]dicament|pharmacie|stock|lot|commande|r[eé]ception|fournisseur|vente|ticket|alerte|expiration|rupture|pr[eé]vision|fefo|cat[eé]gorie/i', $question);
    $isGreeting = preg_match('/^(bonjour|salut|hello|bonsoir|aide|help|merci)\b/i', trim($question));
    if (!$isInScope && !$isGreeting) {
        return chatbot_scope_refusal();
    }

    return groq_call(chatbot_build_messages($pdo, $question, $history, $page));
}

function chatbot_check_question(string $question): string
{
    $question = trim($question);
    if ($question === '') {
        throw new InvalidArgumentException('Écrivez une question.');
    }
    if (mb_strlen($question) > 1000) {
        throw new InvalidArgumentException('La question est trop longue (maximum 1000 caractères).');
    }
    return $question;
}

function chatbot_json_error(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function chatbot_json_success(string $answer): never
{
    echo json_encode(['ok' => true, 'answer' => chatbot_markdown_to_safe_html($answer)], JSON_UNESCAPED_UNICODE);
    exit;
}

function chatbot_handle_request(PDO $pdo): never
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chatbot_json_error('Méthode non autorisée.', 405);
    }
    if (!chatbot_csrf_valid((string) ($_POST['csrf'] ?? ''))) {
        chatbot_json_error('Session expirée. Rechargez la page.', 419);
    }
    try {
        $question = chatbot_check_question((string) ($_POST['message'] ?? ''));
        $history = json_decode((string) ($_POST['history'] ?? '[]'), true);
        $history = is_array($history) ? $history : [];
        $answer = chatbot_answer($pdo, $question, $history, (string) ($_POST['page'] ?? ''));
        chatbot_json_success($answer);
    } catch (InvalidArgumentException $e) {
        chatbot_json_error($e->getMessage());
    } catch (Throwable $e) {
        error_log('MediStock chatbot: ' . $e->getMessage());
        $message = $e->getMessage();
        if ($e instanceof InvalidArgumentException) {
            chatbot_json_error($message);
        }
        // Retourner une indication exploitable sans jamais révéler la clé API.
        $safeMessage = str_contains($message, 'GROQ_API_KEY')
            ? 'GROQ_API_KEY est absente. Vérifiez le fichier .env à la racine du projet.'
            : (str_contains($message, 'service Groq') || str_contains($message, 'joindre')
                ? $message
                : 'Le chatbot est temporairement indisponible. Vérifiez la configuration Groq du serveur.');
        chatbot_json_error($safeMessage, 503);
    }
}

function chatbot_render_widget(string $pageTitle): void
{
    $csrf = htmlspecialchars(chatbot_csrf_token(), ENT_QUOTES, 'UTF-8');
    $page = htmlspecialchars(chatbot_page_label($pageTitle), ENT_QUOTES, 'UTF-8');
    echo <<<HTML
    <button type="button" class="chatbot-launcher" id="chatbotLauncher" aria-label="Ouvrir MediStock Assistant"><i class="bi bi-robot"></i></button>
    <section class="chatbot-panel" id="chatbotPanel" aria-label="MediStock Assistant" data-csrf="{$csrf}" data-page="{$page}">
        <div class="chatbot-header"><strong><i class="bi bi-robot"></i> MediStock Assistant</strong><button type="button" class="btn-close btn-close-white" id="chatbotClose" aria-label="Fermer"></button></div>
        <div class="chatbot-messages" id="chatbotMessages"><div class="chatbot-message chatbot-assistant">Bonjour. Je peux vous aider avec les médicaments, le stock, les lots, les commandes, les ventes, les alertes et les prévisions MediStock.</div></div>
        <form class="chatbot-form" id="chatbotForm"><textarea id="chatbotInput" rows="2" maxlength="1000" placeholder="Posez une question sur MediStock..." required></textarea><button class="btn btn-primary" type="submit" id="chatbotSend"><i class="bi bi-send"></i></button></form>
        <div class="chatbot-disclaimer">Assistant de gestion interne. Aucun diagnostic ni conseil médical.</div>
    </section>
HTML;
}

function chatbot_widget_script(): string
{
    return <<<'JS'
<script>
(() => {
    const panel = document.getElementById('chatbotPanel');
    if (!panel) return;
    const launcher = document.getElementById('chatbotLauncher');
    const close = document.getElementById('chatbotClose');
    const form = document.getElementById('chatbotForm');
    const input = document.getElementById('chatbotInput');
    const messages = document.getElementById('chatbotMessages');
    const send = document.getElementById('chatbotSend');
    const history = [];
    const addMessage = (html, role) => {
        const node = document.createElement('div');
        node.className = 'chatbot-message chatbot-' + role;
        node.innerHTML = html;
        messages.appendChild(node);
        messages.scrollTop = messages.scrollHeight;
    };
    launcher.addEventListener('click', () => { panel.classList.add('open'); input.focus(); });
    close.addEventListener('click', () => panel.classList.remove('open'));
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = input.value.trim();
        if (!question || send.disabled) return;
        addMessage(question.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])), 'user');
        history.push({role: 'user', content: question});
        input.value = '';
        send.disabled = true;
        addMessage('Je vérifie les informations MediStock...', 'assistant chatbot-loading');
        try {
            const body = new URLSearchParams({message: question, history: JSON.stringify(history.slice(-6)), page: panel.dataset.page, csrf: panel.dataset.csrf});
            const response = await fetch('chatbot_api.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'}, body});
            const data = await response.json();
            messages.querySelector('.chatbot-loading')?.remove();
            if (!data.ok) throw new Error(data.error || 'Erreur du chatbot');
            addMessage(data.answer, 'assistant');
            history.push({role: 'assistant', content: data.answer.replace(/<[^>]*>/g, ' ')});
        } catch (error) {
            messages.querySelector('.chatbot-loading')?.remove();
            addMessage(error.message || 'Le chatbot est temporairement indisponible.', 'assistant chatbot-error');
        } finally { send.disabled = false; input.focus(); }
    });
})();
</script>
JS;
}
?>
