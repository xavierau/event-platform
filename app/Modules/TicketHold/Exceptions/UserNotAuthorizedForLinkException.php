<?php

namespace App\Modules\TicketHold\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotAuthorizedForLinkException extends Exception
{
    public function __construct(
        string $message = 'You are not authorized to use this purchase link.',
        int $code = 403,
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
            'error' => 'user_not_authorized_for_link',
        ], 403);
    }
}
