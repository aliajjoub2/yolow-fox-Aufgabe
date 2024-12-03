<?php

class Trip
{
    private $data; // Diese speichert die Rohdaten der Fahrt
    private $startTime; // Speichert den Startzeitpunkt der Fahrt als DateTime-Objekt
    private $endTime; // Speichert den Endzeitpunkt der Fahrt als DateTime-Objekt.
    private $duration; // Enthält die Gesamtdauer der Fahrt in Sekunden.
    private $distance; // Speichert die insgesamt zurückgelegte Distanz der Fahrt in km with 2 decimal places

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->calculateDuration(); // Diese Methode wird aufgerufen, um die Gesamtdauer der Fahrt zu berechnen.
        $this->calculateDistance(); // Diese Methode wird aufgerufen, um die insgesamt zurückgelegte Distanz zu berechnen.
    }

    // 1- Die Methode calculateDuration() berechnet die Gesamtdauer der Fahrt in Sekunden.
    private function calculateDuration()
    {
        $this->startTime = new DateTime($this->data[0]['time']); // Hier wird der Startzeitpunkt der Fahrt ermittelt.
        $this->endTime = new DateTime(end($this->data)['time']); // Hier wird der Endzeitpunkt der Fahrt ermittelt.
        $this->duration = $this->endTime->getTimestamp() - $this->startTime->getTimestamp(); // Das Ergebnis ist die Gesamtdauer der Fahrt in Sekunden.
    }

    // 2- Berechne anhand der vorhanden Geokoordinaten die zurückgelegte Distanz(Luftlinie) in Kilometer(km) mit 2 Nachkommastellen
    private function calculateDistance()
    {
        $totalDistance = 0.0; // Variable zur Speicherung der kumulierten Gesamtdistanz, initialisiert mit 0.
        $earthRadius = 6371; // Earth radius in kilometers

        for ($i = 0; $i < count($this->data) - 1; $i++) {
            $lat1 = deg2rad($this->data[$i]['latitude']);  // Breitengrad des aktuellen Punktes, umgerechnet von Grad in Bogenmaß.
            $lon1 = deg2rad($this->data[$i]['longitude']); // Längengrad des aktuellen Punktes, umgerechnet von Grad in Bogenmaß.
            $lat2 = deg2rad($this->data[$i + 1]['latitude']); // Breitengrad des nächsten Punktes, umgerechnet von Grad in Bogenmaß.
            $lon2 = deg2rad($this->data[$i + 1]['longitude']); // Längengrad des nächsten Punktes, umgerechnet von Grad in Bogenmaß.

            $dLat = $lat2 - $lat1; // Unterschied der Breitengrade zwischen den beiden Punkten.
            $dLon = $lon2 - $lon1; // Unterschied der Längengrade zwischen den beiden Punkten.

            /**
             * Haversine Formula 
             * a = sin²(φB - φA/2) + cos φA * cos φB * sin²(λB - λA/2)
             * c = 2 * atan2( √a, √(1−a) )
             * distance = R ⋅ c
             * Qwelle: https://community.esri.com/t5/coordinate-reference-systems-blog/distance-on-a-sphere-the-haversine-formula/ba-p/902128
             */
            $a = sin($dLat / 2) * sin($dLat / 2) +
                cos($lat1) * cos($lat2) *
                sin($dLon / 2) * sin($dLon / 2);

            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distance = $earthRadius * $c;
            $totalDistance += $distance;
        }

        $this->distance = round($totalDistance, 2); // Die gesamte zurückgelegte Distanz wird auf zwei Nachkommastellen gerundet
    }

     /**
      * 3- Schreibe eine kleine Funktion, die die berechneten Werte aus Schritt 1 und 2 mittels einem INSERT SQL-Statement in eine Datenbanktabelle „trips" schreibt.
      * Die Methode insertTrip() ist eine öffentliche Funktion innerhalb einer Klasse, die dazu dient,
      * die berechneten Fahrtdaten in eine Datenbank einzufügen. Ich verwende PDO (PHP Data Objects) für eine 
      * sichere und effiziente Datenbankinteraktion.
    */
    public function insertTrip(PDO $pdo)
    {
        $startTimeStr = $this->startTime->format('Y-m-d H:i:s');
        $endTimeStr = $this->endTime->format('Y-m-d H:i:s');
        $duration = $this->duration;
        $distance = $this->distance;

        $sql = "INSERT INTO trips (Starttime, Endtime, Duration, Distance) VALUES (:starttime, :endtime, :duration, :distance)";
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':starttime', $startTimeStr);
        $stmt->bindValue(':endtime', $endTimeStr);
        $stmt->bindValue(':duration', $duration, PDO::PARAM_INT);
        $stmt->bindValue(':distance', $distance, PDO::PARAM_STR); // PDO has no PARAM_FLOAT, use PARAM_STR

        $stmt->execute();
    }
}

// Hauptskript
// Daten aus 'drive.json' einlesen
$jsonData = file_get_contents('drive.json');
$dataArray = json_decode($jsonData, true);

// Trip-Objekt erstellen
$trip = new Trip($dataArray);

// PDO-Verbindung herstellen
$host = 'localhost';
$db   = 'datenbankname'; 
$user = 'benutzername';   
$pass = 'passwort';       
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Optionen für PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Fehler als Exceptions werfen
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Fehlerbehandlung
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Trip in die Datenbank einfügen
$trip->insertTrip($pdo);

echo "Trip erfolgreich in die Datenbank eingefügt.";

?>
