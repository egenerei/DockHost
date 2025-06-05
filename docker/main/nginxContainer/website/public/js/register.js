// Define SVG icons as strings
const eyeIcon = `
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20" >
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
       -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
</svg>`;

const eyeOffIcon = `
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20" >
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
    d="M13.875 18.825A10.05 10.05 0 0112 19
       c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 012.478-4.377
       m1.722-1.684A9.969 9.969 0 0112 5
       c4.478 0 8.268 2.943 9.542 7a9.987 9.987 0 01-4.12 5.591"/>
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
    d="M3 3l18 18"/>
</svg>`;

function togglePassword(fieldId, button) {
  const input = document.getElementById(fieldId);
  const isHidden = input.type === "password";
  input.type = isHidden ? "text" : "password";
  // Set button innerHTML to SVG icon
  button.innerHTML = isHidden ? eyeOffIcon : eyeIcon;
}

// Initialize buttons on page load
window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.toggle-password').forEach(button => {
    button.innerHTML = eyeIcon; // start with eye open icon
  });
});
