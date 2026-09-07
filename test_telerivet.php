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

$phoneNumber = '+639233948281';
$payload = [
    'content' => 'SecureBank Telerivet test SMS.',
    'to_number' => $phoneNumber
];

$url =
    'https://api.telerivet.com/v1/projects/' .
    rawurlencode(TELERIVET_PROJECT_ID) .
    '/messages/send';

$ch = curl_init($url);

if ($ch === false) {
    die('ERROR: Could not initialize cURL.');
}

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

$response = curl_exec($ch);

$httpCode =
    curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

$curlError = curl_error($ch);

curl_close($ch);

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

    <link rel="stylesheet" href="css/style.css">

</head>

<body class="telerivet-page">

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