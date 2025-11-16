<?php
/*
 * Copyright (c) 2025. Brusegan Samuele, Davanzo Andrea
 * Questo file fa parte di GradeCraft ed è rilasciato
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
            header("HTTP/1.0 404 Not Found");
            echo "<pre>";
            echo "Pagina non trovata!";
            echo $path; echo "<br>";
            echo $url; echo "<br>";
            echo PHP_URL_PATH; echo "<br>";
            echo "</pre>";
            
        }
    }
}
