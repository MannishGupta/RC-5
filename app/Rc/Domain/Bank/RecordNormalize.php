<?php
declare(strict_types=1);
namespace App\Rc\Domain\Bank;

final class RecordNormalize
{
    

    public static function apply(array $newData): array
    {
        if (!empty($newData['ifsc'])) {
            $newData['ifsc'] = strtoupper(preg_replace('/\s+/', '', (string) $newData['ifsc']) ?? '');
        }
        if (!empty($newData['upi_id'])) {
            $newData['upi_id'] = (((string) $newData['upi_id']) |> trim(...) |> strtolower(...));
        } elseif (!empty($newData['upi'])) {
            $newData['upi'] = (((string) $newData['upi']) |> trim(...) |> strtolower(...));
            $newData['upi_id'] = $newData['upi'];
        }
        if (!empty($newData['acc_no'])) {
            $newData['acc_no'] = preg_replace('/\s+/', '', (string) $newData['acc_no']);
        }
        return $newData;
    }
}
