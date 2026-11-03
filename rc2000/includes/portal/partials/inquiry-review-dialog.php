<?php
/**
 * includes/partials/inquiry-review-dialog.php
 * The inquiry review popup. Included by admin/inquiries/index.php, which provides:
 * $selected (the inquiry row) and $responses (its saved responses).
 */
$status = $selected['status'];
$isClosed = $status === 'closed';
?>
<dialog class="dialog dialog-review" id="review-dialog" aria-labelledby="review-title" data-autoopen>
    <form id="inquiry-form" class="review-form" novalidate
          data-has-response="<?= $responses ? '1' : '0' ?>" data-status="<?= e($status) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $selected['id'] ?>">
        <input type="hidden" name="version" value="<?= e($selected['updated_at']) ?>">

        <header class="dialog-head">
            <div>
                <p class="dialog-kicker">Inquiry · <?= e($selected['reference_no']) ?></p>
                <h2 class="dialog-title" id="review-title"><?= e($selected['subject']) ?></h2>
            </div>
            <div class="dialog-head-side">
                <?= status_badge($status, 'inquiry') ?>
                <button type="button" class="dialog-x" data-dialog-close aria-label="Close">&times;</button>
            </div>
        </header>

        <div class="dialog-body">
            <section>
                <h3 class="review-heading">Inquiry details</h3>
                <dl class="detail-grid">
                    <?= detail_item('Submitted by', e($selected['name'])) ?>
                    <?= detail_item('Email', e($selected['email'])) ?>
                    <?= detail_item('Category', e($selected['category'])) ?>
                    <?= detail_item('Submitted', e(format_datetime($selected['created_at']))) ?>
                    <?php if ($status === 'resolved' && $selected['resolved_at']): ?>
                        <?= detail_item('Resolved', e(format_datetime($selected['resolved_at']))) ?>
                    <?php elseif ($isClosed && $selected['closed_at']): ?>
                        <?= detail_item('Closed', e(format_datetime($selected['closed_at']))) ?>
                    <?php endif; ?>
                </dl>
            </section>

            <section>
                <h3 class="review-heading">Message</h3>
                <div class="inquiry-message"><?= nl2br(e($selected['message'])) ?></div>
            </section>

            <section>
                <h3 class="review-heading">Saved responses</h3>
                <?php if ($responses): ?>
                    <ul class="response-list">
                    <?php foreach ($responses as $r): ?>
                        <li class="response-item">
                            <div class="response-body"><?= nl2br(e($r['body'])) ?></div>
                            <div class="response-meta">
                                <?= e(format_datetime($r['created_at'])) ?><?= $r['admin_name'] ? ' · ' . e($r['admin_name']) : '' ?>
                                · <?= e(inquiry_email_label($r['email_status'])) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">No response has been saved yet.</p>
                <?php endif; ?>
            </section>

            <section class="review-decision">
                <h3 class="review-heading">Response and resolution</h3>
                <div class="decision-grid">
                    <div class="field">
                        <label class="field-label-sm" for="inq-status">Status</label>
                        <select class="select" id="inq-status" name="status">
                            <?php foreach (inquiry_manual_status_options($status) as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $key === $status ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label-sm" for="inq-notes">Resolution notes</label>
                        <textarea class="textarea textarea-compact" id="inq-notes" name="notes" rows="1" maxlength="<?= INQUIRY_NOTES_MAX ?>"
                                  placeholder="Record what was done"><?= e($selected['resolution_notes'] ?? '') ?></textarea>
                    </div>
                    <div class="field field-wide">
                        <label class="field-label-sm" for="inq-response">Response to submitter</label>
                        <textarea class="textarea" id="inq-response" name="response" rows="4" maxlength="<?= INQUIRY_RESPONSE_MAX ?>"
                                  placeholder="Type a response"<?= $isClosed ? ' disabled' : ' autofocus' ?>></textarea>
                    </div>
                </div>
                <p class="review-hint">
                    <?php if ($isClosed): ?>
                        This inquiry is closed. Change the status to Open and save to reopen it.
                    <?php else: ?>
                        Saving a response stores it here. Email delivery is not configured yet, so nothing is emailed to the submitter.
                        An inquiry can only be marked resolved after a response has been saved.
                    <?php endif; ?>
                </p>
            </section>

            <div class="review-message flash" id="review-message" role="alert" hidden></div>
        </div>

        <footer class="dialog-foot">
            <button type="button" class="btn btn-red" data-action="close"<?= $isClosed ? ' disabled' : '' ?>>Close inquiry</button>
            <span class="spacer"></span>
            <button type="button" class="btn btn-light" data-dialog-close>Cancel</button>
            <button type="button" class="btn btn-green" data-action="resolve"<?= in_array($status, ['resolved', 'closed'], true) ? ' disabled' : '' ?>>Mark resolved</button>
            <button type="button" class="btn btn-dark" data-action="save">Save response</button>
        </footer>
    </form>
</dialog>
