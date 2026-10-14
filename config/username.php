<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Format Rules
    |--------------------------------------------------------------------------
    |
    | These options define what a valid username looks like. The Username rule,
    | the "username" string alias and the generator all follow them. Letters
    | are always limited to the English alphabet so a username fits in a URL
    | without encoding.
    |
    | min / max    Length limits, in characters.
    | dots         Allow dots. A dot can never start or end a username, and two
    |              dots can never follow each other.
    | hyphens      Allow hyphens (-).
    | underscores  Allow underscores (_).
    | uppercase    Allow uppercase letters. When disabled, the rule rejects
    |              capitals; lowercase the input before validating it if users
    |              may type them.
    | numeric      Allow usernames made of digits only, such as "12345". Turn
    |              it off when such names could be mistaken for IDs.
    |
    */

    'rules' => [
        'min' => 2,
        'max' => 30,
        'dots' => true,
        'hyphens' => true,
        'underscores' => true,
        'uppercase' => false,
        'numeric' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Username
    |--------------------------------------------------------------------------
    |
    | When the text given to the generator leaves nothing usable after it is
    | normalized (for example "@@@"), the generator starts from this value and
    | numbers it until it finds a free username.
    |
    */

    'fallback' => 'user',

    /*
    |--------------------------------------------------------------------------
    | Reserved Usernames
    |--------------------------------------------------------------------------
    |
    | The NotReserved rule rejects these names and the generator never returns
    | them. Matching ignores case, dots, hyphens and underscores, so "Ad.min"
    | is treated as "admin". Add your own brand and product names here.
    |
    */

    'reserved' => [
        'about', 'abuse', 'account', 'accounts', 'admin', 'administrator', 'all', 'anonymous', 'api',
        'auth', 'billing', 'contact', 'dashboard', 'email', 'everyone', 'guest', 'help', 'hostmaster',
        'info', 'login', 'logout', 'mail', 'me', 'mod', 'moderator', 'noreply', 'null', 'official',
        'oauth', 'payment', 'payments', 'postmaster', 'privacy', 'profile', 'register', 'root',
        'security', 'settings', 'signin', 'signup', 'staff', 'status', 'support', 'system', 'team',
        'terms', 'undefined', 'user', 'users', 'webmaster', 'www',
        'ayarlar', 'cikis', 'destek', 'giris', 'gizlilik', 'guvenlik', 'hakkinda', 'hesap', 'iletisim',
        'kayit', 'kullanici', 'odeme', 'profil', 'resmi', 'sistem', 'yardim', 'yonetici', 'yonetim',
    ],

];
