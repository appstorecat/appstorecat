<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $html = '<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>AppStoreCat API</title>
<style>body{margin:0;background:#000;display:flex;justify-content:center;align-items:center;height:100vh;font-family:system-ui,sans-serif}
a{color:#fff;font-size:1.25rem;text-decoration:none;opacity:.6;transition:opacity .2s}a:hover{opacity:1}</style></head>
<body><a href="https://github.com/appstorecat/appstorecat" target="_blank">github.com/appstorecat/appstorecat</a></body></html>';

    return response($html, 200, ['Content-Type' => 'text/html']);
});
