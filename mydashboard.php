<?php
/*
Template Name: Order Dashboard
*/

function readJsonFromFile($filePath)
{
    if (file_exists($filePath)) {
        $jsonData = file_get_contents($filePath);
        return json_decode($jsonData, true);
    }
    return null;
}

// API call function to cancel the schedule =======================================================================
function cancelScheduleAPI($scheduleId)
{
    $url = "https://eu-test.oppwa.com/scheduling/v1/schedules/" . $scheduleId;
    $url .= "?entityId=";
    $url .= "&testMode=EXTERNAL";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer '
    ));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $responseData = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = 'Error: ' . curl_error($ch);
        curl_close($ch);
        return json_encode(['error' => $error]);
    } else {
        curl_close($ch);

        // Check if response is valid JSON, otherwise wrap it in a JSON structure
        $json = json_decode($responseData, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $responseData; // Already JSON, return it
        } else {
            return json_encode(['error' => 'Invalid JSON response from API']);
        }
    }
}


// API endpoint for refund =======================================================================
function refundApi($dbid, $dbamount)
{
    $url = "https://eu-test.oppwa.com/v1/payments/" . $dbid;

    $data = http_build_query(array(
        'entityId' => '',
        'amount' => $dbamount,
        'paymentType' => 'RF',
        'currency' => 'GBP'
    ));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer '
    ));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Set this to true in production
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set this to true in production
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $responseData = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = 'Error: ' . curl_error($ch);
        curl_close($ch);
        return json_encode(['error' => $error]);
    } else {
        curl_close($ch);
        return $responseData;
    }
}

// Paths to the response files
$paymentResponseFile = '/payment_response.txt';
$scheduleResponseFile = '/schedule_response.txt';
$orderResponseFile = '/ordercreated3.txt';

// Read the JSON data
$paymentResponse = readJsonFromFile($paymentResponseFile);
$scheduleResponse = readJsonFromFile($scheduleResponseFile);
$orderResponse = readJsonFromFile($orderResponseFile);
$address = $orderResponse['billing']['address_1'] . ' ' . $orderResponse['billing']['address_2'] ?? '';
$customer = $paymentResponse['customer'] ?? null;
$paymentBrand = $paymentResponse['paymentBrand'] ?? null;

$email = $customer['email'] ?? 'N/A';
$billing = $paymentResponse['billing'] ?? null;
$card = $paymentResponse['card'] ?? null;
$amount = $scheduleResponse['amount'] ?? null;
$previousExecution = $paymentResponse['timestamp'] ?? null;

$scheduleid = $scheduleResponse['id'] ?? null;
$dbid = $paymentResponse['id'] ?? null;
$nextExecution = $scheduleResponse['job']['startDate'] ?? null;

$previousExecutionDate = $previousExecution ? date('Y-m-d H:i:s', strtotime($previousExecution)) : 'N/A';
$nextExecutionDate = $nextExecution ? date('Y-m-d H:i:s', $nextExecution / 1000) : 'N/A';

