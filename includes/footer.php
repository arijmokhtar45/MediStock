</main>
<?php
require_once __DIR__ . '/../assistant_ai/widget.php';
?>
<footer class="text-center text-muted small py-4">
    MediStock &copy; <?= date('Y') ?> — Projet de Fin d'Études
</footer>
<?php if (function_exists('chatbot_render_widget')): ?>
    <?php chatbot_render_widget($page_titre ?? 'MediStock'); ?>
    <?= chatbot_widget_script() ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
