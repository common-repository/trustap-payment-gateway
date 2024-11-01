let trustapCurrentFocus = -1;

function trustapSearchItem(value) {
    const trustapDropdownContainer = document.getElementById('trustap-dropdown-container');
    if (!trustapDropdownContainer.classList.contains('active')) {
        trustapDropdownContainer.classList.add('active')
    }
    const filteredItems = autocompleteItems.filter(
        (carrier) => carrier.text.toLowerCase().includes(
            value.toLowerCase()
    ));
    trustapGenerateNodeList(filteredItems)
}

function trustapGenerateNodeList(filteredItems) {
    const trustapDropdownContainer = document.getElementById('trustap-dropdown-container');
    trustapDropdownContainer.innerHTML = "";
    filteredItems.forEach(item => {
        let itemElement = document.createElement('div');
        itemElement.innerHTML = item.text;
        itemElement.setAttribute('onclick', 'selectItem(event.target.dataset)');
        itemElement.setAttribute('data-value', `${item.value}`);
        itemElement.setAttribute('data-text', `${item.text}`);
        itemElement.classList.add('trustap-autocomplete-item')
        trustapDropdownContainer.appendChild(itemElement);
    })
}

function trustapCloseDropdown() {
    const trustapDropdownContainer = document.getElementById('trustap-dropdown-container');
    trustapDropdownContainer.classList.remove('active');
    trustapCurrentFocus = -1;
}

function selectItem(dataset) {
    const trustapAutocompleteInput = document.getElementById('trustap-autocomplete-input');
    const trustapAutocompleteValueInput = document.getElementById('autocomplete-value-input');
    trustapAutocompleteValueInput.value = dataset.value;
    trustapAutocompleteInput.value = dataset.text;
    trustapCloseDropdown();
    additionalValidationHandler();
}

function validateInput(event) {
    const trustapAutocompleteInput = document.getElementById('trustap-autocomplete-input');
    const trustapAutocompleteValueInput = document.getElementById('autocomplete-value-input');

    if (
        event.target.parentNode.className === 'trustap-autocomplete-item' ||
        event.target.className === 'trustap-autocomplete-item' || 
        event.target.className === 'trustap-autocomplete'
    ) {
        return;
    }
    let isValid;
    autocompleteItems.forEach(item => {
        if (item.text === trustapAutocompleteInput.value) {
            trustapAutocompleteValueInput.value = item.value;
            isValid = true;
        }
    })
    if (!isValid) {
        trustapClearInputs()
    }

    trustapCloseDropdown();
}

function trustapClearInputs() {
    const trustapAutocompleteInput = document.getElementById('trustap-autocomplete-input');
    const trustapAutocompleteValueInput = document.getElementById('autocomplete-value-input');
    trustapAutocompleteValueInput.value = "";
    trustapAutocompleteInput.value = "";
    additionalValidationHandler();
}

function handleNavigation(event) {
    const trustapDropdownContainer = document.getElementById('trustap-dropdown-container');
    const trustapAutocompleteInput = document.getElementById('trustap-autocomplete-input');
    const trustapDropdownContainerItems = trustapDropdownContainer.childNodes;
    const caretPosition = trustapAutocompleteInput.value.length;
    
    switch (event.keyCode) {
        case 40:
            // Key down
            if (trustapCurrentFocus < trustapDropdownContainerItems.length - 1) {
                trustapCurrentFocus++;
                trustapAutocompleteInput.setSelectionRange(caretPosition, caretPosition)
                trustapDropdownContainerItems[trustapCurrentFocus].classList.add('active');
                const activeElement = trustapDropdownContainerItems[trustapCurrentFocus];
                trustapDropdownContainer.scrollTo({top: activeElement.offsetTop, behavior: 'smooth'});
            } else if (trustapCurrentFocus === trustapDropdownContainerItems.length - 1) {
                trustapDropdownContainerItems[trustapCurrentFocus].classList.add('active');
            }
            break;
        case 38:
            // Key up
            if (trustapCurrentFocus > 0) {
                trustapCurrentFocus--;
                trustapAutocompleteInput.setSelectionRange(caretPosition, caretPosition)
                trustapDropdownContainerItems[trustapCurrentFocus].classList.add('active');
                const activeElement = trustapDropdownContainerItems[trustapCurrentFocus];
                trustapDropdownContainer.scrollTo({top: activeElement.offsetTop, behavior: 'smooth'});
            } else if (trustapCurrentFocus === 0) {
                trustapDropdownContainerItems[trustapCurrentFocus].classList.add('active');
            }
            break;
        case 13:
            // Enter
            trustapDropdownContainerItems[trustapCurrentFocus].click();
            break;
        case 27:
            // Escape
            trustapCloseDropdown();
            break;
        default:
            trustapCurrentFocus = -1;
    }
}

function additionalValidationHandler() {
    const trustapAutocompleteInput = document.getElementById('trustap-autocomplete-input');
    if (!!trustapAutocompleteInput.dataset['additionalValidation']) {
        window[trustapAutocompleteInput.dataset['additionalValidation']]();
    }
}

// Prevent form submission when enter is pressed
window.addEventListener('keydown', function(event) {
    if (event.keyCode == 13) {
        if (event.target.nodeName == 'INPUT' && event.target.type == 'text') {
            event.preventDefault();
            return false;
        }
    }
}, true);

// Doing validation on click because Wordpress is overriding onblur event
window.addEventListener('click', function(event) {
    validateInput(event);
})
