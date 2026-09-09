<?php

/** Calcola una geometria stradale OSRM per una sequenza di fermate. */
class RoadSnapper
{
    public static function snap(array $path): array
    {
        $coordinates = [];
        foreach ($path as $point) {
            $lat = (float) ($point['lat'] ?? 0);
            $lng = (float) ($point['lng'] ?? 0);
            if ($lat !== 0.0 && $lng !== 0.0) $coordinates[] = $lng . ',' . $lat;
        }
        if (count($coordinates) < 2) return [];

        $url = 'https://router.project-osrm.org/route/v1/driving/' . implode(';', $coordinates)
            . '?overview=full&geometries=geojson&steps=false';
        $context = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => 8, 'ignore_errors' => true,
            'header' => "User-Agent: ACTV-Live/1.0\r\nAccept: application/json\r\n",
        ]]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) return [];
        $data = json_decode($raw, true);
        $route = $data['routes'][0]['geometry']['coordinates'] ?? [];
        if (!is_array($route)) return [];

        $result = [];
        foreach ($route as $coordinate) {
            if (is_array($coordinate) && count($coordinate) >= 2) {
                $result[] = ['lat' => (float) $coordinate[1], 'lng' => (float) $coordinate[0]];
            }
        }
        return count($result) > 1 ? $result : [];
    }
}
