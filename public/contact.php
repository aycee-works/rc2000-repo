<?php
require_once __DIR__ . '/../includes/functions.php';

$errors = [];
$old = [
    'sender_name' => '',
    'sender_email' => trim((string) ($_GET['email'] ?? '')),
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    $old['sender_name'] = post_string('sender_name');
    $old['sender_email'] = post_string('sender_email');
    $old['subject'] = post_string('subject');
    $old['message'] = post_string('message');

    if (!required_text($old['sender_name'], 150)) {
        $errors[] = 'Please enter your name.';
    }
    if (!valid_email($old['sender_email'])) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!optional_text($old['subject'], 200)) {
        $errors[] = 'Subject is too long.';
    }
    if (!required_text($old['message'], 8000)) {
        $errors[] = 'Please write a message.';
    }

    if (!$errors) {
        try {
            $stmt = db()->prepare(
                'INSERT INTO inquiries (sender_name, sender_email, subject, message)
                 VALUES (:sender_name, :sender_email, :subject, :message)
                 RETURNING inquiry_id, created_at'
            );
            $stmt->execute([
                'sender_name' => $old['sender_name'],
                'sender_email' => $old['sender_email'],
                'subject' => $old['subject'] !== '' ? $old['subject'] : 'Contact message',
                'message' => $old['message'],
            ]);
            $row = $stmt->fetch();
            $ticket = ticket_number((int) $row['inquiry_id'], (string) $row['created_at']);
            flash_set('success', 'Message sent. If you need to follow up, your ticket number is ' . $ticket . '.');
            redirect('/public/contact.php');
        } catch (Throwable $error) {
            $errors[] = 'We could not send your message yet. Please try again when the database is available.';
        }
    }
}

page_start('Contact Us');
?>
<main class="section contact-page">
  <div class="section-heading">
    <div>
      <h1>Send us a message</h1>
      <p>Ask a question, share feedback, or tell us you’re stopping by.</p>
    </div>
  </div>
  <?php render_alerts($errors); ?>
  <div class="contact-grid">
    <section class="form-card" data-reveal>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
          <label>Name
            <input name="sender_name" required maxlength="150" value="<?= e($old['sender_name']) ?>">
          </label>
          <label>Email
            <input type="email" name="sender_email" required maxlength="255" value="<?= e($old['sender_email']) ?>">
          </label>
        </div>
        <label>Subject
          <input name="subject" maxlength="200" placeholder="Inquiry / Request" value="<?= e($old['subject']) ?>">
        </label>
        <label>Message
          <textarea name="message" rows="6" required><?= e($old['message']) ?></textarea>
        </label>
        <button class="button wide" type="submit">Send message</button>
      </form>
    </section>
    <aside class="visit-card" data-reveal>
      <p class="contact-detail"><img src="<?= e(app_url('public/assets/images/icons/contactpin_icon.png')) ?>" alt="" width="24" height="24"><span>1454 Balagtas St., Barangay La Paz<br>1204 Makati</span></p>
      <p class="contact-detail"><img src="<?= e(app_url('public/assets/images/icons/contactcall_icon.png')) ?>" alt="" width="24" height="24"><a href="tel:+639176176486">0917 617 6486</a></p>
      <p class="contact-detail"><img src="<?= e(app_url('public/assets/images/icons/contactfb_icon.png')) ?>" alt="" width="24" height="24"><span>Follow us</span></p>
      <div class="map-embed">
        <iframe
          title="Reading Club 2000 map"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          src="https://maps.google.com/maps?q=1454%20Balagtas%20St%20La%20Paz%20Makati&t=&z=16&ie=UTF8&iwloc=&output=embed">
        </iframe>
      </div>
      <a class="button button-light" href="https://maps.google.com/?q=1454+Balagtas+St+La+Paz+Makati" target="_blank" rel="noopener">Open in Maps</a>
    </aside>
  </div>
</main>
<?php page_end(); ?>
