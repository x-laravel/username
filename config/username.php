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

    /*
    |--------------------------------------------------------------------------
    | Username History
    |--------------------------------------------------------------------------
    |
    | When a username changes, the old one is kept so that old profile links
    | still find the account, and so that nobody else can take it right away.
    | A model can opt out with a $usernameHistory property.
    |
    | enabled         Keep old usernames and resolve them. When disabled,
    |                 nothing is written, old usernames are not resolved and
    |                 nothing is reserved; existing rows are left as they are.
    | connection      Database connection of the history table. Use the same
    |                 connection as your models so both are written in one
    |                 transaction. Null uses the default connection.
    | table           Name of the history table.
    | reserve_for     Days an old username stays reserved for its owner; no
    |                 one else can take it in that time. Null reserves it for
    |                 as long as the row exists, 0 does not reserve it at all.
    |                 A model can override it with a $usernameReservedFor
    |                 property.
    | prune_after     Days after which history rows are deleted. Null keeps
    |                 them forever.
    | prune_schedule  How often the package schedules model:prune for the
    |                 history table: a schedule frequency such as "daily" or
    |                 "hourly", or a cron expression. Null leaves scheduling
    |                 to you.
    |
    */

    'history' => [
        'enabled' => true,
        'connection' => null,
        'table' => 'username_history',
        'reserve_for' => 90,
        'prune_after' => 365,
        'prune_schedule' => 'daily',
    ],

];
