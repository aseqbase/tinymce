<?php

use MiMFa\Library\Convert;
use MiMFa\Library\TinyMCEContentSanitizer;
use MiMFa\Module\TinyMCE;
use MiMFa\Module\TinyMCEContentTable;

$data = $data ?? [];
$routeHandler = function ($data) {
    auth(\_::$User->AdminAccess);
    module('Table');
    module('TinyMCEContentTable');
    module('TinyMCE');
    library('TinyMCEContentSanitizer');
    TinyMCE::RegisterEditor();

    $module = new TinyMCEContentTable('Content');
    $module->AddHandler = fn($values) => $module->AddRow(TinyMCEContentSanitizer::SanitizeValues($values));
    $module->DuplicateHandler = fn($values) => $module->DuplicateRow(TinyMCEContentSanitizer::SanitizeValues($values));
    $module->ModifyHandler = fn($values) => $module->ModifyRow(TinyMCEContentSanitizer::SanitizeValues($values));

    $userTable = \_::$User->DataTable->Name;
    $module->SelectQuery = "
    SELECT A.{$module->KeyColumn}, A.Type, A.Image, A.Title, A.Description, CONCAT_WS(' ', A.Title, A.Description, A.Name, A.Content) AS SearchContent, A.CategoryIds AS 'Route', A.Name, A.Priority, A.Status, A.MetaData AS 'Lang', A.Access, B.Name AS 'Author', C.Name AS 'Editor', A.CreateTime, A.UpdateTime
    FROM {$module->DataTable->Name} AS A
    LEFT OUTER JOIN $userTable AS B ON A.AuthorId=B.Id
    LEFT OUTER JOIN $userTable AS C ON A.EditorId=C.Id
    ORDER BY A.Priority DESC, A.UpdateTime DESC";
    $module->KeyColumns = ['Image', 'Title'];
    $module->IncludeColumns = ['Type', 'Image', 'Title', 'SearchContent', 'Route', 'Priority', 'Status', 'Lang', 'Access', 'Author', 'Editor', 'CreateTime', 'UpdateTime'];
    $module->AllowDataTranslation = false;
    $module->AllowServerSide = false;
    $module->Quick = false;
    $module->Updatable = true;
    $module->UpdateAccess = \_::$User->AdminAccess;

    $users = table('User')->SelectPairs('Id', 'Name');
    $languages = \_::$Front->Translate->GetLanguages();
    $module->CellsValues = [
        'Title' => function ($value, $key, $record) {
            return \MiMFa\Library\Struct::Link($value, \_::$Address->ContentRootUrlPath . $record['Id'], ['target' => 'blank']);
        },
        'Route' => function ($value, $key, $record) {
            $route = trim(\_::$Back->Query->GetCategoryRoute(first(Convert::FromJson($value))) ?? '', '/\\');
            if (isValid($route))
                return \MiMFa\Library\Struct::Link(
                    "\${{{$route}/{$record['Name']}}}",
                    \_::$Address->ContentRootUrlPath . "{$route}/{$record['Name']}",
                    ['target' => 'blank']
                );
            return $value;
        },
        'Lang' => function ($value) use ($languages) {
            return $value ? get(get($languages, get(Convert::FromJson($value), 'lang')), 'Title') ?? 'Default' : 'Default';
        },
        'Status' => fn($value) => \MiMFa\Library\Struct::Span($value > 0 ? 'Published' : ($value < 0 ? 'Unpublished' : 'Drafted')),
        'SearchContent' => function ($value) {
            $plain = Convert::ToText($value);
            $normalized = TinyMCEContentTable::NormalizeSearchValue($plain);
            return '<span class="tinymce-search-content">'
                . htmlspecialchars($plain . ' ' . $normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</span>';
        },
        'CreateTime' => fn($value) => Convert::ToShownDateTimeString($value),
        'UpdateTime' => fn($value) => Convert::ToShownDateTimeString($value)
    ];

    $module->CellsTypes = [
        'Id' => \_::$User->HasAccess(\_::$User->SuperAccess) ? 'disabled' : false,
        'Name' => 'text',
        'Type' => get($data, 'Type') ? function ($title, $value) use ($data) {
            $field = new stdClass();
            $field->Type = 'hidden';
            $field->Value = get($data, 'Type');
            return $field;
        } : 'enum',
        'Title' => 'text',
        'Image' => 'image',
        'Description' => 'texts',
        'Content' => 'texts',
        'CategoryIds' => function () {
            $field = new stdClass();
            $field->Title = 'Categories';
            $field->Type = 'array';
            $field->Options = [
                'Type' => 'select',
                'Key' => 'CategoryIds',
                'Options' => table('Category')->SelectPairs('`Id`', '`Name`', 'ORDER BY `ParentId` ASC')
            ];
            return $field;
        },
        'TagIds' => function () {
            $field = new stdClass();
            $field->Title = 'Tags';
            $field->Type = 'array';
            $field->Options = [
                'Type' => 'select',
                'Key' => 'TagIds',
                'Options' => table('Tag')->SelectPairs('`Id`', '`Name`')
            ];
            return $field;
        },
        'Status' => [-1 => 'Unpublished', 0 => 'Drafted', 1 => 'Published'],
        'Access' => function () {
            $field = new stdClass();
            $field->Type = 'number';
            $field->Attributes = ['min' => \_::$User->BanAccess, 'max' => \_::$User->SuperAccess];
            return $field;
        },
        'Attach' => 'json',
        'Path' => 'text',
        'Priority' => 'number',
        'AuthorId' => function ($title, $value) use ($users) {
            $field = new stdClass();
            $field->Title = 'Author';
            $field->Type = \_::$User->HasAccess(\_::$User->SuperAccess) ? 'select' : 'hidden';
            $field->Options = $users;
            if (!isValid($value))
                $field->Value = \_::$User->Id;
            return $field;
        },
        'EditorId' => function ($title, $value) use ($users) {
            $field = new stdClass();
            $field->Title = 'Editor';
            $field->Type = \_::$User->HasAccess(\_::$User->SuperAccess) ? 'select' : 'hidden';
            $field->Options = $users;
            if (!isValid($value))
                $field->Value = \_::$User->Id;
            return $field;
        },
        'UpdateTime' => function ($title, $value) {
            $field = new stdClass();
            $field->Type = \_::$User->HasAccess(\_::$User->SuperAccess) ? 'calendar' : 'hidden';
            $field->Value = Convert::ToDateTimeString();
            return $field;
        },
        'CreateTime' => function ($title, $value) {
            return \_::$User->HasAccess(\_::$User->SuperAccess) ? 'calendar' : (isValid($value) ? 'hidden' : false);
        },
        'MetaData' => function ($title, $value) {
            $field = new stdClass();
            $field->Type = 'json';
            if (!$value && \_::$Front->AllowTranslate)
                $field->Value = '{"lang":"' . \_::$Front->Translate->Language . '"}';
            return $field;
        }
    ];

    pod($module, $data);
    return $module->ToString();
};

(new Router())->if(\_::$User->HasAccess(\_::$User->AdminAccess))
    ->Get(function () use ($routeHandler) {
        (\_::$Front->AdminView)($routeHandler, [
            'Image' => 'file',
            'Title' => 'Content Management'
        ]);
    })
    ->Default(fn() => response($routeHandler($data)))
    ->Handle();
