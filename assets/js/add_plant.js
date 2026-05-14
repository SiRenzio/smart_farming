document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('addPlantForm');
    const submitButton = form.querySelector('.submit-btn');
    const requiredInputs = form.querySelectorAll('[required]');

    // Disable button initially
    submitButton.disabled = true;

    function checkFields() {
        let allFilled = true;

        requiredInputs.forEach(input => {
            if (input.value.trim() === '') {
                allFilled = false;
            }
        });

        submitButton.disabled = !allFilled;
    }

    // Check inputs while typing
    requiredInputs.forEach(input => {
        input.addEventListener('input', checkFields);
    });

    // Disable after submit
    form.addEventListener('submit', function() {
        submitButton.disabled = true;
    });

});