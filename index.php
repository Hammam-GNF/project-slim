<?php

use Medoo\Medoo;
use Slim\App;
use Slim\Views\Twig;
use Slim\Flash\Messages;
use Slim\Http\Uri;
use Slim\Http\Environment;

session_start();

require __DIR__ . '/vendor/autoload.php';

$app = new App([
    'settings' => [
        'displayErrorDetails' => true,
    ],
]);

$container = $app->getContainer();

// Twig View Setup
$container['view'] = function ($container) {
    $view = new Twig(__DIR__ . '/views', ['cache' => false]);

    $router = $container->get('router');
    $uri = Uri::createFromEnvironment(new Environment($_SERVER));
    $view->addExtension(new \Slim\Views\TwigExtension($router, $uri));

    return $view;
};

// Flash Messages
$container['flash'] = function () {
    return new Messages();
};

// Database Connection
$container['db'] = function () {
    return new Medoo([
        'database_type' => 'mysql',
        'database_name' => 'school_system',
        'server' => 'localhost',
        'username' => 'root',
        'password' => 'root',
        'charset' => 'utf8',
    ]);
};

// Middleware untuk Autentikasi
$authMiddleware = function ($request, $response, $next) {
    if (!isset($_SESSION['user'])) {
        $this->flash->addMessage('error', 'Anda harus login terlebih dahulu.');
        return $response->withRedirect('/');
    }

    return $next($request, $response);
};

// Route Login
$app->get('/', function ($request, $response) {
    if (isset($_SESSION['user'])) {
        return $response->withRedirect('/dashboard');
    }

    $errorMessages = $this->flash->getMessages();
    return $this->view->render($response, 'login.twig', ['errors' => $errorMessages]);
});

$app->post('/login', function ($request, $response) {
    $data = $request->getParsedBody();
    $username = $data['username'];
    $password = $data['password'];

    $user = $this->db->get('tbl_admins', '*', ['username' => $username]);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        return $response->withRedirect('/dashboard');
    }

    $this->flash->addMessage('error', 'Username atau password salah.');
    return $response->withRedirect('/');
})->setName('login');

// Route Dashboard
$app->get('/dashboard', function ($request, $response) {
    $user = $_SESSION['user'];

    $totalUsers = $this->db->count('tbl_admins');
    $totalSchools = $this->db->count('tbl_school');
    $totalStudents = $this->db->count('tbl_students');

    $studentsPerSchool = $this->db->query("
        SELECT tbl_school.school_name, COUNT(tbl_students.id) AS student_count
        FROM tbl_students
        JOIN tbl_school ON tbl_students.id_school = tbl_school.id
        GROUP BY tbl_school.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    return $this->view->render($response, 'dashboard.twig', [
        'user' => $user,
        'total_users' => $totalUsers,
        'total_schools' => $totalSchools,
        'total_students' => $totalStudents,
        'students_per_school' => $studentsPerSchool,
    ]);
})->add($authMiddleware);

// Route User Management
$app->get('/users', function ($request, $response) {
    $users = $this->db->select('tbl_admins', '*');
    return $this->view->render($response, 'user/index.twig', ['users' => $users]);
})->add($authMiddleware);

// Route School Management
$app->get('/school', function ($request, $response) {
    $schools = $this->db->select('tbl_school', '*');
    return $this->view->render($response, 'school/index.twig', ['schools' => $schools]);
})->add($authMiddleware);

// Route Students Management
$app->get('/students', function ($request, $response) {
    $students = $this->db->query("
        SELECT students.id, students.name, students.email, school.school_name AS school_name
        FROM tbl_students AS students
        JOIN tbl_school AS school ON students.id_school = school.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $schools = $this->db->select('tbl_school', ['id', 'school_name']);
    return $this->view->render($response, 'student/index.twig', [
        'students' => $students,
        'schools' => $schools,
    ]);
})->add($authMiddleware);

// Logout Route
$app->get('/logout', function ($request, $response) {
    session_destroy();
    $this->flash->addMessage('success', 'Anda berhasil logout.');
    return $response->withRedirect('/');
});

// Jalankan Aplikasi
$app->run();
