## GTFS Format

Gtfs format is a standard format for public transport data.
In this project, is embedded in the database.

### Tables
- `agency`
- `calendar`
- `calendar_dates`
- `routes`
- `shapes`
- `stop_times`
- `stops`
- `trips`

### Table Intersections

```sql
JOIN routes r ON t.route_id = r.route_id
JOIN stop_times st ON t.trip_id = st.trip_id
JOIN stops s ON st.stop_id = s.stop_id
JOIN calendar c ON t.service_id = c.service_id
```

### Table: Agency
- Unused

| agency_id | agency_name | agency_url         | agency_timezone | agency_lang | agency_phone | agency_fare_url            |
| --------- | ----------- | ------------------ | --------------- | ----------- | ------------ | -------------------------- |
| ACTVs.p.a | ACTVs.p.a   | http://www.actv.it | Europe/Rome     | IT          | 39041041     | http://www.veneziaunica.it |

### Table: Calendar

| service_id | monday | tuesday | wednesday | thursday | friday | saturday | sunday | start_date | end_date |
| ---------- | ------ | ------- | --------- | -------- | ------ | -------- | ------ | ---------- | -------- |
| 5C0701_000 | 0      | 0       | 0         | 0        | 0      | 0        | 1      | 20251207   | 20251223 |
| 5C0702_000 | 0      | 1       | 0         | 0        | 0      | 0        | 0      | 20251207   | 20251223 |
| 5C0703_000 | 0      | 0       | 1         | 0        | 0      | 0        | 0      | 20251207   | 20251223 |
| 5C0704_000 | 0      | 0       | 0         | 1        | 0      | 0        | 0      | 20251207   | 20251223 |
| 5C0705_000 | 1      | 0       | 0         | 0        | 1      | 0        | 0      | 20251207   | 20251223 |
| 5C0706_000 | 0      | 0       | 0         | 0        | 0      | 1        | 0      | 20251207   | 20251223 |

### Table: Calendar Dates
- Unused

| service_id | date     | exception_type   |
| ---------- | -------- | ---------------- |
| 5C0701_000 | 20251207 | 1                |
| 5C0702_000 | 20251207 | 1                |
| 5C0703_000 | 20251207 | 1                |
| 5C0704_000 | 20251207 | 1                |
| 5C0705_000 | 20251207 | 1                |
| 5C0706_000 | 20251207 | 1                |

### Table: Routes

| route_id | agency_id | route_short_name | route_long_name                               | route_desc | route_type | route_color | route_text_color |
| -------- | --------- | ---------------- | --------------------------------------------- | ---------- | ---------- | ----------- | ---------------- |
| 1        | ACTVs.p.a | 11               | Santa Maria Elisabetta - Pellestrina Cimitero | UL         | 3          | 5B5B5B      | FFFFFF           |
| 2        | ACTVs.p.a | 11               | Santa Maria Elisabetta - Pellestrina Cimitero | UL         | 3          | 5B5B5B      | FFFFFF           |
| 3        | ACTVs.p.a | 11               | Santa Maria Elisabetta - Pellestrina Cimitero | UL         | 3          | 5B5B5B      | FFFFFF           |
| 4        | ACTVs.p.a | 11               | Malamocco Centro - Pellestrina Cimitero       | UL         | 3          | 5B5B5B      | FFFFFF           |
| 5        | ACTVs.p.a | 11               | Santa Maria Del Mare - Pellestrina Cimitero   | UL         | 3          | 5B5B5B      | FFFFFF           |
| 6        | ACTVs.p.a | 11               | Santa Maria Elisabetta - Faro Rocchetta       | UL         | 3          | 5B5B5B      | FFFFFF           |
...

### Table: Trips

