<?php

return [
    'session_retention_days' => (int) env('PRIVACY_SESSION_RETENTION_DAYS', 30),
    'notification_personal_data_days' => (int) env('PRIVACY_NOTIFICATION_DATA_DAYS', 90),
    'support_attachment_days_after_close' => (int) env('PRIVACY_SUPPORT_ATTACHMENT_DAYS', 365),
];
