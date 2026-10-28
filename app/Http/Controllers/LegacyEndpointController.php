<?php

namespace App\Http\Controllers;

use App\Exceptions\Hapi\HapiException;
use App\Exceptions\Portal\PortalAccessDenied;
use App\Portal\PortalRepository;
use App\Portal\UserKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Keeps the image / link actions of the old API URL working
 * ("/forms/public/regos/index.php?action=qr_captain&user_key=...") for
 * emails and integrations that still reference it.
 */
class LegacyEndpointController extends Controller
{
    private const ACTIONS = ['qr', 'qr_captain', 'short'];

    public function __invoke(Request $request, PortalRepository $repository): Response|JsonResponse
    {
        $action = $request->query('action');

        if (! in_array($action, self::ACTIONS, true)) {
            return response()->json(['message' => 'Unsupported action'], 404);
        }

        try {
            $key = UserKey::fromString((string) $request->query('user_key'));
            $repository->authorize($key);

            return match ($action) {
                'qr' => $key->hasRegistration()
                    ? BoardingPassController::svg($repository->registrationQr($key->registrationId))
                    : response()->json(['message' => 'No registration provided'], 404),
                'qr_captain' => BoardingPassController::svg($repository->captainQr($key->bookingId)),
                'short' => response($repository->shortLink($key->bookingId), 200, ['Content-Type' => 'text/plain; charset=UTF-8']),
            };
        } catch (PortalAccessDenied $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (HapiException) {
            return response()->json(['message' => 'An error has occurred'], 502);
        }
    }
}
