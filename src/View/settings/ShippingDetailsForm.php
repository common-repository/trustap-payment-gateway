<?php

defined('ABSPATH') || exit;

do_action(wp_enqueue_script('trustap-payment-autocomplete'));

?>

<div class="trustap-shipping-details-container">
    <label for="carrier">
        <?php echo esc_html__("Carrier", "trustap-payment-gateway") ?>
    </label>
    <div class="trustap-autocomplete-container">
        <input type="text"
               id="trustap-autocomplete-input"
               class="trustap-autocomplete"
               onfocus="trustapSearchItem(event.target.value)"
               onkeyup="trustapSearchItem(event.target.value); handleNavigation(event)"
               onchange="validateInputs();"
               data-additional-validation="validateAutocompleteHandler">
        <input type="hidden" id="autocomplete-value-input" name="carrier">
        <div id="trustap-dropdown-container"></div>
    </div>
    <label for="other-carrier" id="other-carrier-label" style="display: none;">
        <?php echo esc_html__("Carrier", "trustap-payment-gateway") ?>
    </label>
    <input type="text"
           name="other-carrier"
           id="other-carrier-input"
           style="display: none;"
           onkeyup="validateInputs()">
    <label for="tracking-code">
        <?php echo esc_html__("Tracking code", "trustap-payment-gateway") ?>
    </label>
    <input type="text" name="tracking-code" onkeyup="validateInputs()">
    <p>
        <small>
            <?php echo esc_html__(
                "Select \"Other\" at the bottom of the list if you can't find your carrier.", "trustap-payment-gateway"
                ) ?>
        </small>
    </p>
    <button class="button button-primary"
            type="button" id="submit-tracking-info"
            onclick="postTrustapData()"
            disabled>
        <?php echo esc_html__("Submit", "trustap-payment-gateway") ?>
    </button>
</div>
<script>
    function validateCarrier() {
        let carrier = document.getElementsByName('carrier')[0];
        let otherCarrierLabel = document.getElementById('other-carrier-label');
        let otherCarrierInput = document.getElementById('other-carrier-input');
        if (carrier.value === "other") {
            otherCarrierLabel.style.display = 'block';
            otherCarrierInput.style.display = 'block';
        } else {
            otherCarrierLabel.style.display = 'none';
            otherCarrierInput.style.display = 'none';
        }
    }

    function validateAutocompleteHandler() {
        validateCarrier();
        validateInputs();
    }

    function postTrustapData() {
        const params = new Proxy(new URLSearchParams(window.location.search), {
            get: (searchParams, prop) => searchParams.get(prop),
        });
        let orderId = params.post; //
        let carrier = document.getElementsByName('carrier')[0].value;
        let otherCarrier = document.getElementsByName('other-carrier')[0].value;
        let trackingCode = document.getElementsByName('tracking-code')[0].value;

        let data = {
            orderId,
            carrier,
            otherCarrier,
            trackingCode
        }
        console.log("<?php echo $save_tracking_details_url ?>");
        fetch("<?php echo esc_url($save_tracking_details_url) ?>", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': "<?php echo esc_html($nonce) ?>"
                },
                credentials: "include",
                body: JSON.stringify(data)
            })
            .then(response => {
                console.log(response.json())
                location.reload();
            });
    }

    function validateInputs() {
        let carrier = document.getElementsByName('carrier')[0].value;
        let otherCarrier = document.getElementsByName('other-carrier')[0].value;
        let trackingCode = document.getElementsByName('tracking-code')[0].value;
        let submitButton = document.getElementById('submit-tracking-info');
        const regExCarrier = new RegExp('^[A-Za-z0-9 _]*[A-Za-z0-9][A-Za-z0-9 _]*$');

        if (carrier !== '' && trackingCode !== '' || carrier !== regExCarrier) {
            if (carrier !== 'other' || otherCarrier !== '') {
                submitButton.removeAttribute('disabled');
            } else {
                submitButton.setAttribute('disabled', true);
            }
        } else {
            submitButton.setAttribute('disabled', true);
        }
    }

    let autocompleteItems = [
        <?php foreach ($shipping_carriers as $shipping_carrier) {
            echo wp_kses_post("{
				value: '{$shipping_carrier['code']}',
				text: '{$shipping_carrier['name']}'
			},");
        }
        ?>
    ]
</script>
