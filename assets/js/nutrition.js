window.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const select = document.getElementById("saved-nutrition-set");

    // Change default values based on selected growth stage
    select.addEventListener("change", function () {
        const setName = this.value;

        fetch(`../api/fetch_nutrition_set.php?setName=${setName}`)
        .then(res => res.json())
        .then(data => {
            if (!data.nutrition) return;

            document.getElementById("plant-stage").value = data.nutrition.growthStage;
            document.getElementById("soilN").value = data.nutrition.soilN;
            document.getElementById("soilP").value = data.nutrition.soilP;
            document.getElementById("soilK").value = data.nutrition.soilK;
            document.getElementById("soilEC").value = data.nutrition.soilEC;
            document.getElementById("soilPH").value = data.nutrition.soilPH;
            document.getElementById("soilT").value = data.nutrition.soilT;
            document.getElementById("soilM").value = data.nutrition.soilM;
            document.getElementById("flowRate").value = data.nutrition.flowRate;

            const container = document.getElementById("fertilizerContainer");
            container.innerHTML = "";

            if (data.fertilizers.length === 0) {
                const div = document.createElement("div");
                div.classList.add("fertilizer-group");

                div.innerHTML = `
                    <div class="form-group">
                        <label><i class="fas fa-poo-storm"></i> Fertilizer</label>
                        <input 
                            type="text" 
                            name="fertilizer[]"
                            placeholder="Enter fertilizer name"
                        >
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-weight-hanging"></i> Fertilizer Amount (g/L)</label>
                        <input 
                            type="number" 
                            name="fertilizerAmount[]" 
                            step="any"
                            min="0"
                            placeholder="Enter fertilizer amount"
                        >
                    </div>
                `;
                container.appendChild(div);
            } 
            else {
                data.fertilizers.forEach(f => {
                    const div = document.createElement("div");
                    div.classList.add("fertilizer-group");

                    div.innerHTML = `
                        <div class="form-group">
                            <label><i class="fas fa-poo-storm"></i> Fertilizer</label>
                            <input 
                                type="text" 
                                name="fertilizer[]"
                                placeholder="Enter fertilizer name"
                                value="${f.fertilizerName}"
                            >
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-weight-hanging"></i> Fertilizer Amount (g/L)</label>
                            <input 
                                type="number" 
                                name="fertilizerAmount[]" 
                                step="any"
                                min="0"
                                placeholder="Enter fertilizer amount"
                                value="${f.fertilizerAmount}"
                            >
                        </div>
                    `;

                    container.appendChild(div);
                });
            }
        });
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

    let firstGroup = container.querySelector(".fertilizer-group");

    // If no fertilizer group exists, create one
    if (!firstGroup) {

        const div = document.createElement("div");
        div.classList.add("fertilizer-group");

        div.innerHTML = `
            <div class="form-group">
                <label><i class="fas fa-poo-storm"></i> Fertilizer</label>
                <input 
                    type="text" 
                    name="fertilizer[]"
                    placeholder="Enter fertilizer name"
                >
            </div>
            <div class="form-group">
                <label><i class="fas fa-weight-hanging"></i> Fertilizer Amount (g/L)</label>
                <input 
                    type="number" 
                    name="fertilizerAmount[]" 
                    step="any"
                    min="0"
                    placeholder="Enter fertilizer amount"
                >
            </div>
        `;

        container.appendChild(div);
        return;
    }

    const newGroup = firstGroup.cloneNode(true);

    const inputs = newGroup.querySelectorAll("input");
    inputs.forEach(input => input.value = "");

    container.appendChild(newGroup);
}