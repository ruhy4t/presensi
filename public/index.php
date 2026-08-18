<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');

// Validasi Security Dasar
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config/database.php';

// Simple Router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Hapus prefix folder jika ada (misal /web_app/public)
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$route = str_replace($scriptName, '', $uri);

// Default Route
if ($route == '/' || $route == '') {
    require __DIR__ . '/../src/Controllers/PublicController.php';
    $controller = new PublicController();
    $controller->index();
    exit;
}

// Router Switch
switch ($route) {
    case '/login':
        require __DIR__ . '/../src/Controllers/AuthController.php';
        break;
    case '/logout':
        require __DIR__ . '/../src/Controllers/AuthController.php'; // Handle logout action
        break;
    case '/dashboard':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require __DIR__ . '/../src/Controllers/KegiatanController.php';
            $controller = new KegiatanController();
            $action = $_POST['action'] ?? '';
            if ($action === 'edit_kegiatan') {
                $controller->update();
            } elseif ($action === 'update_status') {
                $controller->updateStatus();
            } else {
                $controller->store();
            }
        } else {
            require __DIR__ . '/../src/Views/dashboard.php';
        }
        break;
    case '/attendance':
        require __DIR__ . '/../src/Controllers/AttendanceController.php';
        $controller = new AttendanceController();
        if (isset($_GET['token'])) {
            $controller->showByToken($_GET['token']);
        } elseif (isset($_GET['id'])) {
            $controller->show($_GET['id']);
        } else {
            echo "ID Kegiatan Diperlukan.";
        }
        break;
    case '/attendance/store':
        require __DIR__ . '/../src/Controllers/AttendanceController.php';
        $controller = new AttendanceController();
        $controller->store();
        break;
    case '/attendance/prefill':
        require __DIR__ . '/../src/Controllers/AttendanceController.php';
        $controller = new AttendanceController();
        $controller->prefill();
        break;
    case '/attendance/biodata-prefill':
        require __DIR__ . '/../src/Controllers/AttendanceController.php';
        $controller = new AttendanceController();
        $controller->prefillBiodataByNik();
        break;
    case '/participants':
        require __DIR__ . '/../src/Controllers/ParticipantController.php';
        $controller = new ParticipantController();
        $controller->index();
        break;
    case '/users':
        require __DIR__ . '/../src/Views/users.php';
        break;
    case '/reports':
        require __DIR__ . '/../src/Controllers/ReportController.php';
        $controller = new ReportController();
        $controller->index();
        break;
    case '/registrations':
        require __DIR__ . '/../src/Controllers/RegistrationController.php';
        $controller = new RegistrationController();
        if (isset($_GET['id'])) {
            $controller->index($_GET['id']);
        } else {
            echo "ID Kegiatan Diperlukan.";
        }
        break;
    case '/registrations/action':
        require __DIR__ . '/../src/Controllers/RegistrationController.php';
        $controller = new RegistrationController();
        $controller->handleAction();
        break;
    case '/biodata/print':
        require __DIR__ . '/../src/Controllers/RegistrationController.php';
        $controller = new RegistrationController();
        if (isset($_GET['id'])) {
            $controller->print($_GET['id']);
        } else {
            echo "ID Biodata Diperlukan.";
        }
        break;
    case '/report/print':
        require __DIR__ . '/../src/Controllers/ReportController.php';
        $controller = new ReportController();
        if (isset($_GET['id'])) {
            $controller->print($_GET['id']);
        } else {
            echo "ID Kegiatan Diperlukan.";
        }
        break;
    case '/report/export':
        require __DIR__ . '/../src/Controllers/ReportController.php';
        $controller = new ReportController();
        if (isset($_GET['id'])) {
            $controller->export($_GET['id']);
        } else {
            echo "ID Kegiatan Diperlukan.";
        }
        break;
    // ... Add more routes
    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}
