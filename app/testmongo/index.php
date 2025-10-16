<?php

    require_once __DIR__ . '/../vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    $client = new MongoDB\Client($_ENV['MONGO_URI'] ?? 'mongodb://wimpie:MongoDB123@localhost:27021/admin?retryWrites=true&w=majority');

    $collection = $client->selectCollection('sample_data', 'testphp');

    $document = $collection->insertOne([
        'name' => 'John',
        'surname' => 'Doe',
        'idnumber' => '1234567890123',
        'dob' => new MongoDB\BSON\UTCDateTime(strtotime('1990-01-01') * 1000),
        'createdAt' => new MongoDB\BSON\UTCDateTime()
    ]);

    var_dump($document);
?>
