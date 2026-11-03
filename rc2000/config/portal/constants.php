<?php
/**
 * config/constants.php
 * Status values and labels shared by the PHP backend and the pages.
 * Keep these in sync with database/schema.sql.
 */

const BOOK_STATUSES = [
    'pending'  => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];

const MONEY_STATUSES = [
    'pending'      => 'Pending',
    'verified'     => 'Verified',
    'not_verified' => 'Not verified',
];

const INQUIRY_STATUSES = [
    'open'        => 'Open',
    'in_progress' => 'In progress',
    'resolved'    => 'Resolved',
    'closed'      => 'Closed',
];

const INQUIRY_CATEGORIES = ['Request', 'Technical', 'Report', 'Inquiry'];

const CONTENT_STATUSES = [
    'draft'     => 'Draft',
    'published' => 'Published',
    'archived'  => 'Archived',
];

const BOOK_CONDITIONS = ['Excellent', 'Good', 'Fair', 'Poor'];

// Login throttling: block after this many failures within the window
const LOGIN_MAX_ATTEMPTS   = 5;
const LOGIN_WINDOW_MINUTES = 15;

// Admin session expires after this many seconds of inactivity
const SESSION_IDLE_SECONDS = 7200; // 2 hours

const MIN_PASSWORD_LENGTH = 10;
