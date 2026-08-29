<?php

if (!function_exists('medistock_ai_widget_html')) {
    function medistock_ai_widget_html(string $csrf, string $page): void
    {
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
}

if (!function_exists('medistock_ai_widget_script')) {
    function medistock_ai_widget_script(): string
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
    const append = (html, role) => {
        const node = document.createElement('div');
        node.className = 'chatbot-message chatbot-' + role;
        node.innerHTML = html;
        messages.appendChild(node);
        messages.scrollTop = messages.scrollHeight;
    };

    launcher.addEventListener('click', () => { panel.classList.add('open'); input.focus(); });
    close.addEventListener('click', () => panel.classList.remove('open'));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const question = input.value.trim();
        if (!question || send.disabled) return;

        append(question.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])), 'user');
        history.push({role: 'user', content: question});
        input.value = '';
        send.disabled = true;
        append('Je vérifie les informations MediStock...', 'assistant chatbot-loading');

        try {
            const body = new URLSearchParams({
                message: question,
                history: JSON.stringify(history.slice(-6)),
                page: panel.dataset.page,
                csrf: panel.dataset.csrf
            });

            const response = await fetch('assistant_ai/chat.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'}, body});
            const data = await response.json();
            messages.querySelector('.chatbot-loading')?.remove();
            if (!data.ok) throw new Error(data.error || 'Erreur du chatbot');
            append(data.answer, 'assistant');
            history.push({role: 'assistant', content: data.answer.replace(/<[^>]*>/g, ' ')});
        } catch (error) {
            messages.querySelector('.chatbot-loading')?.remove();
            append(error.message || 'Le chatbot est temporairement indisponible.', 'assistant chatbot-error');
        } finally {
            send.disabled = false;
            input.focus();
        }
    });
})();
</script>
JS;
    }
}

if (!function_exists('chatbot_render_widget')) {
    function chatbot_render_widget(string $pageTitle): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['chatbot_csrf'])) {
            $_SESSION['chatbot_csrf'] = bin2hex(random_bytes(32));
        }

        $csrf = htmlspecialchars($_SESSION['chatbot_csrf'], ENT_QUOTES, 'UTF-8');
        $page = htmlspecialchars(trim(strip_tags($pageTitle)), ENT_QUOTES, 'UTF-8');
        medistock_ai_widget_html($csrf, $page);
    }
}

if (!function_exists('chatbot_widget_script')) {
    function chatbot_widget_script(): string
    {
        return medistock_ai_widget_script();
    }
}
