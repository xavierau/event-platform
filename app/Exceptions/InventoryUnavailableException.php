<?php

namespace App\Exceptions;

use Exception;

class InventoryUnavailableException extends Exception
{
    // You can customize this exception further if needed,
    // e.g., by adding a constructor to pass specific data.
    public function __construct($message = "Inventory unavailable for one or more items.", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    // Optionally, you can add a render method if you want to customize the HTTP response
    // when this exception is thrown and not caught elsewhere.
    // public function render($request)
    // {
    //     return response()->json(['message' => $this->getMessage()], 409); // 409 Conflict
    // }
}
