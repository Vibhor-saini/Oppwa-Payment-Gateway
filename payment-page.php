<?php
/*
Template Name: OPWAP Payment Page
*/

if (!defined('ABSPATH')) {
    exit;
}

$checkoutId = isset($_GET['checkoutId']) ? sanitize_text_field($_GET['checkoutId']) : '';
if (!$checkoutId) {
    wp_die(__('Checkout ID is missing.', 'woocommerce'));
}

$shopperResultUrl = home_url('/schedule'); 
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Form</title>
    <script src="https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId=<?php echo htmlspecialchars($checkoutId); ?>"></script>
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
            background-color: rgba(218, 241, 255, 0.8);
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #7C42DA;
            width: 80%;
            max-width: 300px;
            text-align: center;
            border-radius: 20px;
        }

        .modal button {
            color: #FFFFFF;
            font-family: "Elios Sans", Sans-serif;
            font-size: 14px;
            font-weight: 500;
            line-height: 30px;
            letter-spacing: 0px;
            background-color: #7C42DA;
            border: 2px solid #7C42DA;
            border-radius: 20px;
            cursor: pointer;
        }

        .wpwl-message.wpwl-has-error {
            display: none;
        }
    </style>
</head>

<body>

    <form action="<?php echo esc_url($shopperResultUrl); ?>" class="paymentWidgets" data-brands="VISA MASTER AMEX"></form>

    <div id="successModal" class="modal" style="display: none;">
    </div>

    <script>
        const checkoutId = "<?php echo htmlspecialchars($checkoutId); ?>";
        const previousCheckoutId = localStorage.getItem('checkoutId');

        if (previousCheckoutId === checkoutId) {
            document.getElementById('successModal').style.display = 'block';

            setTimeout(function() {
                    window.location.href = 'https://www.eliosclinics.com/patient-dashboard/';
                }, 3000);
        } else {
            localStorage.setItem('checkoutId', checkoutId);
        }
    </script>
</body>

</html>