// Helper function to manage button state
const setupToggle = (inputId, buttonId) => {
    const input = document.getElementById(inputId);
    const button = document.getElementById(buttonId);
    const originalValue = input.value;

    input.addEventListener('input', () => {
        // Enable button if value changes and is not empty
        // Use .toString() to ensure comparison works with numeric types
        if (input.value !== originalValue && input.value.trim() !== "") {
            button.disabled = false;
        } else {
            button.disabled = true;
        }
    });
};

// Initialize for all three forms
setupToggle('wateringTime', 'wateringTimeBtn');
setupToggle('backOffTime', 'backOffTimeBtn');
setupToggle('mixingTime', 'mixingTimeBtn');