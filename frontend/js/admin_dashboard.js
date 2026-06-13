// SECURITY/PORTABILITY FIX (LOW): replaced hardcoded http://localhost/project/...
// URLs with relative paths so the dashboard works on any machine and any folder name.

// BUG FIX: added null check - form element didn't exist when this ran, causing crash
// BUG FIX: fetch now points to add_product.php (was incorrectly pointing to admin.php)
const addProductForm = document.getElementById("add-product-form");
if (addProductForm) {
    addProductForm.addEventListener("submit", async function(event) {
        event.preventDefault();

        const formData = new FormData(this);

        try {
            const response = await fetch("../backend/php/add_product.php", {
                method: "POST",
                body: formData
            });
            const result = await response.json();
            alert(result.message);

            if (result.status === "success") {
                window.location.reload();
            }
        } catch (error) {
            console.error("Error adding product:", error);
            alert("An error occurred while adding the product.");
        }
    });
}

async function loadOrders() {
    try {
        console.log("Fetching orders...");

        const response = await fetch("../backend/php/get_orders.php");

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const orders = await response.json();
        console.log("Fetched orders:", orders);

        const container = document.getElementById("orders-container");
        container.innerHTML = "";

        if (!Array.isArray(orders) || orders.length === 0) {
            container.innerHTML = "<p>No orders found.</p>";
            return;
        }

        orders.forEach(order => {
            const orderDiv = document.createElement("div");
            orderDiv.classList.add("order");
            orderDiv.innerHTML = `
                <p><strong>Order #${order.id}</strong></p>
                <p>Total Price: $${order.total_price}</p>
                <p>Status: <span class="order-status status-pending">${order.status}</span></p>
            `;
            container.appendChild(orderDiv);
        });

        console.log("Orders loaded successfully.");
    } catch (error) {
        console.error("Error loading orders:", error);
        document.getElementById("orders-container").innerHTML = "<p>Error loading orders.</p>";
    }
}

document.addEventListener("DOMContentLoaded", loadOrders);
