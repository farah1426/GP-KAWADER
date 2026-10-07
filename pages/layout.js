// Kawader - shared code used by every page: header and footer

function byId(id) {
  return document.getElementById(id);
}

// Menu of the candidate pages (add one line to add a page)
const candidateMenu = [
  { id: "jobs", name: "Jobs", link: "jobs.html" },
  { id: "profile", name: "Profile", link: "profile.html" }
];

// Menu of the HR pages (add one line to add a page)
const hrMenu = [
  { id: "hr-jobs", name: "Job Postings", link: "hr-jobs.html" },
  { id: "hr-candidates", name: "Candidates", link: "hr-candidates.html" }
];

function buildHeader(menu, activePage) {
  return `
    <header class="site-header">
      <div class="header-inner">
        <a href="${menu[0].link}"><img src="../images/KawaderLogo.png" alt="Kawader" class="logo"></a>
        <nav class="nav">
          ${menu.map(page =>
            `<a href="${page.link}" class="${page.id === activePage ? "active" : ""}">${page.name}</a>`
          ).join("")}
        </nav>
        <div class="auth-buttons">
          <a href="home.html" class="auth-link">Sign Out</a>
        </div>
      </div>
    </header>`;
}

const footerHtml = `
  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div>
          <img src="../images/KawaderLogoLight.png" alt="Kawader" class="footer-logo">
          <p>An AI-powered applicant tracking system that connects candidates with HR professionals. The final hiring decision always stays with HR.</p>
          <div class="footer-social">
            <a href="#" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
            <a href="#" aria-label="X"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
            <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg></a>
          </div>
        </div>
        <div>
          <h4>Contact</h4>
          <span>support@kawader.com</span>
          <span>+966 11 000 0000</span>
        </div>
        <div>
          <h4>Good to know</h4>
          <span>Web-based platform, available in English</span>
          <span>CVs are accepted as PDF, up to 5 MB</span>
          <span>For users aged 18 and above</span>
        </div>
      </div>
      <div class="copyright">&copy; 2026 Kawader. All rights reserved.</div>
    </div>
  </footer>`;

// Fill the boxes of the page
const headerBox = byId("site-header");
if (headerBox) {
  const menu = headerBox.dataset.menu === "hr" ? hrMenu : candidateMenu;
  headerBox.innerHTML = buildHeader(menu, headerBox.dataset.active);
}

const footerBox = byId("site-footer");
if (footerBox) footerBox.innerHTML = footerHtml;
