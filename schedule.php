<?php

/*
Template Name: Schedule
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get the checkout ID from the query parameters
$checkoutId = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

if (!$checkoutId) {
    wp_die(__('Checkout ID is missing in result.', 'woocommerce'));
}

// Function to make API request to get the payment result
function request_payment_status($checkoutId)
{


    $url = "https://eu-test.oppwa.com/v1/checkouts/$checkoutId/payment";
    $url .= "?entityId=";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
         'Authorization:Bearer '
    ));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set to true in production
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $responseData = curl_exec($ch);

    if (curl_errno($ch)) {
        return curl_error($ch);
    }

    curl_close($ch);
    $filePath = '/home/eliosclinics/webapps/eliosclinics/payment_response.txt';
    file_put_contents($filePath, $responseData);
    return $responseData;
}

function request_schedule($registrationId, $amount, $currency, $monthsInterval)
{
    $url = "https://eu-test.oppwa.com/scheduling/v1/schedules";

    // Set the next payment date
    $nextPaymentDate = new DateTime();
    $nextPaymentDate->modify('+' . $monthsInterval . ' months');  // Modify based on the interval passed

    $currentMonth = (int) $nextPaymentDate->format('m');
    $months = [];

    // Generate the months for scheduling
    for ($i = 0; $i < 12; $i += $monthsInterval) {
        $month = ($currentMonth + $i - 1) % 12 + 1;
        $months[] = $month;
    }

    // Convert the array of months to a comma-separated string (e.g., "3,6,9,12" for 3 months, "1,2,3,...12" for 1 month)
    $recurringMonths = implode(',', $months);

    // Prepare the data for the API request
    $data = http_build_query(array(
        'entityId' => '',
        'amount' => $amount,
        'paymentType' => 'DB',
        'registrationId' => $registrationId,
        'currency' => $currency,
        //'testMode' => 'EXTERNAL',
        'standingInstruction.type' => 'RECURRING',
        'standingInstruction.mode' => 'REPEATED',
        'standingInstruction.source' => 'MIT',
        'standingInstruction.recurringType' => 'SUBSCRIPTION',
        'job.second' => $nextPaymentDate->format('s'),
        'job.minute' => $nextPaymentDate->format('i'),
        'job.hour' => $nextPaymentDate->format('H'),
        'job.dayOfMonth' => $nextPaymentDate->format('d'),
        'job.month' => $recurringMonths,
        'job.dayOfWeek' => '?',
        'job.year' => '*',
        'job.startDate' => $nextPaymentDate->format('Y-m-d H:i:s')
    ));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization:Bearer '
    ));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $responseData = curl_exec($ch);
    if (curl_errno($ch)) {
        return curl_error($ch); // Return the error if there is one
    }
    curl_close($ch);
    $filePath = '';
    file_put_contents($filePath, $responseData);
    return $responseData;
}

// Initialize variables
$paymentSuccess = false;
$scheduleSuccess = false;

// Get payment status response
$response = json_decode(request_payment_status($checkoutId), true);

// Check if the payment was successful
if (isset($response['result']['code']) && $response['result']['code'] === '000.100.110') {
    $paymentSuccess = true;
    $amount = $response['amount']; // Extract the amount
    $registrationId = $response['registrationId'];
    $currency = $response['currency'];

    // Check if the amount is 60 or 20 before proceeding
    if ($amount == 60) {
        $scheduleResponse = request_schedule($registrationId, $amount, $currency, 3); // Passing 3 months
    } elseif ($amount == 20) {
        $scheduleResponse = request_schedule($registrationId, $amount, $currency, 1); // Passing 1 month
    }

    if (isset($scheduleResponse) && !empty($scheduleResponse)) {
        // Check if the scheduling was successful
        $scheduleResponseData = json_decode($scheduleResponse, true);
        if (isset($scheduleResponseData['id'])) {
            $scheduleSuccess = true; // Set schedule success flag
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Result</title>
        <style>
            .modal {
                display: none;
                position: fixed;
                z-index: 1;
                padding-top: 130px;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: #DAF1FF;

            }

            .modal-content {
                background-color: #fefefe;
                margin: auto;
                padding: 20px;
                border: 1px solid #7C42DA;
                width: 80%;
                max-width: 300px;
                text-align: center;
                border-radius: 20px 20px 20px 20px;

            }

            .modal button {
                color: #FFFFFF;
                font-family: "Elios Sans", Sans-serif;
                font-size: 14px;
                font-weight: 500;
                line-height: 30px;
                letter-spacing: 0px;
                background-color: #7C42DA;
                border-style: solid;
                border-width: 2px 2px 2px 2px;
                border-color: #7C42DA;
                border-radius: 20px 20px 20px 20px;
                cursor: pointer;
            }
        </style>
    </head>

    <body>

        <!-- Success Modal -->
        <div id="successModal" class="modal">
            <div class="modal-content">
                <p>Your subscription has been processed successfully.</p>
                <button onclick="window.location.href = '';">Go to
                    Dashboard</button>
            </div>
        </div>

        <!-- Failure Modal -->
        <div id="failureModal" class="modal">
            <div class="modal-content">
                <p>There was an issue with your payment or scheduling. Please try again.</p>
                <button onclick="window.location.href = '';">Try
                    Again</button>
            </div>
        </div>


        <script>
            document.addEventListener('DOMContentLoaded', function () {
                <?php if ($paymentSuccess && $scheduleSuccess): ?>
                    document.getElementById('successModal').style.display = 'block';
                    setTimeout(function () {
                        window.location.href = '';
                    }, 5000);
                <?php else: ?>
                    document.getElementById('failureModal').style.display = 'block';
                    setTimeout(function () {
                        window.location.href = '';
                    }, 5000);
                <?php endif; ?>
            });
        </script>
    </body>

</html>