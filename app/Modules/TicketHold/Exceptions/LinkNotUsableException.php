<?php

namespace App\Modules\TicketHold\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkNotUsableException extends Exception
{
    public function __construct(
        string $message = 'This purchase link is not usable.',
        int $code = 422,
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
            'error' => 'link_not_usable',
        ], 422);
    }
}
