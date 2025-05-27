<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class MyWishlistController extends Controller
{
    /**
     * Display the user's wishlist page.
     */
    public function index(): Response
    {
        return Inertia::render('Public/MyWishlist');
    }
}
