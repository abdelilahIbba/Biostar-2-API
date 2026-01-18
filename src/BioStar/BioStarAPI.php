<?php

namespace BioStarSync\BioStar;

/**
 * BioStar 2 REST API Client
 * 
 * Handles authentication and communication with BioStar 2 API
 */
class BioStarAPI
{
    private $config;
    private $logger;
    private $sessionId;
    private $baseUrl;

    /**
     * Constructor
     * 
     * @param array $config BioStar configuration
     * @param object $logger Logger instance
     */
    public function __construct(array $config, $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->baseUrl = rtrim($config['base_url'], '/');
    }

    /**
     * Authenticate with BioStar 2 API
     * 
     * BioStar 2 login requires wrapping credentials in a "User" object:
     * POST /api/login
     * Body: { "User": { "login_id": "admin", "password": "admin123" } }
     * 
     * The API returns a session ID in the response header "bs-session-id"
     * This session ID must be included in all subsequent API requests as a header:
     * bs-session-id: <session_id_value>
     * 
     * @return bool Authentication success status
     */
    public function authenticate()
    {
        $endpoint = '/login';
        
        // BioStar 2 requires credentials wrapped in "User" object
        $payload = [
            'User' => [
                'login_id' => $this->config['login_id'],
                'password' => $this->config['password']
            ]
        ];

        try {
            $this->logger->info('Authenticating with BioStar 2 API...');
            
            // Make login request (requireAuth = false for login endpoint)
            $response = $this->requestWithHeaders('POST', $endpoint, $payload, false);
            
            // Extract session ID from response headers
            if (isset($response['headers']['bs-session-id'])) {
                $this->sessionId = $response['headers']['bs-session-id'];
                $this->logger->info('Successfully authenticated with BioStar 2');
                $this->logger->debug('Session ID: ' . $this->sessionId);
                return true;
            }
            
            $this->logger->error('Authentication failed: No session ID in response headers');
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error('Authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new user in BioStar 2
     * 
     * @param array $userData User information
     * @return array|null Created user data or null on failure
     */
    public function createUser(array $userData)
    {
        $endpoint = '/users';
        
        $payload = [
            'User' => [
                'user_id' => $userData['member_id'],
                'name' => trim($userData['first_name'] . ' ' . $userData['last_name']),
                'disabled' => false,
                'start_datetime' => '2001-01-01T00:00:00.00Z',
                'expiry_datetime' => '2030-12-31T23:59:00.00Z'
            ]
        ];

        // Add optional fields
        if (!empty($userData['email'])) {
            $payload['User']['email'] = $userData['email'];
        }
        if (!empty($userData['phone'])) {
            $payload['User']['phone'] = $userData['phone'];
        }

        // Add user group if configured (must be object with id field)
        if (!empty($this->config['default_user_group_id'])) {
            $payload['User']['user_group_id'] = [
                'id' => (int)$this->config['default_user_group_id']
            ];
        }

        // Add access groups if configured
        if (!empty($this->config['access_groups'])) {
            $payload['User']['access_groups'] = $this->config['access_groups'];
        }

        // Add face image if provided
        if (!empty($userData['photo_base64'])) {
            $payload['User']['credentials'] = [
                'faces' => [
                    [
                        'raw_image' => $userData['photo_base64']
                    ]
                ]
            ];
        }

        try {
            $this->logger->info("Creating user: {$userData['member_id']} - {$payload['User']['name']}");
            
            $response = $this->request('POST', $endpoint, $payload);
            
            if (isset($response['User'])) {
                $this->logger->info("User created successfully with ID: {$response['User']['id']}");
                return $response['User'];
            }
            
            $this->logger->error('User creation failed: Invalid response');
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('User creation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Enroll visual face for a user
     * 
     * @param string $userId BioStar user ID
     * @param string $imageBase64 Base64-encoded face image
     * @return bool Enrollment success status
     */
    public function enrollVisualFace($userId, $imageBase64)
    {
        $endpoint = '/users/' . $userId . '/face';
        
        $payload = [
            'face_data' => $imageBase64,
            'face_level' => $this->config['face_settings']['face_level'] ?? 'NORMAL'
        ];

        try {
            $this->logger->info("Enrolling visual face for user ID: {$userId}");
            
            $response = $this->request('POST', $endpoint, $payload);
            
            if (isset($response['FaceData'])) {
                $this->logger->info("Visual face enrolled successfully for user ID: {$userId}");
                return true;
            }
            
            $this->logger->warning("Face enrollment response unexpected for user ID: {$userId}");
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error("Face enrollment error for user ID {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Register client with face image
     * 
     * @param array $clientData Client data
     * @param string $imageBase64 Base64-encoded face image
     * @return bool Registration success status
     */
    public function registerClientWithFace(array $clientData, $imageBase64)
    {
        // Create user
        $user = $this->createUser($clientData);
        
        if (!$user) {
            return false;
        }

        // Enroll face
        if (!empty($imageBase64)) {
            $faceResult = $this->enrollVisualFace($user['id'], $imageBase64);
            
            if (!$faceResult) {
                $this->logger->warning("User created but face enrollment failed for: {$clientData['member_id']}");
            }
        } else {
            $this->logger->warning("No face image provided for user: {$clientData['member_id']}");
        }

        return true;
    }

    /**
     * Make HTTP request to BioStar 2 API (with header capture)
     * 
     * This method captures response headers, particularly the bs-session-id
     * returned by the /api/login endpoint
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Request payload
     * @param bool $requireAuth Whether authentication is required
     * @return array Response with 'body' and 'headers' keys
     * @throws \Exception On request failure
     */
    private function requestWithHeaders($method, $endpoint, array $data = [], $requireAuth = true)
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        // Add session ID if authenticated
        // All requests after login must include: bs-session-id: <session_id>
        if ($requireAuth && $this->sessionId) {
            $headers[] = 'bs-session-id: ' . $this->sessionId;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        // SSL verification (configurable)
        $sslVerify = $this->config['ssl_verify'] ?? true;
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

        // Capture response headers
        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) {
                return $len;
            }
            $key = strtolower(trim($header[0]));
            $value = trim($header[1]);
            $responseHeaders[$key] = $value;
            return $len;
        });

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            throw new \Exception("cURL error: {$error}");
        }

        if ($httpCode >= 400) {
            $errorMsg = "HTTP {$httpCode}: " . $response;
            throw new \Exception($errorMsg);
        }

        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return [
            'body' => $decoded,
            'headers' => $responseHeaders
        ];
    }

    /**
     * Make HTTP request to BioStar 2 API (standard - body only)
     * 
     * This is used for all authenticated requests after login.
     * The session ID obtained during login is automatically included as a header.
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Request payload
     * @param bool $requireAuth Whether authentication is required
     * @return array Response data
     * @throws \Exception On request failure
     */
    private function request($method, $endpoint, array $data = [], $requireAuth = true)
    {
        $response = $this->requestWithHeaders($method, $endpoint, $data, $requireAuth);
        return $response['body'];
    }

    /**
     * Logout from BioStar 2 API
     */
    public function logout()
    {
        if (!$this->sessionId) {
            return;
        }

        try {
            $this->request('POST', '/logout');
            $this->logger->info('Logged out from BioStar 2');
        } catch (\Exception $e) {
            $this->logger->warning('Logout error: ' . $e->getMessage());
        }

        $this->sessionId = null;
    }
}
