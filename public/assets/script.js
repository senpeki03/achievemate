// Mobile navigation toggle
const toggle = document.querySelector(".nav-toggle");
const body = document.body;

function closeNav() {
  body.classList.remove("nav-open");
}
function openNav() {
  body.classList.add("nav-open");
}

if (toggle) {
  toggle.addEventListener("click", () => {
    body.classList.toggle("nav-open");
  });
}

// Close nav when clicking a link (mobile)
Array.from(document.querySelectorAll(".main-nav a")).forEach((a) => {
  a.addEventListener("click", () => {
    if (body.classList.contains("nav-open")) closeNav();
  });
});

// Close on escape
window.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeNav();
});

// Reset state on resize to desktop
window.addEventListener("resize", () => {
  if (window.innerWidth > 900) closeNav();
});
