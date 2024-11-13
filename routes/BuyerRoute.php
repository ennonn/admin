<?php

namespace Routes\BuyerRoute;

use App\Controllers\BuyerController;
use App\Providers\Validation\ValidateTokenProvider;
use Config\DatabaseConnection;

class BuyerRoute
{
    private $db;
    private $tokenValidator;

    public function __construct()
    {
        // Instantiate DatabaseConnection and ValidateTokenProvider
        $dbConnection = new DatabaseConnection();
        $this->db = $dbConnection->getConnection();
        $this->tokenValidator = new ValidateTokenProvider($this->db);
    }

    public function handleBuyerRoute($uri, $method)
    {
        // Instantiate BuyerController with dependencies
        $buyerController = new BuyerController($this->db, $this->tokenValidator);

        // Normalize URI for accurate matching
        $uri = str_replace('/buyer', '', $uri);

        // Retrieve and validate the JWT token
        $token = $this->getBearerToken();
        if (!$token) {
            $this->sendErrorResponse('Unauthorized: Token missing', 401);
            return;
        }
        
        if (!$this->isTokenValid($token)) {
            $this->sendErrorResponse('Unauthorized: Invalid token', 401);
            return;
        }

        // Route handling
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

    // Token validation
    private function isTokenValid($token)
    {
        try {
            $this->tokenValidator->validateToken($token);
            return true;
        } catch (\Exception $e) {
            error_log('Token validation failed: ' . $e->getMessage());
            return false;
        }
    }

    // Extract Bearer token from the request headers
    private function getBearerToken()
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!empty($authHeader) && strpos($authHeader, 'Bearer ') === 0) {
            return str_replace('Bearer ', '', $authHeader);
        }
        return null; // Return null if token is not found
    }

    // Send JSON error response
    private function sendErrorResponse($message, $statusCode = 400)
    {
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        http_response_code($statusCode);
    }
}
