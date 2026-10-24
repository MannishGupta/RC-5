<?php
declare(strict_types=1);
namespace App\Rc\Support;

 
final class SafeName
{
    public static function slug(string $s): string
    {
        $s = ((str_replace('\\', '/', $s)) |> trim(...) |> strtolower(...));
        $s = basename($s);
        return (string) preg_replace('/[^a-z0-9\-]+/', '', $s);
    }

    public static function fileStem(string $s): string
    {
        $s = ((basename(str_replace('\\', '/', $s))) |> trim(...) |> strtolower(...));
        return (string) preg_replace('/[^a-z0-9_-]+/', '', $s);
    }
}
