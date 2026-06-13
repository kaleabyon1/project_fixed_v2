document.addEventListener("DOMContentLoaded", () => {
    const productContainer = document.getElementById("product-list");

    fetch('../backend/php/fetch_products.php')
        .then(response => {
            if (!response.ok) throw new Error(`Server returned ${response.status}`);
            return response.text();
        })
        .then(text => {
            let products;
            try {
                products = JSON.parse(text);
            } catch (e) {
                console.error("PHP returned non-JSON response:\n", text);
                throw new Error("Server returned invalid JSON. Check browser console for details.");
            }

            if (products && products.error) {
                throw new Error(products.message || "Error loading products.");
            }

            if (!Array.isArray(products) || products.length === 0) {
                productContainer.innerHTML = "<p>No products found. Add some from the Admin Panel.</p>";
                return;
            }

            productContainer.innerHTML = "";

            const imgUrl = (raw) => {
                if (!raw) return "../backend/uploads/placeholder.jpg";
                const file = String(raw).split("/").pop();
                return "../backend/uploads/" + file;
            };

            products.forEach(product => {
                const finalSrc = imgUrl(product.image);

                const card = document.createElement("div");
                card.className = "product-card";

                const img = document.createElement("img");
                img.src = finalSrc;
                img.alt = product.name;
                img.className = "product-image";
                img.onerror = () => { img.src = "../backend/uploads/placeholder.jpg"; };

                const title = document.createElement("h3");
                title.textContent = product.name;

                const price = document.createElement("p");
                price.className = "price";
                price.textContent = "$" + parseFloat(product.price).toFixed(2);

                const btn = document.createElement("button");
                btn.textContent = "Add to Cart";

                btn.addEventListener("click", () => {
                    addToCart(product.id, product.name, parseFloat(product.price), finalSrc);
                });

                card.append(img, title, price, btn);
                productContainer.appendChild(card);
            });
        })
        .catch(error => {
            console.error('Error loading products:', error);
            productContainer.innerHTML = `
                <p style="color:red; padding:20px;">
                     Error loading products. <br>
                    <small>Open browser DevTools (F12) → Console for details.</small>
                </p>`;
        });
});

function addToCart(id, name, price, image) {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    let existing = cart.find(item => item.id === id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({ id, name, price, image, quantity: 1 });
    }
    localStorage.setItem("cart", JSON.stringify(cart));
    alert(`${name} added to cart!`);
}
