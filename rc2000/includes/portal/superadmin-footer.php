<?php
/** includes/superadmin-footer.php - closes the page opened by superadmin-header.php. Optional $extraJs. */
$extraJs = $extraJs ?? [];
?>
    </main>
</div>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<?php foreach ($extraJs as $js): ?>
<script src="<?= e(asset('js/' . $js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
