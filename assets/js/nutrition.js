window.addEventListener("DOMContentLoaded", () => {
    addFertilizer();
    const form = document.querySelector("form");
    const select = document.getElementById("saved-nutrition-set");
    const container = document.getElementById("fertilizerContainer");
    const template = document.getElementById("fertilizerTemplate");

    select.addEventListener("change", function () {
        const setName = this.value;

        fetch(`../api/fetch_nutrition_set.php?setName=${setName}`)
        .then(res => res.json())
        .then(data => {

            if (!data.nutrition) return;

            // Fill nutrition fields
            document.getElementById("plant-stage").value = data.nutrition.growthStage;
            document.getElementById("soilN").value = data.nutrition.soilN;
            document.getElementById("soilP").value = data.nutrition.soilP;
            document.getElementById("soilK").value = data.nutrition.soilK;
            document.getElementById("soilEC").value = data.nutrition.soilEC;
            document.getElementById("soilPH").value = data.nutrition.soilPH;
            document.getElementById("soilT").value = data.nutrition.soilT;
            document.getElementById("soilM").value = data.nutrition.soilM;
            document.getElementById("flowRate").value = data.nutrition.flowRate;

            container.innerHTML = "";

            if (!data.fertilizers || data.fertilizers.length === 0) {
                addFertilizer();
            } else {
                data.fertilizers.forEach(f => {
                    addFertilizer(f.fertilizerName, f.fertilizerAmount);
                });
            }

            fixFirstFertilizer();
        });
    });

    form.addEventListener("reset", () => {
        setTimeout(() => {
            select.dispatchEvent(new Event("change"));
        }, 0);
    });

    select.dispatchEvent(new Event("change"));
});


function addFertilizer(name = "", amount = "") {
    const container = document.getElementById("fertilizerContainer");
    const template = document.getElementById("fertilizerTemplate");

    const clone = template.content.cloneNode(true);

    clone.querySelector('input[name="fertilizer[]"]').value = name;
    clone.querySelector('input[name="fertilizerAmount[]"]').value = amount;

    container.appendChild(clone);

    fixFirstFertilizer();
}


function removeFertilizer(button) {
    const container = document.getElementById("fertilizerContainer");

    if (container.children.length > 1) {
        button.closest(".fertilizer-group").remove();
    }

    fixFirstFertilizer();
}


function fixFirstFertilizer() {
    const container = document.getElementById("fertilizerContainer");
    const groups = container.querySelectorAll(".fertilizer-group");

    groups.forEach((group, index) => {
        const btn = group.querySelector(".remove-fert-btn");

        if (!btn) return;

        if (index === 0) {
            btn.style.display = "none";
        } else {
            btn.style.display = "inline-block";
        }
    });
}