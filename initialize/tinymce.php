<?php

plugin('TinyMCE');
module('TinyMCE');

\_::$Joint->TinyMCE = new MiMFa\Plugin\TinyMCE();
\MiMFa\Module\TinyMCE::RegisterContentStyles();
\_::$Joint->TinyMCE->RegisterContentManagementRoute();

if (\_::$User->HasAccess(\_::$User->AdminAccess) && isset(\_::$Front->AdminMenus['Administrator-System'])) {
    \_::$Front->AdminMenus['Administrator-System']['Items'][] = [
        'Name' => 'TinyMCE Editor',
        'Path' => '/administrator/tinymce/editor',
        'Access' => \_::$User->AdminAccess,
        'Image' => 'edit'
    ];
}

