window.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const select = document.getElementById("plant-stages");
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
            const stage = select.value;
            const fertilizers = window.nutritionDefaults[stage].fertilizer;

            sel.innerHTML = "";

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
        });
    }

    // Added isRemovable parameter (defaults to true)
    function createFertilizerGroup(fertName = "", amount = "", isRemovable = true) {
        const group = document.createElement("div");
        group.classList.add("fertilizer-group");

        // Conditionally include the remove button HTML
        const removeButtonHTML = isRemovable 
            ? `<button type="button" class="remove-fert-btn"><i class="fas fa-trash"></i> Remove</button>` 
            : ``;

        group.innerHTML = `
            <div class="fert-input-row">
                <div class="form-group">
                    <label><i class="fas fa-poo-storm"></i> Fertilizer</label>
                    <select name="fertilizer[]" class="fertilizer-select"></select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-weight-hanging"></i> Fertilizer Amount (g/L)</label>
                    <input 
                        type="number" 
                        name="fertilizerAmount[]" 
                        placeholder="Enter fertilizer amount"
                        value="${amount}"
                    >
                </div>
            </div>
            ${removeButtonHTML}
        `;

        const selectEl = group.querySelector(".fertilizer-select");
        const amountInput = group.querySelector('input[name="fertilizerAmount[]"]');

        const stage = select.value;
        const ferts = window.nutritionDefaults[stage].fertilizer;
        const amounts = window.nutritionDefaults[stage].fertilizerAmount;

        // Populate initial options for this specific select
        ferts.forEach((fert, index) => {
            const opt = document.createElement("option");
            opt.value = fert;
            opt.textContent = fert;
            if (fert === fertName || (!fertName && index === 0)) {
                opt.selected = true;
                amountInput.value = amount || amounts[index];
            }
            selectEl.appendChild(opt);
        });

        // Update amounts when a new fertilizer is chosen
        selectEl.addEventListener("change", function () {
            const fertIndex = ferts.indexOf(this.value);
            if (fertIndex !== -1) {
                amountInput.value = amounts[fertIndex];
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

    function resetFertilizers() {
        container.innerHTML = "";

        const stage = select.value;
        const values = window.nutritionDefaults[stage];

        // Create the first group and pass 'false' so it cannot be removed
        const firstGroup = createFertilizerGroup(
            values.fertilizer[0],
            values.fertilizerAmount[0],
            false 
        );

        container.appendChild(firstGroup);
        updateFertilizerOptions();
    }

    select.addEventListener("change", function () {
        const values = window.nutritionDefaults[this.value];

        document.getElementById("soilN").value = values.soilN;
        document.getElementById("soilP").value = values.soilP;
        document.getElementById("soilK").value = values.soilK;
        document.getElementById("soilEC").value = values.soilEC;
        document.getElementById("soilPH").value = values.soilPH;
        document.getElementById("soilT").value = values.soilT;
        document.getElementById("soilM").value = values.soilM;
        document.getElementById("flowRate").value = values.flowRate;

        resetFertilizers();
    });

    form.addEventListener("reset", function () {
        setTimeout(() => {
            select.dispatchEvent(new Event("change"));
        }, 0);
    });

    window.addFertilizer = function () {
        const stage = select.value;
        const values = window.nutritionDefaults[stage];

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

        // Pass 'true' (or omit the 3rd argument since it defaults to true) for removable groups
        const group = createFertilizerGroup(nextFertName, nextAmount, true);
        container.appendChild(group);

        updateFertilizerOptions();
    };
    select.dispatchEvent(new Event("change"));
});