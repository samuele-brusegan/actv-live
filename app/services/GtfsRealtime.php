<?php

/** Minimal GTFS-RT protobuf decoder for ACTV vehicle positions and trip updates. */
class GtfsRealtime
{
    private const URLS = [
        'navigation' => ['vehicles' => 'https://tpl.actv.it/nav/GTFSRT/vehiclePositions', 'updates' => 'https://tpl.actv.it/nav/GTFSRT/tripUpdates'],
        'automobilistico' => ['vehicles' => 'https://tpl.actv.it/aut/GTFSRT/vehiclePositions', 'updates' => 'https://tpl.actv.it/aut/GTFSRT/tripUpdates'],
    ];

    public static function read(string $kind, string $service = 'navigation'): array
    {
        $cacheDir = BASE_PATH . '/data/gtfs/realtime/' . $service;
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
        $cacheFile = $cacheDir . '/' . $kind . '.json';
        try {
            $raw = self::download(self::URLS[$service][$kind]);
            $decoded = $kind === 'vehicles' ? self::vehicles($raw, $service) : self::updates($raw, $service);
            // La cache è opzionale: il feed live deve funzionare anche quando
            // il volume è read-only o appartiene a un altro utente.
            @file_put_contents($cacheFile, json_encode(['updated_at' => date(DATE_ATOM), 'data' => $decoded], JSON_PRETTY_PRINT), LOCK_EX);
            return $decoded;
        } catch (Throwable $e) {
            $cached = is_file($cacheFile) ? json_decode((string)file_get_contents($cacheFile), true) : null;
            return is_array($cached['data'] ?? null) ? $cached['data'] : [];
        }
    }

    public static function decode(string $kind, string $raw, string $service = 'navigation'): array
    {
        return $kind === 'vehicles' ? self::vehicles($raw, $service) : self::updates($raw, $service);
    }

    /**
     * Scarica un feed senza usare la cache e restituisce il payload protobuf
     * insieme alla sua rappresentazione decodificata per l'inspector admin.
     */
    public static function inspect(string $kind, string $service = 'navigation'): array
    {
        if (!isset(self::URLS[$service][$kind])) {
            throw new InvalidArgumentException('Feed GTFS-RT non valido');
        }

        $raw = self::download(self::URLS[$service][$kind]);
        return [
            'service' => $service,
            'kind' => $kind,
            'url' => self::URLS[$service][$kind],
            'fetched_at' => date(DATE_ATOM),
            'bytes' => strlen($raw),
            'sha256' => hash('sha256', $raw),
            'raw_base64' => base64_encode($raw),
            'raw_hex_preview' => strtoupper(implode(' ', str_split(bin2hex(substr($raw, 0, 256)), 2))),
            'decoded' => self::decode($kind, $raw, $service),
        ];
    }

    private static function download(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $status >= 400 || $body === '') throw new RuntimeException('Feed GTFS-RT non raggiungibile');
        return $body;
    }

    private static function vehicles(string $raw, string $service): array
    {
        $out = [];
        foreach (self::messages(self::fieldMessages($raw, 2)) as $entity) {
            $vehicle = self::fieldMessage($entity, 4);
            if ($vehicle === null) continue;
            $trip = self::fieldMessage($vehicle, 1);
            $pos = self::fieldMessage($vehicle, 2);
            // TripDescriptor: 2=start_time, 5=route_id.
            $route = self::stringField($trip, 5);
            $tripId = self::stringField($trip, 1);
            $relationship = self::varintField($trip, 3);
            $out[] = [
                'service' => $service, 'trip_id' => $tripId, 'route_id' => $route,
                'route_short_name' => $route, 'status' => $relationship === 3 ? 'cancelled' : 'scheduled',
                'delay_seconds' => 0,
                'vehicle_position' => $pos ? ['lat' => self::floatField($pos, 1), 'lon' => self::floatField($pos, 2)] : null,
            ];
        }
        return $out;
    }

    private static function updates(string $raw, string $service): array
    {
        $out = [];
        foreach (self::messages(self::fieldMessages($raw, 2)) as $entity) {
            $update = self::fieldMessage($entity, 3);
            if ($update === null) continue;
            $trip = self::fieldMessage($update, 1);
            $relationship = self::varintField($trip, 3);
            $delay = 0;
            foreach (self::fieldMessages($update, 4) as $stopUpdate) {
                $arrival = self::fieldMessage($stopUpdate, 2);
                $departure = self::fieldMessage($stopUpdate, 3);
                $event = $arrival ?: $departure;
                if ($event !== null && self::varintField($event, 1) !== null) { $delay = self::signedInt32(self::varintField($event, 1)); break; }
            }
            $out[] = ['service' => $service, 'trip_id' => self::stringField($trip, 1), 'route_id' => self::stringField($trip, 5), 'route_short_name' => self::stringField($trip, 5), 'status' => $relationship === 3 ? 'cancelled' : ($delay ? 'delayed' : 'scheduled'), 'delay_seconds' => $delay, 'vehicle_position' => null];
        }
        return $out;
    }

    private static function messages(array $fields): array { return array_values(array_filter(array_map(fn($f) => $f['wire'] === 2 ? $f['value'] : null, $fields), 'is_string')); }
    private static function fieldMessages(string $data, int $wanted): array { return self::fields($data, $wanted); }
    private static function fieldMessage(string $data, int $wanted): ?string { foreach (self::fields($data, $wanted) as $f) if ($f['wire'] === 2) return $f['value']; return null; }
    private static function stringField(?string $data, int $wanted): string { if ($data === null) return ''; foreach (self::fields($data, $wanted) as $f) if ($f['wire'] === 2) return $f['value']; return ''; }
    private static function varintField(?string $data, int $wanted): ?int { if ($data === null) return null; foreach (self::fields($data, $wanted) as $f) if ($f['wire'] === 0) return (int)$f['value']; return null; }
    private static function floatField(string $data, int $wanted): ?float { foreach (self::fields($data, $wanted) as $f) if ($f['wire'] === 5) return unpack('g', $f['value'])[1]; return null; }
    private static function fields(string $data, ?int $wanted = null): array
    {
        $i = 0; $len = strlen($data); $out = [];
        while ($i < $len) {
            $key = 0; $shift = 0; do { if ($i >= $len) break 2; $b = ord($data[$i++]); $key |= ($b & 127) << $shift; $shift += 7; } while ($b & 128);
            $number = $key >> 3; $wire = $key & 7; if ($wanted !== null && $number !== $wanted) { /* still consume */ }
            if ($wire === 0) { $v = 0; $shift = 0; do { $b = ord($data[$i++]); $v |= ($b & 127) << $shift; $shift += 7; } while ($b & 128); $value = $v; }
            elseif ($wire === 2) { $n = self::readVarint($data, $i); $value = substr($data, $i, $n); $i += $n; }
            elseif ($wire === 5) { $value = substr($data, $i, 4); $i += 4; }
            elseif ($wire === 1) { $value = substr($data, $i, 8); $i += 8; }
            else break;
            if ($wanted === null || $number === $wanted) $out[] = ['wire' => $wire, 'value' => $value];
        }
        return $out;
    }
    private static function readVarint(string $data, int &$i): int { $v = 0; $shift = 0; $len = strlen($data); do { if ($i >= $len) return 0; $b = ord($data[$i++]); $v |= ($b & 127) << $shift; $shift += 7; } while ($b & 128); return $v; }
    private static function signedInt32(?int $value): int { if ($value === null) return 0; return $value > 2147483647 ? $value - 4294967296 : $value; }
}
