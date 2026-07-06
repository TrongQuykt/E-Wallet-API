<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "E-Wallet API Documentation",
    description: "Hệ thống REST API Ví Điện Tử (E-Wallet) - Laravel 11",
    contact: new OA\Contact(email: "admin@e-wallet.com")
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: "E-Wallet API Server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    in: "header",
    name: "Authorization",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
abstract class Controller
{
    //
}

