function customizeLoginButton() {
    var trustapOidcForm = document.getElementById('trustap-oidc-form');
    var trustapSettingsForm = document.getElementById('trustap-settings-form');
    var wooCommerceForm = document.getElementById('mainform');
    var submitSection = document.getElementsByClassName('submit')[1];

    if (trustapOidcForm) {
        submitSection.remove();
        var oidcLoginUri = document.getElementsByName('oidc_login_uri')[0].value;
        wooCommerceForm.setAttribute('action', oidcLoginUri);
    }

    if (trustapSettingsForm) {
        var saveButton = document.getElementsByClassName('woocommerce-save-button')[0];
        saveButton.classList.add('trustap-success-button');
        saveButton.classList.remove('button-primary');
    }
}

window.addEventListener('load', (event) => {
    customizeLoginButton();
});
