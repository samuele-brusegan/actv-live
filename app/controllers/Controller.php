<?php

class Controller {
    function index() {
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
        ini_set('error_log', BASE_PATH . '/php_error.log');
        require_once BASE_PATH . '/app/views/home.php';
    }

    function stops() {
        $stations = $this->fetchData('https://oraritemporeale.actv.it/aut/backend/page/stops', 0.5);
        require_once BASE_PATH . '/app/views/stopList.php';
    }

    function stop() {
        // $resp = file_get_contents(BASE_PATH."/app/views/4586-4587-web-aut.json");
        require_once BASE_PATH . '/app/views/stop.php';
    }

    function routeFinder() {
        require_once BASE_PATH . '/app/views/routeFinder.php';
    }

    function stationSelector() {
        require_once BASE_PATH . '/app/views/stationSelector.php';
    }

    function routeResults() {
        require_once BASE_PATH . '/app/views/routeResults.php';
    }

    function routeDetails() {
        require_once BASE_PATH . '/app/views/routeDetails.php';
    }

    private function fetchData($url, $timeout = 10) {
        $ch = curl_init($url);
        $timeoutMs = max(1, (int) round($timeout * 1000));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $timeoutMs);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
        $response = curl_exec($ch);
        if ($response === false || curl_errno($ch)) {
            Logger::log('PHP_ERROR', 'fetchData cURL error: ' . curl_error($ch), $url);
            curl_close($ch);
            return [];
        }
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }







    function linesMap() {
        require_once BASE_PATH . '/app/views/linesMap.php';
    }



    function tripDetails() {
        require_once BASE_PATH . '/app/views/tripDetails.php';
    }

    function adminLogin() {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!AdminAuth::verifyCsrf($_POST['csrf'] ?? null)) {
                $error = 'Sessione scaduta, riprova.';
            } elseif (AdminAuth::attempt($_POST['password'] ?? '')) {
                header('Location: ' . URL_PATH . '/admin/dashboard');
                exit;
            } else {
                $error = 'Password non valida.';
            }
        }

        $csrf = AdminAuth::csrfToken();
        require_once BASE_PATH . '/app/views/admin/login.php';
    }

    function adminLogout() {
        AdminAuth::logout();
        header('Location: ' . URL_PATH . '/admin/login');
        exit;
    }

    function logs() {
        AdminAuth::requireAuth();
        if (!class_exists('databaseConnector')) {
            require_once BASE_PATH . '/app/models/databaseConnector.php';
        }
        $db = databaseConnector::getInstance();
        $db->connect(ENV['DB_USER'], ENV['DB_PASS'], ENV['DB_HOST'], ENV['DB_NAME']);
        
        $type = $_GET['type'] ?? null;
        $query = "SELECT * FROM logs";
        $params = [];
        if ($type) {
            $query .= " WHERE type = ?";
            $params[] = $type;
        }
        $query .= " ORDER BY created_at DESC LIMIT 100";
        $logs = $db->query($query, $params);
        
        require_once BASE_PATH . '/app/views/admin/logs.php';
    }

    function adminDashboard() {
        AdminAuth::requireAuth();
        require_once BASE_PATH . '/app/views/admin/dashboard.php';
    }

    function adminGtfsUpdate() {
        AdminAuth::requireAuth();
        $csrf = AdminAuth::csrfToken();
        require_once BASE_PATH . '/app/views/admin/gtfsUpdate.php';
    }



    function liveMap() {
        require_once BASE_PATH . '/app/views/liveBusMap.php';
    }

    function widget() {
        require_once BASE_PATH . '/app/views/widget.php';
    }

    function delayStats() {
        require_once BASE_PATH . '/app/views/delayStats.php';
    }

    function routes() {
        global $router;
        $routes = $router -> list();
        require_once BASE_PATH . '/app/views/routes.php';
    }

    function lineSchedule() {
        require_once BASE_PATH . '/app/views/lineSchedule.php';
    }
}
