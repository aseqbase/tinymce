<?php
auth(\_::$User->AdminAccess);
$data = $data ?? [];
$routeHandler = function ($data) {
    return \MiMFa\Library\Revise::ToString(\_::$Joint->TinyMCE);
};
(new Router())
    ->Get(function () use ($routeHandler) {
        (\_::$Front->AdminView)($routeHandler, [
            "Image" => "edit",
            "Title" => "'TinyMCE Editor' Configurations"
        ]);
    })
    ->Default(fn() => response($routeHandler($data)))
    ->Handle();