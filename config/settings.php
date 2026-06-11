<?php

return [
    'upload_max_size_mb'       => 20,
    'upload_dir'               => __DIR__ . '/../uploads/',
    'output_dir'               => __DIR__ . '/../output/fix_scripts/',
    'session_timeout_minutes'  => 30,
    'trust_server_certificate' => true,   // For SQL Server 2019+ / SSMS 19+
    'encrypt'                  => true,
    'default_port'             => 1433,
    'comparison_ignore_case'   => true,   // Object name comparison
    'show_source_extras'       => true,   // Report objects in Target missing in Source (blue items)
];
