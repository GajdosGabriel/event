<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\EmailSuppression;
use App\Services\SystemLog\Recorder;
use Illuminate\Http\Request;

/**
 * Odhlásenie nevyžiadaných e-mailov jedným klikom (EmailSuppression).
 *
 * Podpísaný odkaz z e-mailu — GET z textu správy (presmeruje na stránku
 * s potvrdením), POST z tlačidla poštového klienta (List-Unsubscribe-Post,
 * RFC 8058), ktorý žiadne presmerovanie nečíta.
 */
class EmailUnsubscribeController extends Controller
{
    public function __invoke(Request $request)
    {
        $email = mb_strtolower(trim((string) $request->query('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $suppression = EmailSuppression::add($email, 'unsubscribe', 'outreach');

            if ($suppression->wasRecentlyCreated) {
                Recorder::info('mail', 'unsubscribed', 'Odhlásenie nevyžiadaných e-mailov',
                    status: 'ok',
                    recipient: $email,
                    context: ['source' => 'outreach', 'method' => $request->method()],
                );
            }
        }

        if ($request->isMethod('post')) {
            return response()->noContent();
        }

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/') . '/odhlasenie');
    }
}
