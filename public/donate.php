<?php
require_once __DIR__ . '/../includes/functions.php';

$tab = $_GET['tab'] ?? 'book';
if (!in_array($tab, ['book', 'money'], true)) {
    $tab = 'book';
}

$methods = active_donation_methods();
$errors = [];
$old = [
    'donor_name' => '',
    'donor_email' => '',
    'contact_info' => '',
    'quantity' => '1',
    'donation_description' => '',
    'title' => '',
    'author' => '',
    'genre' => '',
    'condition' => '',
    'amount_reported' => '',
    'donation_method_id' => '',
    'transaction_reference' => '',
    'payment_date' => date('Y-m-d\TH:i'),
    'anonymous' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $action = (string) ($_POST['action'] ?? '');
    foreach ($old as $key => $_) {
        if ($key !== 'anonymous') {
            $old[$key] = post_string($key);
        }
    }
    $old['anonymous'] = isset($_POST['anonymous']) ? '1' : '';
    $tab = $action === 'monetary' ? 'money' : 'book';

    if (!$errors && $action === 'book') {
        if (!required_text($old['donor_name'], 150)) {
            $errors[] = 'Please enter your name.';
        }
        if (!optional_text($old['contact_info'], 255)) {
            $errors[] = 'Contact information is too long.';
        }
        if (!valid_email($old['donor_email']) || mb_strlen($old['donor_email']) > 150) {
            $errors[] = 'Please enter a valid email address (up to 150 characters).';
        }
        if (!valid_quantity($old['quantity'])) {
            $errors[] = 'Quantity must be at least 1.';
        }

        $parts = [];
        if ($old['title'] !== '') {
            $parts[] = 'Title: ' . $old['title'];
        }
        if ($old['author'] !== '') {
            $parts[] = 'Author: ' . $old['author'];
        }
        if ($old['genre'] !== '') {
            $parts[] = 'Genre: ' . $old['genre'];
        }
        if ($old['condition'] !== '') {
            $parts[] = 'Condition: ' . $old['condition'];
        }
        if ($old['donation_description'] !== '') {
            $parts[] = $old['donation_description'];
        }
        $description = implode("\n", $parts);
        if (!optional_text($description, 8000)) {
            $errors[] = 'The book details are too long.';
        }

        $photo = store_upload('donation_photo', 'donations', $errors, false);

        if (!$errors) {
            try {
                $pdo = db();
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO public.donations (donor_name, contact_info, quantity, donation_description, donation_photo_path)
                     VALUES (:donor_name, :contact_info, :quantity, :donation_description, :donation_photo_path)'
                );
                $stmt->execute([
                    'donor_name' => $old['donor_name'],
                    'contact_info' => $old['contact_info'] !== '' ? $old['contact_info'] : null,
                    'quantity' => (int) $old['quantity'],
                    'donation_description' => $description !== '' ? $description : null,
                    'donation_photo_path' => $photo,
                ]);
                $portal = $pdo->prepare(
                    "INSERT INTO rc2000_admin.book_donations
                        (reference_no, donor_name, donor_email, donor_contact, book_title, author, genre, book_condition, qty_offered, notes)
                     VALUES
                        ('BD-' || lpad(nextval('rc2000_admin.book_donation_seq')::text, 4, '0'), :donor_name, :donor_email, :donor_contact, :book_title, :author, :genre, :book_condition, :qty_offered, :notes)"
                );
                $contact = trim($old['contact_info']);
                $portal->execute([
                    'donor_name' => $old['donor_name'],
                    'donor_email' => strtolower(trim($old['donor_email'])),
                    'donor_contact' => preg_match('/^[0-9+() \\-]{7,30}$/', $contact) ? $contact : null,
                    'book_title' => trim($old['title']) !== '' ? $old['title'] : 'Book donation',
                    'author' => trim($old['author']) !== '' ? mb_substr($old['author'], 0, 150) : null,
                    'genre' => trim($old['genre']) !== '' ? mb_substr($old['genre'], 0, 60) : null,
                    'book_condition' => ['Like new' => 'Excellent', 'Mixed lot' => 'Good'][$old['condition']] ?? ($old['condition'] ?: 'Good'),
                    'qty_offered' => (int) $old['quantity'],
                    'notes' => $description !== '' ? $description : null,
                ]);
                $pdo->commit();
                flash_set('success', 'Thank you. Your book donation was recorded and is pending review.');
                redirect('/public/donate.php?tab=book');
            } catch (Throwable $error) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Book donation save failed: ' . $error->getMessage());
                $errors[] = 'We could not save your donation yet. Please try again when the database is available.';
            }
        }
    }

    if (!$errors && $action === 'monetary') {
        $donorName = $old['anonymous'] === '1' ? 'Anonymous' : $old['donor_name'];
        if ($old['anonymous'] !== '1' && !required_text($donorName, 150)) {
            $errors[] = 'Please enter your name, or donate anonymously.';
        }
        if (!optional_text($old['contact_info'], 255)) {
            $errors[] = 'Contact information is too long.';
        }
        if (!valid_email($old['donor_email']) || mb_strlen($old['donor_email']) > 150) {
            $errors[] = 'Please enter a valid email address (up to 150 characters).';
        }
        if (!valid_amount($old['amount_reported'])) {
            $errors[] = 'Please enter a valid amount.';
        }
        if (!required_text($old['transaction_reference'], 150)) {
            $errors[] = 'Please enter a transaction reference or check number.';
        }
        if (mb_strlen($old['transaction_reference']) > 100) {
            $errors[] = 'Transaction reference must be 100 characters or fewer.';
        }
        if (!valid_date_time($old['payment_date'])) {
            $errors[] = 'Please enter the payment date.';
        }
        $methodId = (int) $old['donation_method_id'];
        $validIds = array_map(static fn ($row) => (int) $row['donation_method_id'], $methods);
        if (!in_array($methodId, $validIds, true)) {
            $errors[] = 'Please choose a donation method.';
        }

        $proof = store_upload('proof_of_payment', 'proofs', $errors, false);

        if (!$errors) {
            try {
                $pdo = db();
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO public.monetary_donations
                        (donor_name, contact_info, donation_method_id, amount_reported, transaction_reference, payment_date, proof_of_payment_path)
                     VALUES
                        (:donor_name, :contact_info, :donation_method_id, :amount_reported, :transaction_reference, :payment_date, :proof_of_payment_path)'
                );
                $stmt->execute([
                    'donor_name' => $donorName,
                    'contact_info' => $old['contact_info'] !== '' ? $old['contact_info'] : null,
                    'donation_method_id' => $methodId,
                    'amount_reported' => number_format((float) $old['amount_reported'], 2, '.', ''),
                    'transaction_reference' => $old['transaction_reference'],
                    'payment_date' => date('c', strtotime($old['payment_date'])),
                    'proof_of_payment_path' => $proof,
                ]);
                $method = null;
                foreach ($methods as $availableMethod) {
                    if ((int) $availableMethod['donation_method_id'] === $methodId) {
                        $method = trim($availableMethod['method_name'] . ' · ' . $availableMethod['payment_provider']);
                        break;
                    }
                }
                $portal = $pdo->prepare(
                    "INSERT INTO rc2000_admin.monetary_donations
                        (reference_no, donor_name, donor_email, donor_contact, reported_amount, payment_method, transaction_ref, proof_path)
                     VALUES
                        ('MD-' || lpad(nextval('rc2000_admin.monetary_donation_seq')::text, 4, '0'), :donor_name, :donor_email, :donor_contact, :reported_amount, :payment_method, :transaction_ref, :proof_path)"
                );
                $contact = trim($old['contact_info']);
                $portal->execute([
                    'donor_name' => $donorName,
                    'donor_email' => strtolower(trim($old['donor_email'])),
                    'donor_contact' => preg_match('/^[0-9+() \\-]{7,30}$/', $contact) ? $contact : null,
                    'reported_amount' => number_format((float) $old['amount_reported'], 2, '.', ''),
                    'payment_method' => mb_substr((string) $method, 0, 40),
                    'transaction_ref' => $old['transaction_reference'],
                    'proof_path' => $proof,
                ]);
                $pdo->commit();
                flash_set('success', 'Thank you. Your monetary donation report was saved.');
                redirect('/public/donate.php?tab=money');
            } catch (PDOException $error) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Monetary donation save failed: ' . $error->getMessage());
                if (str_contains($error->getMessage(), 'uq_monetary_method_reference')) {
                    $errors[] = 'That transaction reference was already recorded for this method.';
                } else {
                    $errors[] = 'We could not save your report yet. Please try again when the database is available.';
                }
            } catch (Throwable $error) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Monetary donation save failed: ' . $error->getMessage());
                $errors[] = 'We could not save your report yet. Please try again when the database is available.';
            }
        }
    }
}

