<?php

return [
    'server_url'  => env('SOCKET_SERVER_URL', 'http://socketserver:3000'),
    'public_url'  => env('SOCKET_PUBLIC_URL', 'http://localhost:3000'),
    'secret'      => env('SOCKET_SECRET', 'socket_secret'),
];
