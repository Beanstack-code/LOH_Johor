<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;

final class PdfReceipt
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $view, array $data): string
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
