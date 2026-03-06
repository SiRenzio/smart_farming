window.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const select = document.getElementById("plant-stages");

    select.addEventListener("change", function () {
        const selectedStage = this.value;
        const values = window.nutritionDefaults[selectedStage];

        if (!values) return;

        // Update main nutrition parameters
        const params = ['soilN', 'soilP', 'soilK', 'soilEC', 'soilPH', 'soilT', 'soilM', 'flowRate'];
        params.forEach(param => {
            const el = document.getElementById(param);
            if (el) el.value = values[param];
        });

        const container = document.getElementById("fertilizerContainer");
        
        // Reset container to the default single row
        container.innerHTML = `
            <div class="fertilizer-group">
                <div class="fert-input-row">
                    <div class="form-group">
                        <label><i class="fas fa-poo-storm"></i> Fertilizer</label>
                        <input type="text" name="fertilizer[]" value="${values.fertilizer}">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-weight-hanging"></i> Fertilizer Amount (g/L)</label>
                        <input type="number" step="0.1" name="fertilizerAmount[]" value="${values.fertilizerAmount}">
                    </div>
                </div>
            </div>
        `;
    });

    form.addEventListener("reset", () => {
        setTimeout(() => select.dispatchEvent(new Event("change")), 0);
    });

    select.dispatchEvent(new Event("change"));
});

function addFertilizer() {
    const container = document.getElementById("fertilizerContainer");
    
    const newGroup = document.createElement("div");
    newGroup.className = "fertilizer-group";

    newGroup.innerHTML = `
        <div class="fert-input-row">
            <div class="form-group">
                <label><i class="fas fa-poo-storm"></i> Fertilizer</label>
                <input type="text" name="fertilizer[]" placeholder="Enter name">
            </div>
            <div class="form-group">
                <label><i class="fas fa-weight-hanging"></i> Amount (g/L)</label>
                <input type="number" step="0.1" name="fertilizerAmount[]" placeholder="0.0">
            </div>
        </div>
        <button type="button" class="remove-fert-btn" onclick="removeFertilizer(this)">
            <i class="fas fa-minus"></i> Remove
        </button>
    `;

    container.appendChild(newGroup);
}

function removeFertilizer(button) {
    button.parentElement.remove();
}