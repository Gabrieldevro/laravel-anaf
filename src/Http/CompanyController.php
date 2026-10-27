<?php

namespace Gabrieldev\Anaf\Http;

use Gabrieldev\Anaf\AnafClient;
use Gabrieldev\Anaf\AnafException;
use Gabrieldev\Anaf\Cui;
use Illuminate\Http\JsonResponse;

/** GET /{prefix}/{cui} → billing fields for autocomplete. Enabled only when anaf.route.prefix is set. */
class CompanyController
{
    public function __invoke(AnafClient $anaf, string $cui): JsonResponse
    {
        if (! Cui::isValid($cui)) {
            return response()->json(['message' => trans('anaf::validation.cui', ['attribute' => 'CUI'])], 422);
        }

        try {
            $company = $anaf->company($cui);
        } catch (AnafException) {
            return response()->json(['message' => trans('anaf::validation.unavailable')], 503);
        }

        if ($company === null) {
            return response()->json(['message' => trans('anaf::validation.not_found')], 404);
        }

        return response()->json([
            'data' => $company->toBilling() + [
                'active' => $company->isActive(),
                'e_invoice' => $company->eInvoice,
            ],
        ]);
    }
}
