<?php

return [
    'smtp_host'       => 'smtp.gmail.com',
    'smtp_port'       => 587,
    'smtp_username'   => 'foodorderingdeliverysystem@gmail.com',
    'smtp_password'   => 'ckwvyaquiryzkcib',
    'smtp_encryption' => 'tls',

    'from_email'      => 'foodorderingdeliverysystem@gmail.com',
    'from_name'       => 'Herald Canteen',

    'dev_log_fallback' => false,
    'dev_log_path'     => __DIR__ . '/../logs/mail_dev.log',
];
