<?php
$admin = current_admin();
$adminName = $admin['full_name'] ?? 'Admin';
?>
<aside class="admin-sidebar">
  <p class="eyebrow">Staff</p>
  <strong><?= e($adminName) ?></strong>
  <nav>
    <a href="/admin/index.php">Dashboard</a>
    <a href="/admin/content.php">Content</a>
    <a href="/admin/donations.php">Book donations</a>
    <a href="/admin/monetary.php">Monetary donations</a>
    <a href="/admin/inquiries.php">Inquiries</a>
    <a href="/admin/gallery.php">Gallery</a>
  </nav>
</aside>
