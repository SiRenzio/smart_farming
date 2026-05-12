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

const resetBtn = document.getElementById('resetBtn');
resetBtn.addEventListener('click', () => {
    // Disable the button immediately to prevent multiple clicks
    resetBtn.disabled = true;

    fetch("../api/reset_active_tank.php", {})
        .then(response => response.json())
        .then(data => {
            // Handle the response from the server
            console.log(data);
            var div = document.createElement("div");
            div.innerHTML = `<div class="success-toast"><i class="fas fa-info-circle"></i><span>${data.message}</span></div>`;
            document.body.appendChild(div);

            // Remove the toast after 3 seconds
            setTimeout(() => {
                div.remove();
            }, 3000);
        })
        .catch(error => {
            console.error('Error:', error);
        });
});