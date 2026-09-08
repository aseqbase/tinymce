<?php

namespace MiMFa\Plugin;

class TinyMCE extends \MiMFa\Library\Revise
{
    /** @field text */
    public $Title = 'TinyMCE Editor';

    /** @field texts */
    public $Description = 'Modular rich content editor for AseqBase websites';

    /** @field path */
    public $Image = 'edit';

    /** @field number */
    public $EditorHeight = 620;

    /** @field number */
    public $MinimumEditorHeight = 480;

    public $TinyMCEVersion = '8.9.0';

    public $FontFamilies = [
        'B Nazanin' => 'B Nazanin,Tahoma,sans-serif',
        'Vazirmatn' => 'Vazirmatn,Vazir,Tahoma,Arial,sans-serif',
        'B Yekan' => 'B Yekan,IRANYekanX,Tahoma,sans-serif',
        'B Titr' => 'B Titr,Tahoma,sans-serif',
        'B Koodak' => 'B Koodak,Tahoma,sans-serif',
        'Times New Roman' => 'Times New Roman,Times,serif',
        'Arial' => 'Arial,sans-serif',
        'Calibri' => 'Calibri,Arial,sans-serif'
    ];

    public function RegisterContentManagementRoute(): void
    {
        if (!\_::$User->HasAccess(\_::$User->AdminAccess))
            return;

        $request = strtolower(trim((string)(\_::$Address->UrlRequest ?? ''), '/\\ '));
        if (!str_starts_with($request, 'administrator/content/contents'))
            return;

        \_::$Router->On('administrator')->Reset();
        \_::$Router->On('administrator/content/contents')->Default('administrator/tinymce/contents');
        \_::$Router->On('administrator')->Default(
            \_::$Address->UrlRoute,
            alternative: \_::$Router->DefaultRouteName
        );
    }
}