// Check if the cancel request is made via AJAX =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'cancel_schedule') {
        $scheduleId = $_POST['scheduleId'] ?? null;
        if (!$scheduleId) {
            echo json_encode(['error' => 'Invalid schedule ID']);
            exit;
        }

        $response = cancelScheduleAPI($scheduleId);
        echo $response;
        exit;
    }

    // Handle update_refund_status action=======================================================================
    if ($_POST['action'] === 'update_refund_status') {
        $debitid = $_POST['debitid'] ?? null;

        if (!$debitid) {
            echo json_encode(['error' => 'Invalid debit ID']);
            exit;
        }

        // Update the refund column in the database
        global $wpdb;
        $table_name = 'elirun11w_oppwa_orders_details';

        $updated = $wpdb->update(
            $table_name,
            ['refund' => 'Yes'],
            ['debitid' => $debitid]
        );

        if ($updated !== false) {
            echo json_encode(['success' => 'Refund status updated successfully.']);
        } else {
            echo json_encode(['error' => 'Failed to update refund status.']);
        }

        exit;
    }

    // Check if refund request is made via AJAX =======================================================================
    if ($_POST['action'] === 'refund_payment') {
        $debitid = $_POST['debitid'] ?? null;
        $amount = $_POST['amount'] ?? null;

        if (!$debitid && !$amount) {
            echo json_encode(['error' => 'Invalid Data']);
            exit;
        }
        $refundResponse = refundApi($debitid, $amount);
        echo $refundResponse;
        exit;
    }


    // Handle update_cancel_status action=======================================================================
    if ($_POST['action'] === 'update_cancel_status') {
        $scheduleId = $_POST['scheduleId'] ?? null;

        if (!$scheduleId) {
            echo json_encode(['error' => 'Invalid scheduleId ID']);
            exit;
        }

        global $wpdb;
        $table_name = 'elirun11w_oppwa_orders_details';

        $updated = $wpdb->update(
            $table_name,
            ['cancelled' => 'Yes'],
            ['scheduleid' => $scheduleId]
        );

        if ($updated !== false) {
            echo json_encode(['success' => 'cancelled status updated successfully.']);
        } else {
            echo json_encode(['error' => 'Failed to update cancelled status.']);
        }
        exit;
    }

}


//Insertion==================================================
global $wpdb;
$table_name = 'elirun11w_oppwa_orders_details';

// Extract order ID, schedule ID, and debit ID (dbid) from the JSON response
$order_id = $orderResponse['id'] ?? null;
$scheduleid = $scheduleResponse['id'] ?? null;
$dbid = $paymentResponse['id'] ?? null;

// Check if at least one of the IDs is provided
if ($scheduleid || $dbid) { //$order_id removed
    // Fetch the latest and previous rows from the table
    $latest_order = $wpdb->get_row("SELECT * FROM $table_name ORDER BY snum DESC LIMIT 1"); // Latest order

    // Show the latest and previous order details
    if ($latest_order) {

        // Check if any of the provided IDs already exist in the latest order
        if (
            $latest_order->order_id == $order_id ||
            $latest_order->scheduleid == $scheduleid ||
            $latest_order->debitid == $dbid
        ) {
            // echo "One of the provided IDs already exists in the most recent entry. Skipping insertion.<br>";
        } else {
            $existing_order = $wpdb->get_var($wpdb->prepare(
                "SELECT order_id FROM $table_name WHERE order_id = %d OR scheduleid = %s OR dbid = %s",
                $order_id, $scheduleid, $dbid
            ));

            if (!$existing_order) {
                // Debugging step: check if the required fields exist
                // if ($amount === null) {
                //     echo "Error: Amount is null";
                //     exit;
                // }

                // Insert new record if no matching order_id, scheduleid, or dbid exists
                $data = array(
                    'order_id' => $order_id,
                    'givenName' => $customer['givenName'] ?? 'N/A',
                    'email' => $email,
                    'postcode' => $billing['postcode'] ?? 'N/A',
                    'amount' => $amount,
                    'previous_execution' => $previousExecutionDate,
                    'next_execution' => $nextExecutionDate,
                    'status' => $orderResponse['status'] ?? 'N/A',
                    'street' => $billing['street1'] ?? 'N/A',
                    'city' => $billing['city'] ?? 'N/A',
                    'card_holder' => $card['holder'] ?? 'N/A',
                    'last4Digits' => $card['last4Digits'] ?? 'N/A',
                    'bin' => $card['bin'] ?? 'N/A',
                    'debitid' => $dbid ?? 'N/A',
                    'scheduleid' => $scheduleid ?? 'N/A',
                    'phone' => $orderResponse['billing']['phone'] ?? 'N/A',
                    'paymentBrand' => $paymentResponse['paymentBrand'] ?? 'N/A',
                    'name' => $orderResponse['line_items'][0]['name'] ?? 'N/A',
                    'address'=> $address                
                );

                $inserted = $wpdb->insert($table_name, $data);
            } 
        }
    } else {
        $existing_order = $wpdb->get_var($wpdb->prepare(
            "SELECT order_id FROM $table_name WHERE order_id = %d OR scheduleid = %s OR dbid = %s",
            $order_id, $scheduleid, $dbid
        ));

        if (!$existing_order) {
            // Insert new record if no matching order_id, scheduleid, or dbid exists
            $data = array(
                'order_id' => $order_id,
                'givenName' => $customer['givenName'] ?? 'N/A',
                'email' => $email,
                'postcode' => $billing['postcode'] ?? 'N/A',
                'amount' => $amount,
                'previous_execution' => $previousExecutionDate,
                'next_execution' => $nextExecutionDate,
                'status' => $orderResponse['status'] ?? 'N/A',
                'street' => $billing['street1'] ?? 'N/A',
                'city' => $billing['city'] ?? 'N/A',
                'card_holder' => $card['holder'] ?? 'N/A',
                'last4Digits' => $card['last4Digits'] ?? 'N/A',
                'bin' => $card['bin'] ?? 'N/A',
                'debitid' => $dbid ?? 'N/A',
                'scheduleid' => $scheduleid ?? 'N/A',
                'phone' => $orderResponse['billing']['phone'] ?? 'N/A',
                'paymentBrand' => $paymentResponse['paymentBrand'] ?? 'N/A',
                'name' => $orderResponse['line_items'][0]['name'] ?? 'N/A',
                'address'=> $address 
            );

            $inserted = $wpdb->insert($table_name, $data);        
        } 
    }
}

