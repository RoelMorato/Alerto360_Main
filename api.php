<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require 'db_connect.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get the request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// Remove query string and base path
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/api.php', '', $path);
$path = trim($path, '/');

// Get JSON input for POST/PUT requests
$input = json_decode(file_get_contents('php://input'), true);

// Helper function to send JSON response
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Helper function to get user from token (simplified - in production use JWT)
function getUserFromToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        // In production, validate JWT token here
        // For now, assume token is user_id:session_id format
        $parts = explode(':', $token);
        if (count($parts) === 2) {
            $user_id = intval($parts[0]);
            $session_id = $parts[1];

            // Verify session exists
            global $pdo;
            $stmt = $pdo->prepare("SELECT users.* FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                return $user;
            }
        }
    }
    return null;
}

// API Routes
switch ($path) {
    case 'login':
        if ($method !== 'POST') {
            sendResponse(['error' => 'Method not allowed'], 405);
        }

        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (!$email || !$password) {
            sendResponse(['error' => 'Email and password required'], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            sendResponse(['error' => 'Invalid credentials'], 401);
        }

        // Create session
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        // Return user data with token (simplified)
        $token = $user['id'] . ':' . session_id();
        sendResponse([
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'responder_type' => $user['responder_type'] ?? null
            ],
            'token' => $token
        ]);
        break;

    case 'register':
        if ($method !== 'POST') {
            sendResponse(['error' => 'Method not allowed'], 405);
        }

        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (!$name || !$email || !$password) {
            sendResponse(['error' => 'All fields required'], 400);
        }

        // Check if email exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            sendResponse(['error' => 'Email already exists'], 409);
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
        if ($insert->execute([$name, $email, $hashed])) {
            $user_id = $pdo->lastInsertId();
            sendResponse(['message' => 'User registered successfully', 'user_id' => $user_id], 201);
        } else {
            sendResponse(['error' => 'Registration failed'], 500);
        }
        break;

    case 'incidents':
        $user = getUserFromToken();
        if (!$user) {
            sendResponse(['error' => 'Unauthorized'], 401);
        }

        if ($method === 'GET') {
            // Get incidents based on user role
            if ($user['role'] === 'admin') {
                $stmt = $pdo->query("SELECT incidents.*, users.name AS reporter, responder_users.name AS responder_name FROM incidents JOIN users ON incidents.user_id = users.id LEFT JOIN users AS responder_users ON incidents.accepted_by = responder_users.id ORDER BY incidents.created_at DESC");
            } elseif ($user['role'] === 'responder') {
                $stmt = $pdo->query("SELECT incidents.*, users.name AS reporter FROM incidents JOIN users ON incidents.user_id = users.id ORDER BY incidents.created_at DESC");
            } else {
                // Regular user - only their own incidents
                $stmt = $pdo->prepare("SELECT incidents.*, users.name AS reporter FROM incidents JOIN users ON incidents.user_id = users.id WHERE incidents.user_id = ? ORDER BY incidents.created_at DESC");
                $stmt->execute([$user['id']]);
            }

            $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendResponse(['incidents' => $incidents]);

        } elseif ($method === 'POST') {
            // Create new incident
            $type = $input['type'] ?? '';
            $description = $input['description'] ?? '';
            $latitude = $input['latitude'] ?? null;
            $longitude = $input['longitude'] ?? null;
            $image_path = $input['image_path'] ?? null;

            if (!$type || !$description) {
                sendResponse(['error' => 'Type and description required'], 400);
            }

            $insert = $pdo->prepare("INSERT INTO incidents (user_id, type, description, latitude, longitude, image_path, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            if ($insert->execute([$user['id'], $type, $description, $latitude, $longitude, $image_path])) {
                $incident_id = $pdo->lastInsertId();
                sendResponse(['message' => 'Incident reported successfully', 'incident_id' => $incident_id], 201);
            } else {
                sendResponse(['error' => 'Failed to report incident'], 500);
            }
        }
        break;

    case (preg_match('/incidents\/(\d+)/', $path, $matches) ? true : false):
        $incident_id = $matches[1];
        $user = getUserFromToken();
        if (!$user) {
            sendResponse(['error' => 'Unauthorized'], 401);
        }

        if ($method === 'GET') {
            // Get specific incident
            $stmt = $pdo->prepare("SELECT incidents.*, users.name AS reporter FROM incidents JOIN users ON incidents.user_id = users.id WHERE incidents.id = ?");
            $stmt->execute([$incident_id]);
            $incident = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$incident) {
                sendResponse(['error' => 'Incident not found'], 404);
            }

            // Check permissions
            if ($user['role'] !== 'admin' && $incident['user_id'] != $user['id']) {
                sendResponse(['error' => 'Access denied'], 403);
            }

            sendResponse(['incident' => $incident]);

        } elseif ($method === 'PUT') {
            // Update incident (for responders/admins)
            if ($user['role'] !== 'responder' && $user['role'] !== 'admin') {
                sendResponse(['error' => 'Access denied'], 403);
            }

            $status = $input['status'] ?? '';
            $accepted_by = $input['accepted_by'] ?? null;

            if ($status) {
                $update_fields = "status = ?";
                $update_values = [$status];

                if ($accepted_by) {
                    $update_fields .= ", accepted_by = ?";
                    $update_values[] = $accepted_by;
                }

                $update_stmt = $pdo->prepare("UPDATE incidents SET $update_fields WHERE id = ?");
                $update_values[] = $incident_id;

                if ($update_stmt->execute($update_values)) {
                    sendResponse(['message' => 'Incident updated successfully']);
                } else {
                    sendResponse(['error' => 'Failed to update incident'], 500);
                }
            } else {
                sendResponse(['error' => 'Status required'], 400);
            }
        }
        break;

    case 'responders':
        $user = getUserFromToken();
        if (!$user || $user['role'] !== 'admin') {
            sendResponse(['error' => 'Unauthorized'], 401);
        }

        if ($method === 'GET') {
            $responders = $pdo->query("SELECT id, name, email, responder_type, created_at FROM users WHERE role = 'responder' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            sendResponse(['responders' => $responders]);
        }
        break;

    default:
        sendResponse(['error' => 'Endpoint not found'], 404);
}
?>