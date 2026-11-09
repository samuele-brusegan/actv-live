<?php
/*
 * Copyright (c) 2025. Brusegan Samuele, Davanzo Andrea
 * Questo file fa parte di actv-live ed è rilasciato
 * sotto la licenza MIT. Vedere il file LICENSE per i dettagli.
 */


class Router {
    private array $routes = [];

    // Aggiunge una nuova rotta
    public function add($url, $controller, $action, $params=""): void {
        $this->routes[$url] = [
            'controller' => $controller,
            'action' => $action,
            'params' => $params,
        ];
    }

    // Smista la richiesta all'azione corretta
    public function dispatch($url): void {
        $path = rtrim(parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            $path = '/';
        }

        if (array_key_exists($path, $this->routes)) {
            $route = $this->routes[$path];
            $controllerName = $route['controller'];
            $actionName = $route['action'];
			$params = $route['params'];

            // Crea il controller e chiama l'azione
            $controller = new $controllerName();
            $controller->$actionName($params);
        } else {
            // Gestione errore 404
            http_response_code(404);
            $notFound = defined('BASE_PATH') ? BASE_PATH . '/app/views/404.php' : null;
            if ($notFound && is_file($notFound)) {
                require $notFound;
            } else {
                echo "<pre>Pagina non trovata!</pre>";
            }
        }
    }

    public function list() : array {
        return $this->routes;
    }
}
