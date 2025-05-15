document.addEventListener("DOMContentLoaded", function () {
    const message = document.createElement("p");
    message.textContent = "JavaScript is working!";
    message.style.color = "#2ecc71";
    message.style.fontWeight = "bold";
    message.style.fontSize = "18px";
    document.body.appendChild(message);
});