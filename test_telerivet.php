<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$message = '';
$responseData = null;

if (
    !defined('TELERIVET_API_KEY') ||
    !defined('TELERIVET_PROJECT_ID') ||
    TELERIVET_API_KEY === '' ||
    TELERIVET_PROJECT_ID === ''
) {
    die('Telerivet API key or Project ID is not configured in config.php.');
}


/*
|--------------------------------------------------------------------------
| CHANGE THIS NUMBER
|--------------------------------------------------------------------------
|
| Put the phone number where you want to receive the test SMS.
|
*/

$phoneNumber = '+639233948281';


/*
|--------------------------------------------------------------------------
| Test message
|--------------------------------------------------------------------------
*/

$payload = [
    'content' => 'SecureBank Telerivet test SMS.',
    'to_number' => $phoneNumber
];


/*
|--------------------------------------------------------------------------
| Telerivet API URL
|--------------------------------------------------------------------------
*/

$url =
    'https://api.telerivet.com/v1/projects/' .
    rawurlencode(TELERIVET_PROJECT_ID) .
    '/messages/send';


/*
|--------------------------------------------------------------------------
| Initialize CURL
|--------------------------------------------------------------------------
*/

$ch = curl_init($url);

if ($ch === false) {
    die('ERROR: Could not initialize cURL.');
}


/*
|--------------------------------------------------------------------------
| CURL options
|--------------------------------------------------------------------------
*/

curl_setopt_array(
    $ch,
    [
        CURLOPT_POST => true,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],

        CURLOPT_USERPWD =>
            TELERIVET_API_KEY . ':',

        CURLOPT_POSTFIELDS =>
            json_encode($payload),

        CURLOPT_TIMEOUT => 30,

        CURLOPT_CONNECTTIMEOUT => 10
    ]
);


/*
|--------------------------------------------------------------------------
| Execute request
|--------------------------------------------------------------------------
*/

$response = curl_exec($ch);

$httpCode =
    curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

$curlError = curl_error($ch);

curl_close($ch);


/*
|--------------------------------------------------------------------------
| Display result
|--------------------------------------------------------------------------
*/

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Telerivet Test
    </title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f3f6f9;
            padding: 40px;
        }

        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,.10);
        }

        h1 {
            color: #1f4f7a;
        }

        .success {
            background: #e8f7ed;
            color: #176b35;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .error {
            background: #fde8e8;
            color: #a61b1b;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .info {
            background: #eef5fb;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        pre {
            background: #222;
            color: #eee;
            padding: 20px;
            border-radius: 6px;
            overflow-x: auto;
            white-space: pre-wrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        td:first-child {
            font-weight: bold;
            width: 200px;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>
        SecureBank - Telerivet Test
    </h1>


    <?php if ($response === false): ?>

        <div class="error">

            <strong>
                cURL ERROR
            </strong>

            <br><br>

            <?= htmlspecialchars($curlError) ?>

        </div>

    <?php elseif ($httpCode >= 200 && $httpCode < 300): ?>

        <div class="success">

            <strong>
                Telerivet API request was accepted.
            </strong>

            <br><br>

            Check your phone for the test SMS.

        </div>

    <?php else: ?>

        <div class="error">

            <strong>
                Telerivet API request failed.
            </strong>

            <br><br>

            HTTP Status:
            <?= htmlspecialchars((string) $httpCode) ?>

        </div>

    <?php endif; ?>


    <div class="info">

        <table>

            <tr>

                <td>
                    HTTP Status
                </td>

                <td>
                    <?= htmlspecialchars((string) $httpCode) ?>
                </td>

            </tr>

            <tr>

                <td>
                    Project ID
                </td>

                <td>
                    <?= htmlspecialchars(
                        (string) TELERIVET_PROJECT_ID
                    ) ?>
                </td>

            </tr>

            <tr>

                <td>
                    Phone Number
                </td>

                <td>
                    <?= htmlspecialchars($phoneNumber) ?>
                </td>

            </tr>

            <tr>

                <td>
                    API Key
                </td>

                <td>
                    Configured
                </td>

            </tr>

        </table>

    </div>


    <h2>
        Telerivet Response
    </h2>

    <pre><?php

        if ($response === false) {

            echo htmlspecialchars(
                $curlError
            );

        } else {

            $decoded =
                json_decode(
                    $response,
                    true
                );

            if (
                json_last_error() === JSON_ERROR_NONE
            ) {

                echo htmlspecialchars(
                    json_encode(
                        $decoded,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_SLASHES
                    )
                );

            } else {

                echo htmlspecialchars(
                    $response
                );
            }
        }

    ?></pre>

</div>

</body>

</html>