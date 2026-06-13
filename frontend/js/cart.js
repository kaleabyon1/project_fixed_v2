document.addEventListener("DOMContentLoaded", () => {
    loadCart();
});

function loadCart() {
    const container = document.getElementById("cart-items-container");
    const totalElement = document.getElementById("cart-total-price");

    let cart = JSON.parse(localStorage.getItem("cart")) || [];

    if (cart.length === 0) {
        container.innerHTML = "<p>Your cart is empty. <a href='index.php'>Go Shopping</a></p>";
        if(totalElement) totalElement.innerText = "0.00";
        return;
    }

    container.innerHTML = cart.map((item, index) => {
        let imgSrc = item.image || '../backend/php/uploads/placeholder.jpg';
        let itemTotal = item.price * item.quantity;
        return `
        <div class="cart-item">
            <img src="${imgSrc}" alt="${item.name}">
            <div class="item-details">
                <h3>${item.name}</h3>
                <p>$${parseFloat(item.price).toFixed(2)}</p>
            </div>
            <div class="item-quantity">
                <button onclick="changeQty(${index}, -1)">-</button>
                <span>${item.quantity}</span>
                <button onclick="changeQty(${index}, 1)">+</button>
            </div>
            <div class="item-total">$${itemTotal.toFixed(2)}</div>
            <button class="remove-btn" onclick="removeItem(${index})">X</button>
        </div>
        `;
    }).join('');

    let finalTotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    if(totalElement) totalElement.innerText = finalTotal.toFixed(2);
}

function changeQty(index, change) {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    cart[index].quantity += change;

    if (cart[index].quantity <= 0) {
        cart.splice(index, 1);
    }
    
    localStorage.setItem("cart", JSON.stringify(cart));
    loadCart(); 
}

function removeItem(index) {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    cart.splice(index, 1);
    localStorage.setItem("cart", JSON.stringify(cart));
    loadCart();
}