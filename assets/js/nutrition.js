window.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const select = document.getElementById("plant-stages");

    // Change default values based on selected growth stage
    select.addEventListener("change", function () {
        const selectedStage = this.value;
        const values = window.nutritionDefaults[selectedStage];

        if (!values) return;

        document.getElementById("soilN").value = values.soilN;
        document.getElementById("soilP").value = values.soilP;
        document.getElementById("soilK").value = values.soilK;
        document.getElementById("soilEC").value = values.soilEC;
        document.getElementById("soilPH").value = values.soilPH;
        document.getElementById("soilT").value = values.soilT;
        document.getElementById("soilM").value = values.soilM;
        document.getElementById("flowRate").value = values.flowRate;

        const firstFertilizer = document.querySelector('input[name="fertilizer[]"]');
        const firstAmount = document.querySelector('input[name="fertilizerAmount[]"]');

        if (firstFertilizer) {
            firstFertilizer.value = values.fertilizer;
        }

        if (firstAmount) {
            firstAmount.value = values.fertilizerAmount;
        }

        const container = document.getElementById("fertilizerContainer");

        // Remove all fertilizer groups except first
        container.innerHTML = '';

        const newGroup = document.createElement("div");
        newGroup.classList.add("fertilizer-group");

        newGroup.innerHTML = `
            <div class="form-group">
                <label><i class="fas fa-poo-storm"></i> Fertilizer</label>
                <input 
                    type="text" 
                    name="fertilizer[]"
                    placeholder="Enter fertilizer name"
                    value="${values.fertilizer}"
                >
            </div>
            <div class="form-group">
                <label><i class="fas fa-weight-hanging"></i> Fertilizer Amount (g/L)</label>
                <input 
                    type="number" 
                    name="fertilizerAmount[]" 
                    placeholder="Enter fertilizer amount"
                    value="${values.fertilizerAmount}"
                >
            </div>
        `;

        container.appendChild(newGroup);
    });

    // Reset form to default values when reset button is clicked
    form.addEventListener("reset", function () {
        setTimeout(() => {
            // Trigger stage change again
            select.dispatchEvent(new Event("change"));
        }, 0);
    });

    select.dispatchEvent(new Event("change"));
});

function addFertilizer() {
    const container = document.getElementById("fertilizerContainer");
    const firstGroup = container.querySelector(".fertilizer-group");

    // Clone the first group
    const newGroup = firstGroup.cloneNode(true);

    // Clear values inside cloned inputs
    const inputs = newGroup.querySelectorAll("input");
    inputs.forEach(input => input.value = "");

    container.appendChild(newGroup);
}