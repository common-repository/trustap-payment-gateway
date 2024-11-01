<?php

defined('ABSPATH') || exit;

do_action(wp_enqueue_script('trustap-payment-settings'));

$login = __("Login", "trustap-payment-gateway");
$logout = __("Logout", "trustap-payment-gateway");

?>

<tr valign="top" id="trustap-settings-form">
<th>
        <?php echo esc_html__(
            "Trustap Keys",
            "trustap-payment-gateway"
        )
?>
    </th>
    <td>
        <div class="trustap-settings-container">
            <p>
            <img src="<?php echo wp_kses_post($icons['info']) ?>"
                     alt="Info icons"
                     class="trustap-status-icon">
                <?php echo esc_html__(
                    "Before you can use the Trustap Payment Gateway you will
                        need to link a Trustap account for payouts. Please enter
                        your Trustap key and click on the Login button,
                        which will redirect you to our login page. Please contact
                        Trustap support if you don't yet have a Trustap key.",
                    "trustap-payment-gateway"
                )
                        ?>
                <div class="trustap-settings-form">
                    <label for="trustap-test-key">
                        <?php echo
                            esc_html__("Trustap Test Key", "trustap-payment-gateway");

                        if ($is_logged_id['test']) {
                            echo wp_kses_post("<img src='{$icons['checkmark']}' alt=''>");
                        }
                        ?>
                    </label>
                    <?php if ($is_logged_id['test']) {
                            echo "<input type='text'
                                        name='trustap-test-key'
                                        value='***************************'
                                        disabled>
                                    <a href='" . esc_url($logout_uri) . "&environment=test'>
                                        <button class='trustap-success-button'
                                                id='trustap-test-key-button'
                                                type='button'>" .
                                            esc_html($logout) . "
                                        </button>
                                    </a>
                                ";
                    } else {
                        echo "<input type='text'
                                    name='trustap-test-key'
                                    onkeyup='validateLogin(event)'
                                    onpaste='validateLogin(event)'>
                                <button class='trustap-success-button'
                                        id='trustap-test-key-button'
                                        type='button'
                                        onclick='login(\"test\")'>" .
                                        esc_html($login) . "
                                </button>
                            ";
                    }
                    ?>
                </div>
                <div class="trustap-settings-form">
                    <label for="trustap-live-key">
                        <?php echo
                            esc_html__("Trustap Live Key", "trustap-payment-gateway")
                        ?>
                    </label>
                    <?php if ($is_logged_id['live']) {
                            echo "
                                <input type='text'
                                    name='trustap-live-key'
                                    value='***************************'
                                    disabled>
                                <a href='" . esc_url($logout_uri) . "&environment=live'>
                                    <button class='trustap-success-button'
                                            id='trustap-live-key-button'
                                            type='button'>" .
                                            esc_html($logout) . "</button>
                                </a>
                            ";
                    } else {
                        echo "<input type='text'
                                    name='trustap-live-key'
                                    onkeyup='validateLogin(event)'
                                    onpaste='validateLogin(event)'>
                                <button class='trustap-success-button'
                                        id='trustap-live-key-button'
                                        type='button'
                                        onclick='login(\"live\")'>" .
                                    esc_html($login) . "
                                </button>
                            ";
                    }
                    ?>
                </div>
            </p>
        </div>
    </td>
</tr>
<script>
const testKeyButton= document.getElementById('trustap-test-key-button');
const liveKeyButton= document.getElementById('trustap-live-key-button');

const testKeyInputValue = document.getElementsByName('trustap-test-key')[0].value;
const liveKeyInputValue = document.getElementsByName('trustap-live-key')[0].value;

if (testKeyInputValue === "") {
    testKeyButton.classList.add('disabled')
}
if (liveKeyInputValue === "") {
    liveKeyButton.classList.add('disabled')
}

function validateLogin(event) {
    if (event.target.name === "trustap-test-key" && !!event.target.value) {
        testKeyButton.classList.remove('disabled')
    }
    if (event.target.name === "trustap-test-key" && !event.target.value) {
        testKeyButton.classList.add('disabled')
    }
    if (event.target.name === "trustap-live-key" && !!event.target.value) {
        liveKeyButton.classList.remove('disabled')
    }
    if (event.target.name === "trustap-live-key" && !event.target.value) {
        liveKeyButton.classList.add('disabled')
    }
}

function login(environment) {
    const testKeyInputValue = document.getElementsByName('trustap-test-key')[0].value;
    const liveKeyInputValue = document.getElementsByName('trustap-live-key')[0].value;
    let trustap_credentials;
    let loginUri;

    switch(environment) {
        case 'test':
            trustap_credentials = testKeyInputValue;
            loginUri = '<?php echo esc_url($oidc_test_login_uri) ?>';

            break;
        case 'live':
            trustap_credentials = liveKeyInputValue;
            loginUri = '<?php echo esc_url($oidc_live_login_uri) ?>';

    }

    const data = {
        trustap_credentials
    }

    fetch(loginUri, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(response => { return response.json()} )
        .then(response => {
            if (response.data === 'success') {
                window.location.href = loginUri;
            }
        })
        .catch(err => {
            console.log(JSON.parse(err))
    })
}
</script>
