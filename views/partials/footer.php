<?php
$pageScript = $pageScript ?? null;
$pageScriptIsModule = (bool)($pageScriptIsModule ?? false);
$logoutScriptPath = __DIR__ . '/../../assets/js/logout.js';
?>
<script src="<?= htmlspecialchars(BASE_URL . '/assets/js/vendor/sweetalert2.all.min.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL . '/assets/js/sweetalert-notifications.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL . '/assets/js/logout.js?v=' . (file_exists($logoutScriptPath) ? filemtime($logoutScriptPath) : time())) ?>"></script>
<?php if (!empty($pageScript)): ?>
    <?php if ($pageScriptIsModule): ?>
        <script type="module" src="<?= htmlspecialchars($pageScript) ?>"></script>
    <?php else: ?>
        <script src="<?= htmlspecialchars($pageScript) ?>"></script>
    <?php endif; ?>
<?php endif; ?>
<script src="<?= htmlspecialchars(BASE_URL . '/assets/js/spa.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL . '/assets/js/mobile-layout.js?v=' . filemtime(__DIR__ . '/../../assets/js/mobile-layout.js')) ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL . '/assets/js/searchable-selects.js?v=' . filemtime(__DIR__ . '/../../assets/js/searchable-selects.js')) ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL . '/assets/js/date-entry.js?v=' . filemtime(__DIR__ . '/../../assets/js/date-entry.js')) ?>"></script>
<?php require_once __DIR__ . '/../../includes/websocket-config.php'; ?>
<script>window.HOSPITAL_WEBSOCKET_PORT = <?= WEBSOCKET_PORT ?>;</script>
<script src="<?= htmlspecialchars(BASE_URL . '/assets/js/websocket-client.js?v=' . filemtime(__DIR__ . '/../../assets/js/websocket-client.js')) ?>"></script>
</body>
</html>