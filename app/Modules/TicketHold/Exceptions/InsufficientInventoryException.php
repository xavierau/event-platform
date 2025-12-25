<?php

namespace App\Modules\TicketHold\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsufficientInventoryException extends Exception
{
    public function __construct(
        string $message = 'Insufficient inventory available for the requested tickets.',
        int $code = 409,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'insufficient_inventory',
        ], 409);
    }
}
