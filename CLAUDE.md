# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**actv-live** is a web application for Venice's public transport (ACTV). It provides real-time bus/water bus tracking, route planning, and stop information using GTFS data. The app is built with plain PHP (no framework) and vanilla JavaScript.

## Tech Stack

- **Backend**: PHP 8.4+ (plain PHP, no framework)
- **Frontend**: Vanilla JavaScript, no build tool
- **Database**: MySQL (GTFS data stored in tables: `stops`, `routes`, `trips`, `stop_times`, `calendar`, `shapes_refined`, `logs`)
- **Testing**: Pest (PHP) + Jest (JavaScript)
- **Dependencies**: Composer (Pest, symfony/var-dumper), npm (Jest, jest-environment-jsdom)

## Key Commands

```bash
# Install dependencies
composer install
npm install

# Run PHP tests (Pest)
vendor/bin/pest

# Run a single PHP test
vendor/bin/pest tests/Unit/RouterTest.php

# Run JavaScript tests (Jest)
npm test

# Run a single JS test
npx jest --testPathPattern=routeFinder
```

## Architecture

### Request Flow

1. `public/index.php` — entry point, starts session, creates Router
2. `public/routes.php` — defines all routes (URL → controller + action)
3. `public/functions.php` / `public/imports.php` — utility functions and common imports
4. `app/Router.php` — simple URL-keyed router that instantiates controllers and calls actions
5. Controllers load views from `app/views/`

### Directory Structure

```
app/
  controllers/    Controller.php (page rendering), ApiController.php (JSON API endpoints)
  models/         databaseConnector.php (PDO singleton), GTFS models (gtfsStops, gtfsPassages, etc.)
  services/       Logger.php (DB logging), RoutePlanner.php (GTFS-based routing), GTFSParser.php
  views/          PHP templates for each page (home, routeFinder, stopList, admin/*, etc.)
public/
  index.php       Entry point
  routes.php      Route definitions
  functions.php   Global helper functions
  js/             Client-side JS per page (routeFinder.js, liveBusMap.js, etc.)
  components/     Reusable JS components (StopCard.js, StopListItem.js)
tests/
  Unit/           PHP unit tests (Pest)
  Feature/        Feature tests (Pest)
  jest/           JavaScript tests (Jest)
data/gtfs/cache/  Parsed GTFS JSON cache (stops.json, routes.json, stop_times per route)
scripts/          CLI utilities for GTFS processing and DB management
```

### Key Components

- **Router**: Simple array-based router in `app/Router.php`. Routes defined in `public/routes.php`.
- **databaseConnector**: PDO singleton wrapper with prepared statements. `app/models/databaseConnector.php`.
- **RoutePlanner**: GTFS-based route finder (direct + 1 transfer). Uses JSON cache files from `data/gtfs/cache/`, not the database. See `app/services/RoutePlanner.php`.
- **GTFSParser**: Downloads and parses ACTV GTFS zip feed into JSON cache files. See `app/services/GTFSParser.php`.
- **Logger**: Logs PHP/JS errors to the `logs` database table. See `app/services/Logger.php`.
- **ApiController**: All API endpoints return JSON. Handles GTFS queries, route planning, bus positions, and error logging.

### Configuration

- Environment variables in `.env`: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
- Constants defined in `app/bootstrap.php`: `BASE_PATH`, `ENV`, `URL_PATH`
- Session management with expiration check

### CI

GitHub Actions (`.github/workflows/test.yml`) runs on push/PR:
- Jest on Node 20
- Pest on PHP 8.4
