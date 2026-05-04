window.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const stageSelect = document.getElementById("plant-stage");
    const savedSetSelect = document.getElementById("saved-nutrition-set");
    const container = document.getElementById("fertilizerContainer");

    function updateFertilizerOptions() {
        const selected = [];

        // Collect all currently selected fertilizers
        document.querySelectorAll('select[name="fertilizer[]"]').forEach(sel => {
            if (sel.value) selected.push(sel.value);
        });

        // Rebuild options for each select element
        document.querySelectorAll('select[name="fertilizer[]"]').forEach(sel => {
            const current = sel.value;
            const stage = stageSelect.value;
            
            sel.innerHTML = '<option value="">Select a Fertilizer</option>';

            if (stage && fertilizerDefaults[stage]) {
                const fertilizers = fertilizerDefaults[stage].fertilizer;

                fertilizers.forEach((fert) => {
                    // Only add the option if it's not selected elsewhere, OR if it's the current input's value
                    if (!selected.includes(fert) || fert === current) {
                        const opt = document.createElement("option");
                        opt.value = fert;
                        opt.textContent = fert;
                        if (fert === current) opt.selected = true;
                        sel.appendChild(opt);
                    }
                });
            }
        });
    }

    // Creates the HTML structure for a fertilizer row
    function createFertilizerGroup(fertName = "", amount = "", isRemovable = true) {
        const group = document.createElement("div");
        group.classList.add("fertilizer-group");

        const removeButtonHTML = isRemovable 
            ? `<button type="button" class="remove-fert-btn"><i class="fas fa-minus"></i> Remove</button>` 
            : ``;

        group.innerHTML = `
            <div class="fert-input-row">
                <div class="form-group">
                    <label><i class="fas fa-poo-storm"></i> Fertilizer</label>
                    <select name="fertilizer[]" class="dropdown fertilizer-select"></select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-weight-hanging"></i> Amount (g/L)</label>
                    <input 
                        type="number" 
                        name="fertilizerAmount[]" 
                        step="0.1" 
                        min="0" 
                        placeholder="0.0"
                        value="${amount}"
                    >
                </div>
            </div>
            ${removeButtonHTML}
        `;

        const selectEl = group.querySelector(".fertilizer-select");
        const amountInput = group.querySelector('input[name="fertilizerAmount[]"]');

        const stage = stageSelect.value;

        // Populate initial options
        selectEl.innerHTML = '<option value="">Select a Fertilizer</option>';
        if (stage && fertilizerDefaults[stage]) {
            const ferts = fertilizerDefaults[stage].fertilizer;
            ferts.forEach((fert) => {
                const opt = document.createElement("option");
                opt.value = fert;
                opt.textContent = fert;
                if (fert === fertName) {
                    opt.selected = true;
                }
                selectEl.appendChild(opt);
            });
        }

        // Update amounts when a new fertilizer is chosen
        selectEl.addEventListener("change", function () {
            const currentStage = stageSelect.value;
            if (currentStage && fertilizerDefaults[currentStage]) {
                const ferts = fertilizerDefaults[currentStage].fertilizer;
                const amounts = fertilizerDefaults[currentStage].fertilizerAmount;
                const fertIndex = ferts.indexOf(this.value);
                
                if (fertIndex !== -1) {
                    amountInput.value = amounts[fertIndex];
                } else {
                    amountInput.value = "";
                }
            }
            updateFertilizerOptions();
        });

        // Only attach the remove event listener if the button exists
        if (isRemovable) {
            group.querySelector(".remove-fert-btn").addEventListener("click", () => {
                group.remove();
                updateFertilizerOptions();
            });
        }

        return group;
    }

    // Handle fetching saved nutrition sets
    savedSetSelect.addEventListener("change", function () {
        const setID = this.value;
        if (!setID) return;

        fetch(`../api/fetch_nutrition_set.php?setID=${setID}`)
        .then(res => res.json())
        .then(data => {
            if (!data.nutrition) return;

            // Fill nutrition fields
            document.getElementById("nutritionSetName").value = data.nutrition.nutritionSetName;
            soilTypeSelect.value = data.nutrition.soilType;
            soilTypeSelect.dispatchEvent(new Event("change"));
            stageSelect.value = data.nutrition.growthStage;
            document.getElementById("soilN").value = data.nutrition.soilN;
            document.getElementById("soilP").value = data.nutrition.soilP;
            document.getElementById("soilK").value = data.nutrition.soilK;
            document.getElementById("soilEC").value = data.nutrition.soilEC;
            document.getElementById("soilPH").value = data.nutrition.soilPH;
            document.getElementById("liquidVolume").value = data.nutrition.liquidVolume;
            document.getElementById("numberOfPlants").value = data.nutrition.numberOfPlants;

            container.innerHTML = ""; // Clear existing fertilizers

            if (!data.fertilizers || data.fertilizers.length === 0) {
                // Add one blank row if no fertilizers are saved
                container.appendChild(createFertilizerGroup("", "", false));
            } else {
                // Loop through and add saved fertilizers
                data.fertilizers.forEach((f, index) => {
                    const isRemovable = index !== 0; // First row cannot be removed
                    const group = createFertilizerGroup(f.fertilizerName, f.fertilizerAmount, isRemovable);
                    container.appendChild(group);
                });
            }

            updateFertilizerOptions();
        });
    });

    // When the Growth Stage changes, refresh the dropdowns and amounts
    stageSelect.addEventListener("change", function () {
        updateFertilizerOptions();
        // Trigger change on all existing fertilizer selects so they grab the new amounts for that stage
        document.querySelectorAll('select[name="fertilizer[]"]').forEach(sel => {
            if(sel.value) sel.dispatchEvent(new Event("change"));
        });
    });

    form.addEventListener("reset", function () {
        setTimeout(() => {
            container.innerHTML = "";
            container.appendChild(createFertilizerGroup("", "", false));
            updateFertilizerOptions();
        }, 0);
    });

    // Function attached to the "Add Fertilizer" button
    window.addFertilizer = function () {
        const stage = stageSelect.value;
        if (!stage) {
            alert("Please select a Growth Stage first.");
            return;
        }

        const values = fertilizerDefaults[stage];

        // Find which fertilizers are already selected
        const selectedFerts = Array.from(document.querySelectorAll('select[name="fertilizer[]"]')).map(sel => sel.value);
        
        // Filter out selected ones to find the next available fertilizer
        const availableFerts = values.fertilizer.filter(f => !selectedFerts.includes(f));

        // If all options are used, prevent adding an empty/duplicate row
        if (availableFerts.length === 0) {
            alert("All available fertilizers for this stage have been added.");
            return; 
        }

        const nextFertName = availableFerts[0];
        const nextFertIndex = values.fertilizer.indexOf(nextFertName);
        const nextAmount = values.fertilizerAmount[nextFertIndex];

        // add an input dropdown 
        const group = createFertilizerGroup(nextFertName, nextAmount, true);
        container.appendChild(group);

        updateFertilizerOptions();
    };

    const soilTypeSelect = document.getElementById("soil-type");
    const moistureDisplay = document.getElementById("soil-moisture-display");
    const hiddenInput = document.getElementById("soilM");

    soilTypeSelect.addEventListener("change", function () {

        const soilType = this.value;

        if (!soilType || !moistureThresholdValues[soilType]) {
            moistureDisplay.textContent = "Please Select a Soil Type";
            if(hiddenInput) hiddenInput.value = "";
            return;
        }

        const values = moistureThresholdValues[soilType];

        moistureDisplay.textContent = values;

        // optional: store the first threshold as the base value
        const firstValue = values.split(" - ")[0].replace("%","");

        if(hiddenInput){
            hiddenInput.value = firstValue;
        }

    });

    // default fertilizer input
    container.appendChild(createFertilizerGroup("", "", false));
    updateFertilizerOptions();
});