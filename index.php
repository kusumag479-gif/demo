<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config.php';

$method = $_SERVER["REQUEST_METHOD"];
$route = $_GET["route"] ?? "";
$input = json_decode(file_get_contents("php://input"), true) ?? [];

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function getUserId($pdo) {
    $headers = getallheaders();
    $auth = $headers["Authorization"] ?? $headers["authorization"] ?? "";

    if (!preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
        respond(["error" => "Login required"], 401);
    }

    $token = $matches[1];
    $stmt = $pdo->prepare(
        "SELECT user_id FROM api_tokens WHERE token_hash = ?"
    );
    $stmt->execute([hash("sha256", $token)]);
    $user = $stmt->fetch();

    if (!$user) {
        respond(["error" => "Invalid token"], 401);
    }

    return (int)$user["user_id"];
}

try {
    // Health check
    if ($route === "health" && $method === "GET") {
        respond(["success" => true, "message" => "LifeFlow API is running"]);
    }

    // Register donor
    if ($route === "register" && $method === "POST") {
        $name = trim($input["name"] ?? "");
        $email = trim($input["email"] ?? "");
        $phone = trim($input["phone"] ?? "");
        $password = $input["password"] ?? "";
        $bloodGroup = trim($input["blood_group"] ?? "");
        $city = trim($input["city"] ?? "");

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !$phone || strlen($password) < 8 || !$bloodGroup) {
            respond(["error" => "Please provide valid registration details. Password must be at least 8 characters."], 400);
        }

        $allowedGroups = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];
        if (!in_array($bloodGroup, $allowedGroups, true)) {
            respond(["error" => "Invalid blood group"], 400);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, phone, password_hash, blood_group, city)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        try {
            $stmt->execute([
                $name,
                $email,
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                $bloodGroup,
                $city
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                respond(["error" => "Email already registered"], 409);
            }
            throw $e;
        }

        respond(["success" => true, "message" => "Registration successful"], 201);
    }

    // Login
    if ($route === "login" && $method === "POST") {
        $email = trim($input["email"] ?? "");
        $password = $input["password"] ?? "";

        $stmt = $pdo->prepare(
            "SELECT id, name, email, password_hash FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user["password_hash"])) {
            respond(["error" => "Invalid email or password"], 401);
        }

        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare(
            "INSERT INTO api_tokens (user_id, token_hash) VALUES (?, ?)"
        );
        $stmt->execute([
            $user["id"],
            hash("sha256", $token)
        ]);

        respond([
            "success" => true,
            "token" => $token,
            "user" => [
                "id" => $user["id"],
                "name" => $user["name"],
                "email" => $user["email"]
            ]
        ]);
    }

    // Blood stock
    if ($route === "stock" && $method === "GET") {
        $stmt = $pdo->query(
            "SELECT blood_group, SUM(units) AS units
             FROM bank_stock
             GROUP BY blood_group
             ORDER BY blood_group"
        );
        respond($stmt->fetchAll());
    }

    // Blood banks
    if ($route === "banks" && $method === "GET") {
        $stmt = $pdo->query(
            "SELECT id, name, address, city, phone FROM banks ORDER BY name"
        );
        respond($stmt->fetchAll());
    }

    // Emergency requests
    if ($route === "emergency" && $method === "GET") {
        $stmt = $pdo->query(
            "SELECT id, patient_name, blood_group, units_needed,
                    hospital, city, contact, status, created_at
             FROM emergency_requests
             ORDER BY created_at DESC"
        );
        respond($stmt->fetchAll());
    }

    // Create emergency request
    if ($route === "emergency" && $method === "POST") {
        $patient = trim($input["patient_name"] ?? "");
        $bloodGroup = trim($input["blood_group"] ?? "");
        $units = (int)($input["units_needed"] ?? 0);
        $hospital = trim($input["hospital"] ?? "");
        $city = trim($input["city"] ?? "");
        $contact = trim($input["contact"] ?? "");

        $allowedGroups = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];

        if (!$patient || !in_array($bloodGroup, $allowedGroups, true) ||
            $units < 1 || !$hospital || !$city || !$contact) {
            respond(["error" => "Please provide all valid request details"], 400);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO emergency_requests
             (patient_name, blood_group, units_needed, hospital, city, contact)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$patient, $bloodGroup, $units, $hospital, $city, $contact]);

        respond([
            "success" => true,
            "message" => "Emergency request submitted",
            "id" => (int)$pdo->lastInsertId()
        ], 201);
    }

    // Create donation appointment
    if ($route === "appointments" && $method === "POST") {
        $userId = getUserId($pdo);
        $bankId = (int)($input["bank_id"] ?? 0);
        $date = $input["appointment_date"] ?? "";
        $time = $input["appointment_time"] ?? "";

        $dateObj = DateTime::createFromFormat("!Y-m-d", $date);
        $timeObj = DateTime::createFromFormat("!H:i", $time);

        if (!$bankId || !$dateObj || $dateObj->format("Y-m-d") !== $date ||
            !$timeObj || $timeObj->format("H:i") !== $time ||
            $date < date("Y-m-d")) {
            respond(["error" => "Please provide a valid future appointment date, time and bank"], 400);
        }

        $stmt = $pdo->prepare("SELECT id FROM banks WHERE id = ?");
        $stmt->execute([$bankId]);
        if (!$stmt->fetch()) {
            respond(["error" => "Blood bank not found"], 404);
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO appointments
                 (user_id, bank_id, appointment_date, appointment_time)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $bankId, $date, $time]);
        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                respond(["error" => "This appointment slot is already booked"], 409);
            }
            throw $e;
        }

        respond([
            "success" => true,
            "message" => "Appointment booked",
            "id" => (int)$pdo->lastInsertId()
        ], 201);
    }

    respond(["error" => "Route not found"], 404);

} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(["error" => "Server error. Check the PHP error log."], 500);
}
