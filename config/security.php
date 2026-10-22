<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Admin IP Whitelist
    |--------------------------------------------------------------------------
    | รายการ IP ที่อนุญาตให้เปิดหน้า /admin/login (คั่นด้วย comma)
    |
    | อ่านผ่าน config() เท่านั้น ห้ามเรียก env() ใน middleware/controller โดยตรง
    | เพราะเมื่อรัน `php artisan config:cache` ค่า env() จะคืน null ทำให้ whitelist
    | ถูกปิดเงียบ ๆ (fail-open)
    */
    'admin_ip_whitelist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_IP_WHITELIST', ''))
    ))),

];
