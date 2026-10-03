// Kawader - shared helper, header and footer (used by every page)

// Short helper: byId("x") = document.getElementById("x")
function byId(id) {
  return document.getElementById(id);
}
// Usage inside a page:  ${renderHeader("jobs")}  ...page content...  ${renderFooter()}

// To add a page to the menu, add one line here
const menuPages = [
  { id: "jobs", name: "Jobs", link: "#jobs" },
  { id: "profile", name: "Profile", link: "#profile" }
];

// activePage = id of the current page (gets the dark purple underline)
function renderHeader(activePage) {
  return `
    <header class="site-header">
      <div class="header-inner">
        <a href="#home"><img src="../images/KawaderLogo.png" alt="Kawader" class="logo"></a>
        <nav class="nav">
          ${menuPages.map(page =>
            `<a href="${page.link}" class="${page.id === activePage ? "active" : ""}">${page.name}</a>`
          ).join("")}
        </nav>
      </div>
    </header>`;
}

function renderFooter() {
  return `
    <footer class="site-footer">
      <div class="container">
        <div class="footer-grid">
          <div>
            <img src="../images/KawaderLogoLight.png" alt="Kawader" class="footer-logo">
            <p>An AI-assisted recruitment platform connecting job seekers with the right employers.</p>
          </div>
          <div>
            <h4>Quick Links</h4>
            ${menuPages.map(page => `<a href="${page.link}">${page.name}</a>`).join("")}
          </div>
          <div><h4>Contact</h4><span>support@kawader.com</span><span>+966 11 000 0000</span></div>
          <div><h4>Legal</h4><a href="#support">Support</a><a href="#privacy">Privacy</a><a href="#terms">Terms</a></div>
        </div>
        <div class="copyright">&copy; 2026 Kawader. All rights reserved.</div>
      </div>
    </footer>`;
}