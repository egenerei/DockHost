function togglePassword(fieldId, button) {
  const input = document.getElementById(fieldId);
  const isHidden = input.type === "password";
  input.type = isHidden ? "text" : "password";
  button.textContent = isHidden ? "🙈" : "👁️";
}
