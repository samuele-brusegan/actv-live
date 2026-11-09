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

    function adminDatabaseHealth() {
        AdminAuth::requireAuth();
        require_once BASE_PATH . '/app/views/admin/databaseHealth.php';
    }

    function adminGtfsUpdate() {
        AdminAuth::requireAuth();
        $csrf = AdminAuth::csrfToken();
        require_once BASE_PATH . '/app/views/admin/gtfsUpdate.php';
    }

    function adminGtfsRealtimeInspector() {
        AdminAuth::requireAuth();
        require_once BASE_PATH . '/app/views/admin/gtfsRtInspector.php';
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
        AdminAuth::requireAuth();
        global $router;
        $routes = $router -> list();
        require_once BASE_PATH . '/app/views/routes.php';
    }

    function lineSchedule() {
        require_once BASE_PATH . '/app/views/lineSchedule.php';
    }

    function tripFinder() {
        require_once BASE_PATH . '/app/views/tripFinder.php';
    }
    
    function deleteCookie() {
        require_once BASE_PATH . '/app/views/deleteCookie.php';
    }

    function feedback() {
        require_once BASE_PATH . '/app/views/feedback.php';
    }

    function credits() {
        require_once BASE_PATH . '/app/views/credits.php';
    }

    function homeAlt() {
        require_once BASE_PATH . '/app/views/homeAlt.php';
    }

    private function siteBaseUrl(): string {
        if (URL_PATH !== '') {
            return URL_PATH;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? '') === 'https') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    function robotsTxt() {
        header('Content-Type: text/plain; charset=utf-8');
        $base = $this->siteBaseUrl();
        echo "User-agent: *\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /api\n";
        echo "Disallow: /widget\n\n";
        echo "Sitemap: {$base}/sitemap.xml\n";
    }

    function i18nJs() {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo 'window.ACTV_LANG = ' . json_encode(I18n::lang()) . ";\n";
        echo 'window.ACTV_T = ' . json_encode(I18n::all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
    }

    function sitemap() {
        header('Content-Type: application/xml; charset=utf-8');
        $base = $this->siteBaseUrl();

        $urls = [
            ['loc' => '/', 'priority' => '1.0'],
            ['loc' => '/stopList', 'priority' => '0.9'],
            ['loc' => '/route-finder', 'priority' => '0.8'],
            ['loc' => '/live-map', 'priority' => '0.8'],
            ['loc' => '/lines-map', 'priority' => '0.7'],
            ['loc' => '/line-schedule', 'priority' => '0.7'],
            ['loc' => '/trip-finder', 'priority' => '0.5'],
            ['loc' => '/delay-stats', 'priority' => '0.4'],
            ['loc' => '/feedback', 'priority' => '0.3'],
            ['loc' => '/credits', 'priority' => '0.2'],
            ['loc' => '/privacy', 'priority' => '0.2'],
        ];

        // Tutte le fermate dal feed GTFS
        $stopsFile = BASE_PATH . '/data/gtfs/stops.txt';
        if (is_file($stopsFile)) {
            $fh = fopen($stopsFile, 'r');
            $header = fgetcsv($fh);
            if ($header) {
                $iId = array_search('stop_id', $header);
                $iName = array_search('stop_name', $header);
                while (($row = fgetcsv($fh)) !== false) {
                    $id = $row[$iId] ?? '';
                    $name = $row[$iName] ?? '';
                    if ($id === '' || $name === '') continue;
                    $urls[] = [
                        'loc' => '/aut/stops/stop?id=' . rawurlencode($id) . '&name=' . rawurlencode($name),
                        'priority' => '0.6',
                    ];
                }
            }
            fclose($fh);
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo "  <url><loc>" . htmlspecialchars($base . $u['loc'], ENT_XML1, 'UTF-8')
                . "</loc><changefreq>weekly</changefreq><priority>{$u['priority']}</priority></url>\n";
        }
        echo '</urlset>';
    }

    function adminFeedback() {
        AdminAuth::requireAuth();
        if (!class_exists('databaseConnector')) require_once BASE_PATH . '/app/models/databaseConnector.php';
        $db = databaseConnector::getInstance();
        $db->connect(ENV['DB_USER'], ENV['DB_PASS'], ENV['DB_HOST'], ENV['DB_NAME']);
        $db->query("CREATE TABLE IF NOT EXISTS feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(32) NOT NULL,
            priority VARCHAR(16) NOT NULL DEFAULT 'normal',
            status VARCHAR(16) NOT NULL DEFAULT 'new',
            name VARCHAR(120) NULL,
            email VARCHAR(190) NULL,
            subject VARCHAR(180) NULL,
            message TEXT NOT NULL,
            ip_hash CHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_feedback_status (status),
            INDEX idx_feedback_category (category),
            INDEX idx_feedback_created (created_at)
        )");
        $category = $_GET['category'] ?? '';
        $status = $_GET['status'] ?? '';
        $where = [];
        $params = [];
        if (in_array($category, ['feature', 'bug', 'improvement', 'question', 'other'], true)) { $where[] = 'category = ?'; $params[] = $category; }
        if (in_array($status, ['new', 'in_progress', 'resolved', 'archived'], true)) { $where[] = 'status = ?'; $params[] = $status; }
        $sql = 'SELECT * FROM feedback' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC LIMIT 250';
        $feedback = $db->query($sql, $params);
        require_once BASE_PATH . '/app/views/admin/feedback.php';
    }
}
