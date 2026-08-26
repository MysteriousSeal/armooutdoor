<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * La comptabilité : ce qui est entré, ce qui est sorti.
 *
 * Les deux pages sont encore vides. Elles existent d'abord pour tenir la
 * place dans la navigation et fixer l'adresse, le reste viendra dedans.
 */
class AccountingController extends Controller
{
    public function sales(): View
    {
        return view('admin.accounting.sales');
    }

    public function purchases(): View
    {
        return view('admin.accounting.purchases');
    }
}
