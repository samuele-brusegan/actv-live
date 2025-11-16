<?php

class Controller {
	function index() {
		require_once BASE_PATH . '/app/views/home.php';
	}
	
	function stops() {
        require_once BASE_PATH . '/app/views/stopList.php';
    }
	
	function stop() {
		// $resp = file_get_contents(BASE_PATH."/app/views/4586-4587-web-aut.json");
		require_once BASE_PATH . '/app/views/stop.php';
	}
	
	function favorite(){
		require_once BASE_PATH . '/app/models/addToFavorites.php';
	}
}