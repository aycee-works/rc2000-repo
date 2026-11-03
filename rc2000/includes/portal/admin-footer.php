<?php
/**
 * includes/admin-footer.php
 * Closes the page opened by admin-header.php.
 * Optional: $extraJs (array of file names in assets/js/).
 */
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
