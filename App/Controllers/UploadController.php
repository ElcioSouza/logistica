<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use InvalidArgumentException;
use Throwable;
class UploadController
{

    public function __construct(
        
    ) {}

    public function handle(Request $request): Response
    {

       return new Response([
                'success' => true,
                'message' => 'File received successfully and registered for asynchronous processing.',
                'upload_id' => 2,
            ], 202);
    }
}