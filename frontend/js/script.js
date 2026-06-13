//makes the cart system work
let cart = JSON.parse(localStorage.getItem("cart")) || [];
async function loadProducts() {
    try {
        const response = await fetch("http://localhost/project/backend/php/fetch_products.php");
        const products = await response.json();
        const productsContainer = document.getElementById("products-container");
        productsContainer.innerHTML = "";
        products.forEach(product => {
            const productDiv = document.createElement("div");
            productDiv.classList.add("product");
            productDiv.innerHTML = `
                <img src="http://localhost/project/backend/uploads/${product.image}" alt="${product.name}">
                <p>${product.name}</p>
                <p>$${product.price}</p>
                <button onclick="addToCart(${product.id}, '${product.name}', ${product.price}, '${product.image}')">Add to Cart</button>
            `;
            productsContainer.appendChild(productDiv);
        });
    } catch (error) {
        console.error("Error loading products:", error);
        document.getElementById("products-container").innerHTML = "<p>Error loading products.</p>";
    }
}
document.addEventListener("DOMContentLoaded", loadProducts);

function addToCart(productId, productName, productPrice, productImage) {
    const existingProduct = cart.find(item => item.id === productId);
    if (existingProduct) {
        existingProduct.quantity += 1;
    } else {
        cart.push({
            id: productId,
            name: productName,
            price: productPrice,
            image: productImage,
            quantity: 1
        });
    }

    localStorage.setItem("cart", JSON.stringify(cart));
    updateCartUI();
}

function updateCartUI() {
    const cartContainer = document.getElementById("cart-container");
    if (!cartContainer) return;
    cartContainer.innerHTML = ""; // Clear
    if (cart.length === 0) {
        cartContainer.innerHTML = "<p>Your cart is empty.</p>";
        return;
    }
    cart.forEach((product, index) => {
        const productDiv = document.createElement("div");
        productDiv.classList.add("cart-item");

        productDiv.innerHTML = `
            <img src="http://localhost/project/backend/uploads/${product.image}" alt="${product.name}">
            <p>${product.name} - $${product.price} x ${product.quantity}</p>
            <button onclick="increaseQuantity(${index})">+</button>
            <button onclick="decreaseQuantity(${index})">-</button>
            <button onclick="removeFromCart(${index})">Remove</button>
        `;
        cartContainer.appendChild(productDiv);
    });

    const totalPrice = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    cartContainer.innerHTML += `<h2>Total: $${totalPrice.toFixed(2)}</h2>`;
}
function removeFromCart(index) {
    const confirmation = confirm("Are you sure you want to remove this item from your cart?");
    if (confirmation) {
        cart.splice(index, 1);
        localStorage.setItem("cart", JSON.stringify(cart));
        updateCartUI();
    }
}

function increaseQuantity(index) {
    cart[index].quantity += 1;
    localStorage.setItem("cart", JSON.stringify(cart));
    updateCartUI();
}

function decreaseQuantity(index) {
    if (cart[index].quantity > 1) {
        cart[index].quantity -= 1;
    } else {
        removeFromCart(index);
    }
    localStorage.setItem("cart", JSON.stringify(cart));
    updateCartUI();
}
document.addEventListener("DOMContentLoaded", updateCartUI);


