<?php

use App\Crud\Mongo;

// Load Composer autoload (provides MongoDB\Client and other deps)
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/crud/mongo.php';

// Build MongoDB URI from environment variables (docker-compose provides these)
$mongoHost = getenv('MONGO_HOST') ?: 'localhost';
$mongoPort = getenv('MONGO_PORT') ?: '27017';
$mongoUser = getenv('MONGO_USER') ?: 'wimpie';
$mongoPass = getenv('MONGO_PASSWORD') ?: 'MongoDB123';
$mongoDb = getenv('MONGO_DATABASE') ?: 'codeinfinity_db';
$authDb = getenv('MONGO_AUTHDB') ?: 'admin';

$uri = sprintf('mongodb://%s:%s@%s:%s/%s?retryWrites=true&w=majority',
    rawurlencode($mongoUser), rawurlencode($mongoPass), $mongoHost, $mongoPort, $authDb
);

$mongo = new Mongo($uri, $mongoDb, 'people');

// Prepare form state and errors
$error = [];
$name = '';
$surname = '';
$idnumber = '';
$dobInput = '';
$dobNormalized = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars($_POST['name'] ?? '');
    if (empty($name)) {
        $error['name'] = 'Name is required.';
    }

    $surname = htmlspecialchars($_POST['surname'] ?? '');
    if (empty($surname)) {
        $error['surname'] = 'Surname is required.';
    }

    $idnumber = htmlspecialchars($_POST['idnumber'] ?? '');
    if (empty($idnumber)) {
        $error['idnumber'] = 'ID Number is required.';
    } else if (!preg_match('/^[0-9]{13}$/', $idnumber)) {
        $error['idnumber'] = 'ID Number must be exactly 13 digits.';
    }

    $dobInput = trim(htmlspecialchars($_POST['dob'] ?? ''));
    if (empty($dobInput)) {
        $error['dob'] = 'Date of Birth is required.';
    } else {
        if (!preg_match('#^(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/([0-9]{4})$#', $dobInput, $m)) {
            $error['dob'] = 'Date of Birth must be in dd/mm/YYYY format.';
        } else {
            $day = (int)$m[1];
            $month = (int)$m[2];
            $year = (int)$m[3];
            if (!checkdate($month, $day, $year)) {
                $error['dob'] = 'Date of Birth is not a valid calendar date.';
            } else {
                $dobNormalized = sprintf('%04d-%02d-%02d', $year, $month, $day);
                try {
                    $dobDate = new DateTimeImmutable($dobNormalized);
                    $today = new DateTimeImmutable('today');
                    if ($dobDate >= $today) {
                        $error['dob'] = 'Date of Birth must be in the past.';
                    }
                } catch (Exception $e) {
                    $error['dob'] = 'Invalid Date of Birth.';
                }
            }
        }
    }

    // After DOB validation, ensure ID prefix matches DOB (YYMMDD)
    if (empty($error) && !empty($idnumber) && !empty($dobNormalized)) {
        // dobNormalized is YYYY-MM-DD; build YYMMDD
        $yy = substr($dobNormalized, 2, 2); // positions 2-3
        $mm = substr($dobNormalized, 5, 2);
        $dd = substr($dobNormalized, 8, 2);
        $expectedPrefix = $yy . $mm . $dd;
        if (substr($idnumber, 0, 6) !== $expectedPrefix) {
            $error['idnumber'] = 'ID Number prefix (first 6 digits) must match DOB in YYMMDD format.';
        }
    }

    // If still no errors, check uniqueness and insert
    if (empty($error)) {
        try {
            if ($mongo->findOneByIdnumber($idnumber)) {
                $error['idnumber'] = 'ID Number already exists.';
            }
        } catch (\Throwable $e) {
            error_log('Error checking existing idnumber: ' . $e->getMessage());
            $error['idnumber'] = 'Could not validate ID Number uniqueness. Please try again later.';
        }
    }

    if (empty($error)) {
        $person = [
            'name' => $name,
            'surname' => $surname,
            'idnumber' => $idnumber,
            'dob' => $dobNormalized
        ];
        try {
            $mongo->insertOne($person);
        } catch (\Throwable $e) {
            error_log('Insert failed: ' . $e->getMessage());
            $error['general'] = 'Failed to save record. Please try again later.';
        }

        if (empty($error)) {
            // Escape values for output
            $outName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $outSurname = htmlspecialchars($surname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $outId = htmlspecialchars($idnumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $outDob = htmlspecialchars($dobNormalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            echo <<<HTML
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Personal Information</title>
                <link rel="stylesheet" href="styles.css">
            </head>
            <body>
                <div class="input-container">
                    <h1>Person Added</h1>
                    <div class="form-group"><label>Name</label><div>{$outName}</div></div>
                    <div class="form-group"><label>Surname</label><div>{$outSurname}</div></div>
                    <div class="form-group"><label>ID Number</label><div>{$outId}</div></div>
                    <div class="form-group"><label>Date of Birth</label><div>{$outDob}</div></div>
                    <div style="margin-top:1rem;"><a href="index.php">Add another person</a></div>
                </div>
            </body>
            </html>
            HTML;
            exit;
        }
    }
}

// Render the form (GET or POST with errors) with inline error messages
$nameVal = htmlspecialchars($name ?? '');
$surnameVal = htmlspecialchars($surname ?? '');
$idnumberVal = htmlspecialchars($idnumber ?? '');
$dobVal = htmlspecialchars($dobInput ?? '');

// Precompute escaped error messages for safe use in heredoc
$errName = htmlspecialchars($error['name'] ?? '');
$errSurname = htmlspecialchars($error['surname'] ?? '');
$errIdnumber = htmlspecialchars($error['idnumber'] ?? '');
$errDob = htmlspecialchars($error['dob'] ?? '');
$errGeneral = htmlspecialchars($error['general'] ?? '');

echo <<<HTML
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test 1</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="input-container">
    <h2>Enter Your Details</h2>
    <form action="index.php" method="POST">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required value="{$nameVal}">
            <div class="error">{$errName}</div>
        </div>
        <div class="form-group">
            <label for="surname">Surname</label>
            <input type="text" id="surname" name="surname" required value="{$surnameVal}">
            <div class="error">{$errSurname}</div>
        </div>
        <div class="form-group">
            <label for="idnumber">ID Number</label>
            <input type="text" id="idnumber" name="idnumber" required pattern="[0-9]{13}" maxlength="13" placeholder="13 digits ID number" value="{$idnumberVal}">
            <div class="error">{$errIdnumber}</div>
        </div>
        <div class="form-group">
            <label for="dob">Date of Birth</label>
            <input type="text" id="dob" name="dob" required placeholder="dd/mm/YYYY" value="{$dobVal}">
            <div class="error">{$errDob}</div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit">POST</button>
            <button type="reset">CANCEL</button>
        </div>
    </form>
    <div class="error">{$errGeneral}</div>
    </div>
</body>
</html>
HTML;
?>
