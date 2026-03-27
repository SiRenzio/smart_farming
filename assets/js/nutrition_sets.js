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

// USE BUTTON
document.querySelectorAll(".use-btn").forEach(button => { 
    button.addEventListener("click", () => { 
        const nutritionID = button.dataset.id; 
        
        // Hide all cancel buttons 
        document.querySelectorAll(".cancel-btn").forEach(cancel => { 
            cancel.style.display = "none"; 
            cancel.disabled = true; 
        }); 

        // Disable all buttons except for the clicked button
        document.querySelectorAll(".use-btn").forEach(btn => { 
            btn.disabled = true; 
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Use'; 
        }); 

            
        // Enable ONLY the clicked button 
        button.style.display = "inline-block";
        button.disabled = true; 
        button.disabled = true; button.textContent = "Processing..."; 
        console.log("Action is processing..."); 
        
        setTimeout(() => { 
            fetch('../api/set_nutritionneed_flag.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: `active=${nutritionID}`
            }) 
            .then(res => res.json()) 
            .then(response => { 
                console.log("Use response:", response);
                if (response.success) { 
                    button.innerHTML = '<i class="fas fa-check"></i> In Use'; 
                    const row = button.closest("tr"); 
                    const cancelBtn = row.querySelector(".cancel-btn"); 
                    cancelBtn.style.display = "inline-block"; 
                    cancelBtn.innerHTML = '<i class="fas fa-ban"></i> Cancel';
                    cancelBtn.disabled = false; 

                    // Hide all buttons
                    document.querySelectorAll(".use-btn").forEach(btn => { 
                        btn.style.display = "none";
                    }); 

                    button.style.display = "inline-block";
                    button.disabled = true; 
                } 
            }) 
            .catch(err => console.error("Fetch error:", err)); 
        }, 1000); // simulate delay 
    }); 
}); 

// CANCEL BUTTON (separate listener) 
document.querySelectorAll(".cancel-btn").forEach(cancelBtn => { 
    cancelBtn.addEventListener("click", () => { 
        const row = cancelBtn.closest("tr"); 
        const useBtn = row.querySelector(".use-btn"); 
        const nutritionID = useBtn.dataset.id; 
        
        console.log("Action cancelled"); 
        cancelBtn.disabled = true;
        cancelBtn.textContent = 'Cancelling...'
        
        setTimeout(() => { 
            fetch('../api/set_nutritionneed_flag.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: `inactive=${nutritionID}`
            }) 
            .then(res => res.json()) 
            .then(response => { 
                console.log("Cancel response:", response); 

                if (response.success) {

                    // Show ALL use buttons again
                    document.querySelectorAll(".use-btn").forEach(btn => { 
                        btn.style.display = "inline-block";
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Use'; 
                    }); 

                    // Hide all cancel buttons
                    document.querySelectorAll(".cancel-btn").forEach(c => { 
                        c.style.display = "none"; 
                        c.disabled = true; 
                    }); 
                }
            }) 
            .catch(err => console.error("Fetch error:", err)); 
        }, 1000); 
    }); 
});

// COMMENTED OUT IN CASE OF USER BEING ABLE TO USE MULTIPLE NUTRITION SETS
// document.querySelectorAll(".use-btn").forEach(button => {
//     button.addEventListener("click", () => {
//         const nutritionID = button.dataset.id;

//         const row = button.closest("tr");
//         const cancelBtn = row.querySelector(".cancel-btn");

//         // Disable only THIS button
//         button.disabled = true;
//         button.textContent = "Processing...";

//         console.log("Activating:", nutritionID);

//         fetch('../api/set_nutritionneed_flag.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/x-www-form-urlencoded'
//             },
//             body: `active=${nutritionID}`
//         })
//         .then(res => res.json())
//         .then(response => {
//             console.log("Use response:", response);

//             if (response.success) {
//                 button.innerHTML = '<i class="fas fa-check"></i> In Use';

//                 // Show cancel for THIS row only
//                 cancelBtn.style.display = "inline-block";
//                 cancelBtn.disabled = false;
//             } else {
//                 button.disabled = false;
//                 button.textContent = "Use";
//             }
//         })
//         .catch(err => {
//             console.error("Fetch error:", err);
//             button.disabled = false;
//             button.textContent = "Use";
//         });
//     });
// });

// // CANCEL BUTTON (separate listener)
// document.querySelectorAll(".cancel-btn").forEach(cancelBtn => {
//     cancelBtn.addEventListener("click", () => {

//         const row = cancelBtn.closest("tr");
//         const useBtn = row.querySelector(".use-btn");
//         const nutritionID = useBtn.dataset.id;

//         console.log("Deactivating:", nutritionID);

//         cancelBtn.disabled = true;
//         cancelBtn.textContent = "Cancelling...";

//         fetch('../api/set_nutritionneed_flag.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/x-www-form-urlencoded'
//             },
//             body: `inactive=${nutritionID}`
//         })
//         .then(res => res.json())
//         .then(response => {
//             console.log("Cancel response:", response);

//             if (response.success) {
//                 // Reset THIS row only
//                 useBtn.disabled = false;
//                 useBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Use';

//                 cancelBtn.style.display = "none";
//                 cancelBtn.disabled = true;
//                 cancelBtn.innerHTML = '<i class="fas fa-ban"></i> Cancel';
//             }
//         })
//         .catch(err => {
//             console.error("Fetch error:", err);
//             cancelBtn.disabled = false;
//             cancelBtn.textContent = "Cancel";
//         });
//     });
// });

// Restore button states when page reloads
window.addEventListener("DOMContentLoaded", () => {
    fetch('../api/get_active_nutritionsets.php')
    .then(res => res.json())
    .then(data => {
        if (!data.success) return;

        const activeID = data.active;

        // Show all buttons first
        document.querySelectorAll(".use-btn").forEach(btn => {
            btn.style.display = "inline-block";
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Use';
        });

        document.querySelectorAll(".cancel-btn").forEach(cancel => {
            cancel.style.display = "none";
            cancel.disabled = true;
        });

        if (activeID) {
            const activeBtn = document.querySelector(`.use-btn[data-id="${activeID}"]`);

            if (activeBtn) {
                const row = activeBtn.closest("tr");
                const cancelBtn = row.querySelector(".cancel-btn");

                // Hide all other use buttons
                document.querySelectorAll(".use-btn").forEach(btn => {
                    if (btn.dataset.id !== String(activeID)) {
                        btn.style.display = "none";
                    }
                });

                // Activate current
                activeBtn.style.display = "inline-block";
                activeBtn.innerHTML = '<i class="fas fa-check"></i> In Use';
                activeBtn.disabled = true;

                cancelBtn.style.display = "inline-block";
                cancelBtn.disabled = false;
            }
        }
    })
    .catch(err => console.error("Restore error:", err));
});