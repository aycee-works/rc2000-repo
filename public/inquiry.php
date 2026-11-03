<?php
require_once __DIR__ . '/../includes/functions.php';

$view = $_GET['view'] ?? 'submit';
if (!in_array($view, ['submit', 'status'], true)) {
    $view = 'submit';
}

$errors = [];
$statusResult = null;
$old = [
    'category' => '',
    'sender_name' => '',
    'sender_email' => '',
    'phone' => '',
    'subject' => '',
    'message' => '',
    'ticket_number' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    $action = (string) ($_POST['action'] ?? '');
    foreach ($old as $key => $_) {
        $old[$key] = post_string($key);
    }

    if ($action === 'submit') {
        $view = 'submit';
        $categories = inquiry_categories();
        if (!isset($categories[$old['category']])) {
            $errors[] = 'Please select a category.';
        }
        if (!required_text($old['sender_name'], 150)) {
            $errors[] = 'Please enter your name.';
        }
        if (!valid_email($old['sender_email'])) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!valid_phone($old['phone'])) {
            $errors[] = 'Please enter a valid phone number, or leave it blank.';
        }
        if (!required_text($old['subject'], 200)) {
            $errors[] = 'Please enter a subject.';
        }
        if (!required_text($old['message'], 8000)) {
            $errors[] = 'Please describe your issue.';
        }

        if (!$errors) {
            $label = $categories[$old['category']];
            $message = $old['message'];
            if ($old['phone'] !== '') {
                $message .= "\n\nPhone: " . $old['phone'];
            }
            
              try {
                  $pdo = db();
                  $pdo->beginTransaction();

                  $stmt = $pdo->prepare(
                      'INSERT INTO rc2000_admin.inquiries
                          (name, email, category, subject, message, status)
                      VALUES
                          (:name, :email, :category, :subject, :message, :status)
                      RETURNING id, created_at'
                  );

                  $stmt->execute([
                      'name'     => $old['sender_name'],
                      'email'    => $old['sender_email'],
                      'category' => $label,
                      'subject'  => $old['subject'],
                      'message'  => $message,
                      'status'   => 'open',
                  ]);

                  $row = $stmt->fetch();
                  $inquiryId = (int) $row['id'];

                  $referenceNo = 'INQ-' . str_pad(
                      (string) $inquiryId,
                      4,
                      '0',
                      STR_PAD_LEFT
                  );

                  $update = $pdo->prepare(
                      'UPDATE rc2000_admin.inquiries
                      SET reference_no = :reference_no
                      WHERE id = :id'
                  );

                  $update->execute([
                      'reference_no' => $referenceNo,
                      'id' => $inquiryId,
                  ]);

                  $pdo->commit();

                  flash_set(
                      'success',
                      'Inquiry submitted. Save this reference number to check status: '
                      . $referenceNo
                  );

                  redirect('/public/inquiry.php?view=status');

              } catch (Throwable $error) {
                  if (isset($pdo) && $pdo instanceof PDO
                      && $pdo->inTransaction()) {
                      $pdo->rollBack();
                  }

                  error_log(
                      'Public inquiry submission failed: '
                      . $error->getMessage()
                  );

                  $errors[] = 'We could not save your inquiry. Please check the database connection and try again.';
              }

        }
    }

    if ($action === 'status') {
        $view = 'status';
        $referenceNo = strtoupper(trim($old['ticket_number']));
        if (!preg_match('/^INQ-\d{4,}$/', $referenceNo)) {
            $errors[] = 'Enter a reference number such as INQ-0001.';
        }
        if (!required_text($old['sender_name'], 150)) {
            $errors[] = 'Please enter the name used when you submitted.';
        }
        if (!valid_email($old['sender_email'])) {
            $errors[] = 'Please enter the email used when you submitted.';
        }
        if (!$errors) {
            $stmt = db()->prepare(
                'SELECT id, reference_no, name, email, subject, status, created_at, updated_at
                 FROM rc2000_admin.inquiries
                 WHERE LOWER(reference_no) = LOWER(:reference_no)
                   AND LOWER(email) = LOWER(:email)
                   AND LOWER(name) = LOWER(:name)
                 LIMIT 1'
            );
            $stmt->execute([
                'reference_no' => $referenceNo,
                'email' => $old['sender_email'],
                'name' => $old['sender_name'],
            ]);
            $row = $stmt->fetch() ?: null;
            if (!$row) {
                $errors[] = 'No ticket matched that number, name, and email.';
            } else {
                $statusResult = $row;
            }
        }
    }
}

page_start('Inquiry');
?>
<main class="section inquiry-page">
  <div class="section-heading">
    <div>
      <h1>Send us an inquiry!</h1>
      <p>Drop us a message and we’ll reply faster than you can finish a chapter. You’ll get a reference number so you can check your message status.</p>
    </div>
  </div>

  <div class="subnav">
    <a href="/rc2000/public/inquiry.php?view=submit" class="<?= $view === 'submit' ? 'is-active' : '' ?>">Submit an inquiry</a>
    <a href="/rc2000/public/inquiry.php?view=status" class="<?= $view === 'status' ? 'is-active' : '' ?>">Check inquiry status</a>
  </div>

  <?php render_alerts($errors); ?>

  <?php if ($view === 'submit'): ?>
    <section class="form-card framed" data-reveal>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit">
        <label>Category
          <select name="category" required>
            <option value="">Select a category</option>
            <?php foreach (inquiry_categories() as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $old['category'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="form-grid">
          <label>Name
            <input name="sender_name" required maxlength="150" value="<?= e($old['sender_name']) ?>">
          </label>
          <label>Email
            <input type="email" name="sender_email" required maxlength="255" value="<?= e($old['sender_email']) ?>">
          </label>
        </div>
        <label>Phone (optional)
          <input name="phone" maxlength="30" value="<?= e($old['phone']) ?>">
        </label>
        <label>Subject
          <input name="subject" required maxlength="200" value="<?= e($old['subject']) ?>">
        </label>
        <label>Describe your issue
          <textarea name="message" rows="7" required><?= e($old['message']) ?></textarea>
        </label>
        <button class="button wide" type="submit">Send Message</button>
      </form>
    </section>
  <?php else: ?>
    <section class="form-card framed" data-reveal>
      <?php if ($statusResult): ?>
        <div class="status-result">
          <p class="eyebrow">Inquiry reference</p>
          <h2><?= e((string) $statusResult['reference_no']) ?></h2>
          <p><strong>Status:</strong> <?= e(['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'][(string) $statusResult['status']] ?? (string) $statusResult['status']) ?></p>
          <p><strong>Subject:</strong> <?= e((string) $statusResult['subject']) ?></p>
          <p><strong>Submitted:</strong> <?= e(date('M j, Y g:i A', strtotime((string) $statusResult['created_at']))) ?></p>
        </div>
      <?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <label>Reference number
          <input name="ticket_number" required placeholder="INQ-0001" value="<?= e($old['ticket_number']) ?>">
        </label>
        <div class="form-grid">
          <label>Name
            <input name="sender_name" required maxlength="150" value="<?= e($old['sender_name']) ?>">
          </label>
          <label>Email used when you submitted
            <input type="email" name="sender_email" required maxlength="255" value="<?= e($old['sender_email']) ?>">
          </label>
        </div>
        <button class="button wide" type="submit">Check Status</button>
      </form>
    </section>
  <?php endif; ?>
</main>
<?php page_end(); ?>
