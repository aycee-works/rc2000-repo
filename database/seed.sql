-- 1. DEFAULT ADMINS
INSERT INTO admins (
    full_name,
    email,
    password_hash,
    role
)
VALUES
(
    'RC2000 SUPERADMIN',
    'rc2000.superadmin@gmail.com',
    '$2y$10$VruEp6WJwTQ/MtqiQICHmuiQUqWfVSPU84bP5ymPQafPNrkpcCh1C',
    'super_admin'
),
(
    'RC2000 ADMIN',
    'rc2000.admin@gmail.com',
    '$2y$10$guh0JwyvnZUyd36.9FmvJu/ClJa6JNg8eGYH0xNIXTrtoIEAIvPyS',
    'admin'
);

-- 2. SAMPLE CONTENT
INSERT INTO content (
    content_type,
    title,
    body,
    author_name,
    status,
    created_by,
    updated_by,
    published_at
)
VALUES
(
    'announcement',
    'Welcome to RC2000',
    'Official launch of our website.',
    'RC2000 Team',
    'published',
    1,
    1,
    CURRENT_TIMESTAMP
),
(
    'story',
    'A Child Received New Books',
    'A heartwarming story of a student receiving donated books.',
    'Community Volunteer',
    'published',
    1,
    1,
    CURRENT_TIMESTAMP
);

-- 3. SAMPLE TRIVIA
INSERT INTO trivia (
    title,
    body,
    source,
    status,
    created_by,
    updated_by,
    published_at
)
VALUES
(
    'Reading Fact',
    'Reading 20 minutes every day improves vocabulary and comprehension.',
    'National Literacy Research',
    'published',
    1,
    1,
    CURRENT_TIMESTAMP
);

-- 4. SAMPLE DONATION METHODS
INSERT INTO donation_methods (
    method_name,
    payment_provider,
    qr_image_path,
    instructions,
    created_by
)
VALUES
(
    'GCash Donation',
    'GCash',
    '/storage/qr/gcash.png',
    'Scan the QR code and send your donation.',
    1
),
(
    'Maya Donation',
    'Maya',
    '/storage/qr/maya.png',
    'Scan the QR code and complete payment.',
    1
);

-- 5. SAMPLE BOOK DONATIONS
INSERT INTO donations (
    donor_name,
    contact_info,
    quantity,
    donation_description,
    status,
    handled_by
)
VALUES
(
    'Juan Dela Cruz',
    'juan@email.com',
    30,
    'Children story books',
    'received',
    2
),
(
    'Maria Santos',
    'maria@email.com',
    15,
    'Educational reference books',
    'pending',
    NULL
);

-- 6. SAMPLE MONETARY DONATIONS
INSERT INTO monetary_donations (
    donor_name,
    contact_info,
    donation_method_id,
    amount_reported,
    transaction_reference,
    payment_date
)
VALUES
(
    'John Doe',
    'john@email.com',
    1,
    500.00,
    'GCASH-10001',
    CURRENT_TIMESTAMP
),
(
    'Jane Cruz',
    'jane@email.com',
    2,
    1000.00,
    'MAYA-10001',
    CURRENT_TIMESTAMP
);

-- 7. SAMPLE INQUIRIES
INSERT INTO inquiries (
    sender_name,
    sender_email,
    subject,
    message,
    status
)
VALUES
(
    'Pedro Reyes',
    'pedro@email.com',
    'Book Donation Inquiry',
    'How can I donate books?',
    'pending'
),
(
    'Anna Lim',
    'anna@email.com',
    'Volunteer Program',
    'Do you accept volunteers?',
    'resolved'
);

-- 8. SAMPLE GALLERY IMAGES
INSERT INTO gallery_images (
    image_path,
    caption,
    alt_text,
    display_order,
    uploaded_by
)
VALUES
(
    '/storage/gallery/gallery1.jpg',
    'Book Distribution Event',
    'Students receiving books',
    1,
    1
),
(
    '/storage/gallery/gallery2.jpg',
    'Community Literacy Program',
    'Reading session with volunteers',
    2,
    1
);

-- 9. SAMPLE AUDIT LOGS
INSERT INTO admin_audit_logs (
    admin_id,
    action,
    entity_type,
    entity_id,
    details
)
VALUES
(
    1,
    'CREATE',
    'content',
    1,
    'Published announcement'
),
(
    2,
    'UPDATE',
    'donation',
    1,
    'Marked donation as received'
);

