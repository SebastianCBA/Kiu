<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class JourneyController extends Controller
{
    /**
     * Busca viajes (secuencias de vuelos) desde un origen a un destino en una fecha especifica.
     *
     * Esta función procesa una solicitud GET con parametros `date` (YYYY-MM-DD),
     * `from` (código de la ciudad de origen) y `to` (código de la ciudad de destino).
     * Devuelve una lista de viajes validos que cumplen las siguientes condiciones:
     * - Máximo de 2 conexiones.
     * - Duración total del viaje no superior a 24 horas.
     * - Tiempo de conexión entre vuelos no mayor a 4 horas.
     *
     * Ejemplo de respuesta:
     * [
     *   {
     *     "connections": 1,
     *     "path": [
     *       {
     *         "flight_number": "FL1234",
     *         "from": "BUE",
     *         "to": "MAD",
     *         "departure_time": "2024-09-12T12:00:00Z",
     *         "arrival_time": "2024-09-13T00:00:00Z"
     *       }
     *     ]
     *   },
     *   {
     *     "connections": 2,
     *     "path": [
     *       {
     *         "flight_number": "FL1234",
     *         "from": "BUE",
     *         "to": "MAD",
     *         "departure_time": "2024-09-12T12:00:00Z",
     *         "arrival_time": "2024-09-13T00:00:00Z"
     *       },
     *       {
     *         "flight_number": "FL5678",
     *         "from": "MAD",
     *         "to": "PMI",
     *         "departure_time": "2024-09-13T02:00:00Z",
     *         "arrival_time": "2024-09-13T03:00:00Z"
     *       }
     *     ]
     *   }
     * ]
     *
     * @param Request $request La solicitud HTTP que contiene los parametros `date`, `from` y `to`.
     *
     * - `date` (string): Fecha de salida en formato `YYYY-MM-DD`. Ejemplo: `2024-09-12`.
     * - `from` (string): Código de la ciudad de origen. Ejemplo: `BUE`.
     * - `to` (string): Código de la ciudad de destino. Ejemplo: `PMI`.
     *
     * @return \Illuminate\Http\JsonResponse
     * Un JSON con una lista de viajes validos. Cada viaje incluye:
     * - `connections`: Numero de vuelos en el viaje (1 o 2).
     * - `path`: Secuencia de eventos de vuelo que forman el viaje, con los detalles de cada vuelo:
     *    - `flight_number`: Número de vuelo.
     *    - `from`: Ciudad de origen.
     *    - `to`: Ciudad de destino.
     *    - `departure_time`: Fecha y hora de salida (UTC).
     *    - `arrival_time`: Fecha y hora de llegada (UTC).
     *
     * @throws \Exception Si ocurre un error al procesar los vuelos o generar los viajes.
     */
    public function search(Request $request)
    {
        // Obtener parámetros de consulta
        $date = $request->query('date'); // YYYY-MM-DD
        $from = $request->query('from'); // Código de ciudad (ej. BUE)
        $to = $request->query('to');     // Código de ciudad (ej. PMI)

        // Generar eventos de vuelo simulados
        $flights = $this->generateFlightEvents($date);

        // Crear combinaciones de viajes válidos (1 o 2 conexiones)
        $results = $this->createValidJourneys($flights, $from, $to, $date);

        // Retornar las primeras 4 o 5 combinaciones como respuesta
        return response()->json(array_slice($results, 0, 5));
    }

    /**
     * Genera una lista simulada de eventos de vuelo para una fecha especifica devolviendo una matriz
     * con el formato del payload proporcionado en el openApi.
     *
     * @param string $date La fecha de los eventos de vuelo en formato `YYYY-MM-DD`.
     * @return array
     * Una lista de eventos de vuelo simulados. Cada evento incluye:
     * - `flight_number`: Número de vuelo.
     * - `departure_city`: Ciudad de origen (código IATA).
     * - `arrival_city`: Ciudad de destino (código IATA).
     * - `departure_datetime`: Fecha y hora de salida en formato ISO8601 (UTC).
     * - `arrival_datetime`: Fecha y hora de llegada en formato ISO8601 (UTC).
     */
    private function generateFlightEvents($date)
    {
        $cities = ['BUE', 'MAD', 'PMI', 'LAX', 'NYC', 'CDG', 'FRA'];
        $events = [];

        for ($i = 0; $i < 10; $i++) {
            $departureCity = $cities[array_rand($cities)];
            $arrivalCity = $cities[array_rand($cities)];
            while ($arrivalCity === $departureCity) {
                $arrivalCity = $cities[array_rand($cities)];
            }

            $departureTime = date('Y-m-d\TH:i:s\Z', strtotime("$date +".rand(0, 23)." hours +".rand(0, 59)." minutes"));
            $arrivalTime = date('Y-m-d\TH:i:s\Z', strtotime("$departureTime +".rand(1, 6)." hours"));

            $events[] = [
                'flight_number' => 'FL' . rand(1000, 9999),
                'departure_city' => $departureCity,
                'arrival_city' => $arrivalCity,
                'departure_datetime' => $departureTime,
                'arrival_datetime' => $arrivalTime,
            ];
        }

        return $events;
    }

    /**
     * Crea una lista de viajes válidos a partir de los eventos de vuelo.
     *
     * @param array $flights Lista de eventos de vuelo.
     * @param string $from Código IATA de la ciudad de origen.
     * @param string $to Código IATA de la ciudad de destino.
     * @param string $date Fecha de salida en formato `YYYY-MM-DD`.
     * @return array
     * Una lista de viajes válidos que cumplen las condiciones del challenge.
     */
    private function createValidJourneys($flights, $from, $to, $date)
    {
        $results = [];

        foreach ($flights as $flight1) {
            // Vuelos directos
            if ($flight1['departure_city'] === $from && $flight1['arrival_city'] === $to) {
                $results[] = [
                    'connections' => 1,
                    'path' => [$flight1],
                ];
            }

            // Vuelos con una conexión
            foreach ($flights as $flight2) {
                if (
                    $flight1['departure_city'] === $from &&
                    $flight1['arrival_city'] === $flight2['departure_city'] &&
                    $flight2['arrival_city'] === $to &&
                    strtotime($flight2['departure_datetime']) - strtotime($flight1['arrival_datetime']) <= 14400 && // Máximo 4 horas de conexión
                    strtotime($flight2['arrival_datetime']) - strtotime($flight1['departure_datetime']) <= 86400 // Máximo 24 horas de duración total
                ) {
                    $results[] = [
                        'connections' => 2,
                        'path' => [$flight1, $flight2],
                    ];
                }
            }
        }

        return $results;
    }
}