// Database query using $wpdb to fetch all order details==========
$orders = $wpdb->get_results("SELECT * FROM $table_name ORDER BY order_id DESC");

if (!$orders) {
    echo '<h4 style="text-align:center;">There are no orders available</h4>';
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Order Details</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    </head>
    <style>
        body {
            background-color: transparent;
            background-image: linear-gradient(0deg, #DAE3FF 0%, #DAF8FF 100%);
        }

        #woo_order {
            float: right;
            display: inline-block;
            margin-bottom: 5px;
            padding: 10px 15px;
            color: #7C42DA;
            border: 2px solid rgb(124, 66, 218);
            background-color: transparent;
            border-radius: 30px;
            text-decoration: none;
            font-size: 16px;
        }


        .modal-dialog {
            max-width: 100%;
            margin: 1.75rem auto;
        }

        .modal-content {
            width: 55%;
            /* Set the width of the modal */
            margin: 0 auto;
            /* Center the modal horizontally */
            max-width: 900px;
            /* Optional: Set a max-width for larger screens */
        }


        .table {
            width: 100%;
            table-layout: auto;
            box-shadow: 0px 0px 10px 0px rgba(0, 0, 0, 0.1);
        }

        .table th,
        .table td {
            white-space: nowrap;
        }

        @media (max-width: 576px) {

            .modal-header,
            .modal-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .modal-footer .btn {
                width: 100%;
                margin-top: 10px;
            }

            .modal-body {
                padding: 15px;
            }
        }

        .container {
            max-width: fit-content !important;
        }
    </style>

    <body>

        <div class="container mt-5">
            <h1 style="text-align:center;  font-size: 3.5rem; padding-bottom: 20px;">Subscription Orders</h1>
            <table class="table table-bordered">
                <thead>
                    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
                        rel="stylesheet">

                    <a href="https://www.eliosclinics.com/woocommerce_orders" target="_blank">
                        <button id="woo_order"
                            onmouseover="this.style.backgroundColor='#6c63ff'; this.style.color='#FFFFFF';"
                            onmouseout="this.style.backgroundColor='transparent'; this.style.color='#7C42DA';">
                            Woocommerce Orders
                        </button></a>
                    <tr>
                        <th>Order No.</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Postcode</th>
                        <th>Amount</th>
                        <th>Brand</th>
                        <th>Previous Execution</th>
                        <th>Next Execution</th>
                        <th>Status</th>
                        <th>Cancelled</th>
                        <th>Refunded</th>
                        <th>Phone</th>
                        <th>Product</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <a href="#" class="order-id-link"
                                    data-order-id="<?php echo htmlspecialchars($order->order_id); ?>">
                                    <?php echo htmlspecialchars("#" . $order->order_id); ?>
                                </a>
                            </td>

                            <script>
                                $(document).on('click', '.order-id-link', function (e) {
                                    e.preventDefault(); // Prevent default link behavior
                                    var orderId = $(this).data('order-id'); // Get the order ID from the data attribute

                                    // Redirect to view_orders.php with the order ID as a query parameter
                                    window.location.href = '/view-orders/?order_id=' + orderId;
                                });
                            </script>


                            <td><?php echo htmlspecialchars($order->givenName); ?></td>
                            <td><?php echo htmlspecialchars($order->email); ?></td>
                            <td><?php echo htmlspecialchars($order->postcode); ?></td>
                            <td><?php echo ' £' . htmlspecialchars($order->amount); ?></td>
                            <td> <?php
                            $paymentBrand = strtolower(htmlspecialchars($order->paymentBrand)); // Get payment brand and convert to lowercase
                        
                            // Check for payment brands and display the respective FontAwesome icon
                            switch ($paymentBrand) {
                                case 'visa':
                                    echo '<i class="fab fa-cc-visa" style="font-size: 24px; color: #1a1f71;"></i>'; // Visa icon
                                    break;
                                case 'master':
                                    echo '<i class="fab fa-cc-mastercard" style="font-size: 24px; color: #ff5f00;"></i>'; // Mastercard icon
                                    break;
                                case 'amex':
                                    echo '<i class="fab fa-cc-amex" style="font-size: 24px; color: #2e77bb;"></i>'; // American Express icon
                                    break;
                                default:
                                    echo htmlspecialchars($order->paymentBrand); // Fallback to text if no icon is available
                                    break;
                            }
                            ?></td>
                            <td><?php echo htmlspecialchars($order->previous_execution); ?></td>
                            <td><?php echo htmlspecialchars($order->next_execution); ?></td>
                            <td><?php echo htmlspecialchars($order->status); ?></td>
                            <td><?php echo htmlspecialchars($order->cancelled); ?></td>
                            <td><?php echo htmlspecialchars($order->refund); ?></td>
                            <td><?php echo htmlspecialchars($order->phone); ?></td>
                            <td><?php echo htmlspecialchars(explode(' ', $order->name)[0]); ?></td>

                            <td>
                                <button class="btn btn-warning refund-payment"
                                    data-amount="<?php echo htmlspecialchars($order->amount); ?>"
                                    data-dbid="<?php echo htmlspecialchars($order->debitid); ?>" data-toggle="modal"
                                    data-target="#refundModal" <?php echo (strtolower($order->refund) === 'yes' ? 'disabled' : ''); ?>>Refund
                                </button>

                                <button class="btn btn-danger cancel-schedule"
                                    data-schedule-id="<?php echo htmlspecialchars($order->scheduleid); ?>"
                                    data-toggle="modal" data-target="#cancelModal" <?php echo (strtolower($order->cancelled) === 'yes' ? 'disabled' : ''); ?>>Cancel
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Refund Confirmation Modal -->
        <div class="modal fade" id="refundModal" tabindex="-1" role="dialog" aria-labelledby="refundModalLabel"
            aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="refundModalLabel">Confirm Refund</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to refund this payment?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
                        <button type="button" class="btn btn-danger" id="confirmRefundButton">Yes, Refund</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Confirmation Modal -->
        <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-labelledby="cancelModalLabel"
            aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelModalLabel">Confirm Cancel</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to cancel this schedule?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
                        <button type="button" class="btn btn-danger" id="confirmCancelButton">Yes, Cancel</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- Status Modal -->
        <div class="modal fade" id="statusModal" tabindex="-1" role="dialog" aria-labelledby="statusModalLabel"
            aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Status</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <span id="modalMessage"></span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            let currentAmount;
            let currentDebitid;

            // Handle refund button click
            $(document).on('click', '.refund-payment', function () {
                currentAmount = $(this).data('amount');
                currentDebitid = $(this).data('dbid');
            });

            // Handle confirm refund button click
            $('#confirmRefundButton').on('click', function () {
                console.log("Confirming refund for dbid:", currentDebitid);
                console.log("Confirming refund for amount:", currentAmount);

                $.ajax({
                    type: 'POST',
                    url: '',
                    data: {
                        action: 'refund_payment',
                        debitid: currentDebitid,
                        amount: currentAmount
                    },
                    success: function (response) {
                        let data;
                        try {
                            data = JSON.parse(response);
                        } catch (e) {
                            $('#modalTitle').text('Error');
                            $('#modalMessage').text('Invalid server response. Please try again.');
                            $('#statusModal').modal('show');
                            return;
                        }
                   

                        $('#modalTitle').text('Refund Status');
                        if (data['result']['code'] === '000.000.000') {
                            updateRefundStatus();
                            $('#modalMessage').text('Refund successful!');
                        } else if (data['result']['code'] === '700.400.200') {
                            $('#modalMessage').text('This refund has already been processed.');
                        } else {
                            $('#modalMessage').text(data.error || 'An unexpected error occurred.');
                        }

                        $('#statusModal').modal('show');
                        $('#refundModal').modal('hide');

                    },
                    error: function () {
                        $('#modalTitle').text('Error');
                        $('#modalMessage').text('Error processing refund. Please try again.');
                        $('#statusModal').modal('show');
                        $('#refundModal').modal('hide');
                    }
                });
            });

            // Function to update the refund status in the database
            function updateRefundStatus() {
                $.ajax({
                    type: 'POST',
                    url: '',
                    data: {
                        action: 'update_refund_status',
                        debitid: currentDebitid
                    },
                    success: function (updateResponse) {
                        console.log('Refund status update response:', updateResponse);
                        setTimeout(function () {
                            location.reload();
                        }, 2000);
                    },
                    error: function () {
                        console.log('Error updating the refund status.');
                    }
                });

            }


            //==============================================
            let currentCancelScheduleId;
            // Handle cancel button click
            $(document).on('click', '.cancel-schedule', function () {
                currentCancelScheduleId = $(this).data('schedule-id'); // Get the schedule ID
            });

            // Handle confirm cancel button click
            $('#confirmCancelButton').on('click', function () {
                console.log("Confirming cancel for Schedule ID:", currentCancelScheduleId);
                $.ajax({
                    type: 'POST',
                    url: '',
                    data: {
                        action: 'cancel_schedule',
                        scheduleId: currentCancelScheduleId
                    },
                    success: function (response) {
                        let data;
                        try {
                            data = JSON.parse(response);
                        } catch (e) {
                            $('#modalTitle').text('Error');
                            $('#modalMessage').text('Invalid server response. Please try again.');
                            $('#statusModal').modal('show');
                            return;
                        }

                       

                        $('#modalTitle').text('Cancellation Status');
                        if (data['result']['code'] === '000.000.000') {
                            updateCancelStatus();
                            $('#modalMessage').text('Cancellation successful!');
                        } else if (data['result']['code'] === '100.350.101') {
                            $('#modalMessage').text('This Cancellation has already been processed.');
                        } else {
                            $('#modalMessage').text(data.error || 'An unexpected error occurred.');
                        }

                        $('#statusModal').modal('show');
                        $('#cancelModal').modal('hide');
                    },
                    error: function () {
                        $('#modalTitle').text('Error');
                        $('#modalMessage').text('Error canceling schedule. Please try again.');
                        $('#statusModal').modal('show');
                        $('#cancelModal').modal('hide');
                    }
                });
            });

            // Function to update the cancel status in the database
            function updateCancelStatus() {
                $.ajax({
                    type: 'POST',
                    url: '',
                    data: {
                        action: 'update_cancel_status',
                        scheduleId: currentCancelScheduleId
                    },
                    success: function (updateResponse) {
                        setTimeout(function () {
                            location.reload();
                        }, 2000);
                    },
                    error: function () {
                    }
                });
            }
        </script>
    </body>

</html>