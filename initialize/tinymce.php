<?php
plugin("TinyMCE");
\_::$Joint->TinyMCE = new MiMFa\Plugin\TinyMCE();

// TODO: Initializing if needed

if (\_::$User->HasAccess(\_::$User->AdminAccess) && isset(\_::$Front->AdminMenus["Administrator"])) {
    \_::$Front->AdminMenus["Administrator-System"]["Items"][] = 
        array("Name" => "TinyMCE", "Path" => "/administrator/tinymce/editor", "Access" => \_::$User->AdminAccess, "Image" => "edit");
}