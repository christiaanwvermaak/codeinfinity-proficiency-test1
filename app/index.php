<?php

use App\Crud\Mongo;

    require_once './crud/mongo.php';
    $mongo = new Mongo('mongodb://wimpie:MongoDB123@localhost:27017', 'codeinfinity_db', 'people');

    if ($_SERVER['REQUEST_METHOD'] === 'GET' || (empty($_POST['name']) && empty($_POST['surname']) && empty($_POST['idnumber']) && empty($_POST['dob']))) {
        echo <<<HTML
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Test 1</title>
            <link rel="stylesheet" href="styles.css"> 
        </head>
        <body>
            <<div class="input-container">
            <h2>Enter Your Details</h2>
            <form action="index.php" method="POST">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="surname">Surname</label>
                    <input type="text" id="surname" name="surname" required>
                </div>
                <div class="form-group">
                    <label for="idnumber">ID Number</label>
                    <input type="text" id="idnumber" name="idnumber" required pattern="[0-9]{13}" maxlength="13" placeholder="13 digits ID number">
                </div>
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" required>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit">POST</button>
                    <button type="reset">CANCEL</button>
                </div>
            </form>
        </div>
        </body>
        </html>
        HTML;
    }
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
        }
        if (!preg_match('/^[0-9]{13}$/', $idnumber)) {
            $error['idnumber'] = 'ID Number must be exactly 13 digits.';
        }
        if ($mongo->findOneByIdnumber($idnumber)) {
            $error['idnumber'] = 'ID Number already exists.';
        }

        $dob = htmlspecialchars($_POST['dob'] ?? '');
        if (empty($dob)) {
            $error['dob'] = 'Date of Birth is required.';
        }

        if (!empty($error)) {
            echo "<h2>Errors:</h2><ul>";
            foreach ($error as $err) {
                echo "<li>$err</li>";
            }
            echo "</ul><a href='index.php'>Go back</a>";
            exit;
        } else {
            $person = [
                'name' => $name,
                'surname' => $surname,
                'idnumber' => $idnumber,
                'dob' => $dob
            ];
            $mongo->insertOne($person);
            echo <<<HTML
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Personal Information</title>
            </head>
            <body>
                <h1>Person Added</h1>
                <p>Name: $name</p>
                <p>Surname: $surname</p>
                <p>ID Number: $idnumber</p>
                <p>Date of Birth: $dob</p>
                <a href="index.php">Add another person</a>
            </body>
            </html>
            HTML;
        }
    }
?>
