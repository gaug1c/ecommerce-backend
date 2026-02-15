<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        Password::sendResetLink($request->only('email'));

        // Toujours retourner succès (sécurité)
        return response()->json([
            'success' => true,
            'message' => 'Si un compte est associé à cette adresse email, un lien de réinitialisation a été envoyé.',
            'data' => null,
            'errors' => null
        ]);
    }
}
