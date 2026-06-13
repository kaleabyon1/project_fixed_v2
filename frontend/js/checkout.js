document.addEventListener("DOMContentLoaded", () => {

    const cart = JSON.parse(localStorage.getItem("cart")) || [];
    const container = document.getElementById("checkout-container");
    const form = document.getElementById("checkout-form");

    if (cart.length === 0) {
        container.innerHTML = "<p>Your cart is empty. <a href='index.html'>Go Shopping</a></p>";
        if (form) form.style.display = "none";
        return;
    }

    // SECURITY FIX (HIGH — XSS):
    // The old code injected item.name and item.image into the page with
    // innerHTML template literals. If a product name ever contained HTML,
    // that became stored XSS. We now build each row with createElement and
    // set all text via textContent, which is never parsed as HTML.
    container.innerHTML = "";

    const list = document.createElement("div");
    list.className = "checkout-list";
    list.style.cssText = "margin-bottom:20px; text-align:left; border:1px solid #ddd; padding:15px; border-radius:8px; background:#fff;";

    let total = 0;
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;

        const row = document.createElement("div");
        row.style.cssText = "display:flex; align-items:center; border-bottom:1px solid #eee; padding:10px 0;";

        const img = document.createElement("img");
        img.src = item.image || "../backend/php/uploads/placeholder.jpg";
        img.style.cssText = "width:60px; height:60px; object-fit:cover; border-radius:4px; margin-right:15px; border:1px solid #ccc;";

        const info = document.createElement("div");
        info.style.flexGrow = "1";
        const nameEl = document.createElement("strong");
        nameEl.style.fontSize = "1.1em";
        nameEl.textContent = item.name;               // safe
        const sub = document.createElement("small");
        sub.style.color = "#666";
        sub.textContent = `$${parseFloat(item.price).toFixed(2)} x ${item.quantity}`;
        info.append(nameEl, document.createElement("br"), sub);

        const lineTotal = document.createElement("div");
        lineTotal.style.cssText = "font-weight:bold; color:#333;";
        lineTotal.textContent = `$${itemTotal.toFixed(2)}`;

        row.append(img, info, lineTotal);
        list.appendChild(row);
    });

    const totalRow = document.createElement("div");
    totalRow.style.cssText = "margin-top:15px; text-align:right; font-size:1.2em;";
    totalRow.innerHTML = `<strong>Total to Pay: <span style="color:green;">$${total.toFixed(2)}</span></strong>`;
    list.appendChild(totalRow);

    container.appendChild(list);

    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const orderData = {
                name: document.getElementById("name").value,
                address: document.getElementById("address").value,
                payment: document.getElementById("payment").value,
                cart: cart
            };

            fetch('../backend/php/process_checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(orderData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    alert("✅ Order Placed Successfully!");
                    localStorage.removeItem("cart");
                    window.location.href = "order_success.html";
                } else {
                    alert("❌ Error: " + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("An error occurred while placing the order.");
            });
        });
    }
});