page_start('Donate');
?>
<main class="donate-layout">
  <aside class="donate-side">
    <p class="eyebrow">Donate by</p>
    <a class="<?= $tab === 'book' ? 'is-active' : '' ?>" href="/rc2000/public/donate.php?tab=book">Book Donation</a>
    <a class="<?= $tab === 'money' ? 'is-active' : '' ?>" href="/rc2000/public/donate.php?tab=money">Monetary Donation</a>
  </aside>

  <div class="donate-main">
    <?php render_alerts($errors); ?>

    <section class="howto">
      <h3>How to donate</h3>
      <ol>
        <li>Books: set a drop-off time, or we’ll contact you about pick-up.</li>
        <li>Money: use QR, bank transfer, or check, then fill out the donation form so we can thank you.</li>
        <li>You’ll get a confirmation after you submit.</li>
      </ol>
    </section>

    <?php if ($tab === 'book'): ?>
      <section class="form-card">
        <h1>Book donation</h1>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="book">
          <div class="form-grid">
            <label>Name (Donor or Organization)
              <input name="donor_name" required maxlength="150" value="<?= e($old['donor_name']) ?>">
            </label>
            <label>Email
              <input type="email" name="donor_email" required maxlength="150" value="<?= e($old['donor_email']) ?>">
            </label>
            <label>Contact info
              <input name="contact_info" maxlength="255" placeholder="Phone (optional)" value="<?= e($old['contact_info']) ?>">
            </label>
            <label>Title <span class="field-note"></span>
              <input name="title" maxlength="200" placeholder=" Description only if bulk donation" value="<?= e($old['title']) ?>">
            </label>
            <label>Author 
              <input name="author" maxlength="200" placeholder="Leave blank if donating multiple books" value="<?= e($old['author']) ?>">
            </label>
            <label>Genre
              <input name="genre" maxlength="120" value="<?= e($old['genre']) ?>">
            </label>
            <label>Estimated quantity <span class="field-note">An approximate count is fine. If you only know a range, enter your best estimate and note the range below. We use this for transparency and don’t track each donated book individually.</span>
              <input type="number" name="quantity" min="1" value="<?= e($old['quantity']) ?>" required>
            </label>
            <label>Condition
              <select name="condition">
                <option value="">Select</option>
                <?php foreach (['Like new', 'Good', 'Fair', 'Mixed lot'] as $condition): ?>
                  <option value="<?= e($condition) ?>" <?= $old['condition'] === $condition ? 'selected' : '' ?>><?= e($condition) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <label>Notes
            <textarea name="donation_description" rows="3" placeholder="Optional: estimated quantity range, editions, or pickup notes"><?= e($old['donation_description']) ?></textarea>
          </label>
          <label class="upload-box">
            <span>Upload book photo (optional)</span>
            <input type="file" name="donation_photo" accept="image/jpeg,image/png,image/webp,image/gif">
          </label>
          <button class="button wide" type="submit">Submit book donation</button>
        </form>
      </section>
    <?php else: ?>
      <section class="form-card">
        <h1>Monetary donation</h1>
        <p class="lede">After donating, please fill out this form so we can thank you. The amount is self-reported and recorded for transparency; it is not independently tracked through this form.</p>

        <div class="method-tabs" data-tabs>
            <button type="button" class="is-active" data-tab-target="qr">QR Codes</button>
            <button type="button" data-tab-target="bank">Bank Transfer</button>
            <button type="button" data-tab-target="check">Check</button>
          </div>

        <div class="method-panels">
          <div class="is-active" data-tab-panel="qr">
            <?php $qrMethods = array_values(array_filter($methods, static fn ($m) => stripos($m['payment_provider'] . $m['method_name'], 'qr') !== false || stripos($m['payment_provider'], 'gcash') !== false || stripos($m['payment_provider'], 'maya') !== false)); ?>
            <?php if ($qrMethods): foreach ($qrMethods as $method): ?>
              <figure class="qr-card">
                <?php if (media_file_exists($method['qr_image_path'])): ?>
                  <img src="<?= e(media_url($method['qr_image_path'])) ?>" alt="<?= e($method['method_name']) ?>">
                <?php else: ?>
                  <div class="qr-placeholder">Add the <?= e($method['method_name']) ?> image in the donation method settings.</div>
                <?php endif; ?>
                <figcaption>
                  <strong><?= e($method['method_name']) ?></strong>
                  <p><?= e($method['instructions'] ?: $method['payment_provider']) ?></p>
                </figcaption>
              </figure>
            <?php endforeach; else: ?>
              <div class="qr-placeholder">QR images appear here once donation methods are added in the database.</div>
              <p>Scan with GCash, Maya, or any QR PH app.</p>
            <?php endif; ?>
          </div>
          <div hidden data-tab-panel="bank">
            <?php $bankMethods = array_values(array_filter($methods, static fn ($m) => stripos($m['payment_provider'] . $m['method_name'], 'bank') !== false)); ?>
            <?php if ($bankMethods): foreach ($bankMethods as $method): ?>
              <p><strong><?= e($method['method_name']) ?></strong><br><?= nl2br(e($method['instructions'] ?: $method['payment_provider'])) ?></p>
            <?php endforeach; else: ?>
              <p><strong>Bank / Account name</strong><br>BDO · Reading Club 2000</p>
              <p><strong>Account number</strong><br>Add the live account in donation_methods.</p>
            <?php endif; ?>
          </div>
          <div hidden data-tab-panel="check">
            <p>Make checks payable to <strong>Reading Club 2000</strong>.</p>
            <p>Mail or drop off at 1454 Balagtas St., Barangay La Paz, 1204 Makati.</p>
          </div>
        </div>

        <?php if (!$methods): ?>
          <p class="alert alert-error">Monetary reports need at least one active row in <code>donation_methods</code>.</p>
        <?php else: ?>
          <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="monetary">
            <label class="check-line">
              <input type="checkbox" name="anonymous" value="1" <?= $old['anonymous'] ? 'checked' : '' ?>>
              Donate anonymously
            </label>
            <div class="form-grid">
              <label>Name 
                <input name="donor_name" maxlength="150" value="<?= e($old['donor_name']) ?>">
              </label>
              <label>Email
                <input type="email" name="donor_email" required maxlength="150" value="<?= e($old['donor_email']) ?>">
              </label>
              <label>Contact info
                <input name="contact_info" maxlength="255" value="<?= e($old['contact_info']) ?>">
              </label>
              <label>Amount
                <input type="number" name="amount_reported" min="1" step="0.01" required value="<?= e($old['amount_reported']) ?>">
              </label>
              <label class="payment-date-label"><span><img src="<?= e(app_url('public/assets/images/icons/donationdate_icon.png')) ?>" alt="" width="18" height="18">Payment date</span>
                <input type="datetime-local" name="payment_date" required value="<?= e($old['payment_date']) ?>">
              </label>
              <label>Donation method
                <select name="donation_method_id" required>
                  <option value="">Select a method</option>
                  <?php foreach ($methods as $method): ?>
                    <option value="<?= (int) $method['donation_method_id'] ?>" <?= (string) $method['donation_method_id'] === $old['donation_method_id'] ? 'selected' : '' ?>>
                      <?= e($method['method_name'] . ' · ' . $method['payment_provider']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Transaction / check number
                <input name="transaction_reference" required maxlength="150" value="<?= e($old['transaction_reference']) ?>">
              </label>
            </div>
            <label class="upload-box">
              <span>Proof of payment (optional)</span>
              <input type="file" name="proof_of_payment" accept="image/jpeg,image/png,image/webp,image/gif">
            </label>
            <label class="check-line">
              <input type="checkbox" name="sent_confirm" value="1" required>
              I’ve sent my donation
            </label>
            <button class="button wide" type="submit">Submit donation</button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</main>
<?php page_end(); ?>