| shape_id | shape_pt_lat | shape_pt_lon | shape_pt_sequence | shape_dist_traveled |
| -------- | ------------ | ------------ | ----------------- | ------------------- |
| 1_0_1    | 45.41782     | 12.368981    | 0                 | 0                   |
| 1_0_1    | 45.417545    | 12.368747    | 1                 | 0.03562153228662857 |
| 1_0_1    | 45.417423    | 12.368521    | 2                 | 0.05805829840320775 |
| 1_0_1    | 45.417377    | 12.36844     | 3                 | 0.06606895498094048 |
| 1_0_1    | 45.417259    | 12.368265    | 4                 | 0.08499255861563323 |
| 1_0_1    | 45.417206    | 12.368233    | 5                 | 0.09145758194821828 |
| 1_0_1    | 45.417194    | 12.36818     | 6                 | 0.0958068666891484  |
| 1_0_1    | 45.417156    | 12.368126    | 7                 | 0.1017394390313324  |
| 1_0_1    | 45.417107    | 12.368098    | 8                 | 0.10768619425268532 |
| 1_0_1    | 45.417053    | 12.368101    | 9                 | 0.11363413515238864 |
| 1_0_1    | 45.41703     | 12.368118    | 10                | 0.11663408511712142 |
| 1_0_1    | 45.417004    | 12.368135    | 11                | 0.11958053623143768 |
| 1_0_1    | 45.416969    | 12.368194    | 12                | 0.12551385458325048 |
| 1_0_1    | 45.416965    | 12.368212    | 13                | 0.12702206592725235 |
| 1_0_1    | 45.416958    | 12.368267    | 14                | 0.13145007261201053 |
| 1_0_1    | 45.416962    | 12.368303    | 15                | 0.13432327140304312 |
| 1_0_1    | 45.416965    | 12.368341    | 16                | 0.137372470600578   |
| 1_0_1    | 45.416985    | 12.368384    | 17                | 0.1413401818568051  |
| 1_0_1    | 45.417019    | 12.368534    | 18                | 0.15366592980551358 |
| 1_0_1    | 45.417007    | 12.368567    | 19                | 0.15641771364995138 |
...

### Table: Stop Times

| trip_id | arrival_time | departure_time | stop_id | stop_sequence | stop_headsign | pickup_type | drop_off_type |
| ------- | ------------ | -------------- | ------- | ------------- | ------------- | ----------- | ------------- |
| 4       | 05:55:00     | 05:55:00       | 261     | 1             | OSPEDALE      | 0           | 1             |
| 4       | 05:55:00     | 05:55:00       | 153     | 2             | OSPEDALE      | 0           | 0             |
| 4       | 05:56:00     | 05:56:00       | 151     | 3             | OSPEDALE      | 0           | 0             |
| 4       | 05:57:00     | 05:57:00       | 1264    | 4             | OSPEDALE      | 0           | 0             |
| 4       | 05:57:00     | 05:57:00       | 1265    | 5             | OSPEDALE      | 0           | 0             |
| 4       | 05:58:00     | 05:58:00       | 1269    | 6             | OSPEDALE      | 0           | 0             |
...

### Table: Stops

**Note:** `data_url` is not a standard field, is used when fetching the incoming trips.

| stop_id | stop_code | stop_name               | stop_lat  | stop_lon  | data_url       |
| ------- | --------- | ----------------------- | --------- | --------- | -------------- |
| 4       | 4         | Torino Rossetto         | 45.479889 | 12.250535 | 4-1004-web-aut |
| 5       | 5         | Torino Universita'      | 45.478184 | 12.253569 | 5-1005-web-aut |
| 7       | 7         | Paganello Ticozzi       | 45.477222 | 12.250363 | 7-1007-web-aut |
| 8       | 8         | Ca' Marcello Rossetto   | 45.478947 | 12.246139 | 8-1008-web-aut |
| 9       | 9         | Ca' Marcello Cappuccina | 45.481762 | 12.23745  | 9-1009-web-aut |
| 10      | 10        | Gozzi Cappuccina        | 45.48465  | 12.237596 | 10-web-aut     |

### Table: Trips

| route_id | service_id | trip_id | trip_headsign        | direction_id | block_id | shape_id |
| -------- | ---------- | ------- | -------------------- | ------------ | -------- | -------- |
| 1        | 5C0701_000 | 2292    | Pellestrina Cimitero | 0            | 400111   | 1_0_1    |
| 1        | 5C0701_000 | 2298    | Pellestrina Cimitero | 0            | 400111   | 1_0_1    |
| 1        | 5C0701_000 | 2304    | Pellestrina Cimitero | 0            | 400111   | 1_0_1    |
| 1        | 5C0701_000 | 2310    | Pellestrina Cimitero | 0            | 400111   | 1_0_1    |
| 1        | 5C0701_000 | 2316    | Pellestrina Cimitero | 0            | 400111   | 1_0_1    |
| 1        | 5C0701_000 | 2333    | Pellestrina Cimitero | 0            | 400112   | 1_0_1    |