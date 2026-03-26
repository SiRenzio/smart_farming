// Nutrition Modal
    const modal = document.getElementById("modal");
    const modalContent = document.getElementById("modal-content");
    const closeBtn = document.getElementById("close-btn");

    document.querySelectorAll(".details-btn").forEach(button => {
        button.addEventListener("click", () => {
            console.log("clicked");
            const data = button.dataset;

            // Parse fertilizers JSON
            let fertilizers = [];
            try {
                fertilizers = JSON.parse(data.fertilizers || "[]");
            } catch (e) {
                console.error("Invalid fertilizer JSON", e);
            }

            // Build fertilizer HTML
            let fertilizerHTML = "<p><i class='fas fa-poo-storm'></i><strong> Fertilizers:</strong></p>";

            if (fertilizers.length > 0) {
                fertilizerHTML += "<ul>";
                fertilizers.forEach(f => {
                    fertilizerHTML += `<li>${f.fertilizerName} (${f.fertilizerAmount}g)</li>`;
                });
                fertilizerHTML += "</ul>";
            } else {
                fertilizerHTML += "<p>-</p>";
            }

            modalContent.innerHTML = `
                <div class="detail-item"><span>Soil Type</span><span>${data.soil || '-'}</span></div>
                <div class="detail-item"><span>Soil Temperature:</span><span>30° | 31°-34° | 35°</span></div>
                <div class="detail-item"><span>Moisture</span><span>${data.moisture || '-'} | 
                    ${parseInt(data.moisture || 0) + 5} | 
                    ${parseInt(data.moisture || 0) + 10}</span></div>
                <div class="detail-item"><span>Growth Stage</span><span>${data.stage || '-'}</span></div>
                <div class="detail-item"><span>Plants</span><span>${data.plants || '-'}</span></div>
                <div class="detail-item"><span>Nitrogen (N)</span><span>${data.n || '-'}</span></div>
                <div class="detail-item"><span>Phosphorus (P)</span><span>${data.p || '-'}</span></div>
                <div class="detail-item"><span>Potassium (K)</span><span>${data.k || '-'}</span></div>
                <div class="detail-item"><span>EC</span><span>${data.ec || '-'}</span></div>
                <div class="detail-item"><span>pH</span><span>${data.ph || '-'}</span></div>
                <div class="detail-item"><span>Liquid Volume (ml)</span><span>${data.liquid || '-'} ml</span></div>

                <div class="fert-section">
                    <strong>Fertilizers</strong>
                    ${fertilizers.length > 0 
                        ? `<ul class="fert-list">
                            ${fertilizers.map(f => `<li>${f.fertilizerName} <span>${f.fertilizerAmount}g</span></li>`).join("")}
                        </ul>`
                        : `<p>-</p>`
                    }
                </div>
            `;

            modal.style.display = "block";
        });
    });

    closeBtn.addEventListener("click", () => {
        modal.style.display = "none";
    });

    modal.addEventListener("click", (e) => {
        if (e.target.classList.contains("modal-backdrop")) {
            modal.style.display = "none";
        }
    });