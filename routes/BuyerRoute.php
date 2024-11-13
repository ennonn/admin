<?php

namespace Routes\BuyerRoute;

use App\Controllers\BuyerController;
use App\Providers\Auth\JWTProvider;

class BuyerRoute
{
    private $jwtProvider;

    public function __construct()
    {
        $this->jwtProvider = new JWTProvider();
    }

    public function handleBuyerRoute($uri, $method)
    {
        $buyerController = new BuyerController();

        // Normalize URI for accurate matching
        $uri = str_replace('/buyer', '', $uri);

        // Extract and validate JWT token
        $token = $this->getBearerToken();
        if (!$this->isTokenValid($token)) {
            $this->sendErrorResponse('Unauthorized', 401);
            return;
        }

        switch ($uri) {
            case '/cart/add':
                if ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo $buyerController->addProductToCart($token, $data['product_id'], $data['quantity']);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            case '/cart/view':
                if ($method === 'GET') {
                    echo $buyerController->viewCart($token);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            case '/cart/remove':
                if ($method === 'DELETE') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo $buyerController->removeProductFromCart($token, $data['product_id']);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            case '/cart/checkout':
                if ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo $buyerController->checkoutCart($token, $data);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            default:
                $this->sendErrorResponse('Route not found', 404);
                break;
        }
    }

    // JWT token validation
    private function isTokenValid($token)
    {
        return $this->jwtProvider->verifyToken($token);
    }

    // Extract Bearer token
    private function getBearerToken()
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!empty($authHeader) && strpos($authHeader, 'Bearer ') !== false) {
            return str_replace('Bearer ', '', $authHeader);
        }
        return null;
    }

    // Send JSON error response
    private function sendErrorResponse($message, $statusCode = 400)
    {
        echo json_encode(['status' => 'error', 'message' => $message]);
        http_response_code($statusCode);
    }
}
