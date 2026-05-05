/**
 * Simulated auth: sign up, log in, log out (sessionStorage only)
 */
(function (global) {
  const { generateId, load, save } = global.Storage;
  const { toast } = global.UI;

  function normalizeEmail(email) {
    return String(email || "").trim().toLowerCase();
  }

  function showAppShell() {
    const authRoot = document.getElementById("auth-root");
    const app = document.getElementById("app");
    if (authRoot) authRoot.hidden = true;
    if (app) app.hidden = false;
  }

  function showAuthShell() {
    const authRoot = document.getElementById("auth-root");
    const app = document.getElementById("app");
    if (authRoot) authRoot.hidden = false;
    if (app) app.hidden = true;
  }

  function renderAuth() {
    const root = document.getElementById("auth-root");
    if (!root) return;

    root.innerHTML =
      '<div class="auth-layout">' +
      '<div class="auth-card">' +
      '<div class="auth-brand"><span class="logo-mark" style="margin:0 auto 1rem;">✈</span>' +
      '<h1 class="auth-title">TripSync</h1>' +
      '<p class="muted auth-tagline">Collaborative trip workspace — sign in to continue</p></div>' +
      '<div class="tabs auth-tabs" id="auth-tabs">' +
      '<button type="button" class="tab is-active" data-auth-tab="login">Log in</button>' +
      '<button type="button" class="tab" data-auth-tab="signup">Sign up</button>' +
      "</div>" +
      '<div class="tab-panel" data-auth-panel="login">' +
      '<div class="form-grid" style="margin-top:1rem;">' +
      '<div class="form-row"><label for="login-email">Email</label>' +
      '<input id="login-email" type="email" class="input" autocomplete="username" placeholder="you@company.com" /></div>' +
      '<div class="form-row"><label for="login-pass">Password</label>' +
      '<input id="login-pass" type="password" class="input" autocomplete="current-password" /></div>' +
      '<button type="button" class="btn btn--primary" style="width:100%;margin-top:0.5rem;" id="btn-login">Log in</button>' +
      "</div></div>" +
      '<div class="tab-panel" data-auth-panel="signup" hidden>' +
      '<div class="form-grid" style="margin-top:1rem;">' +
      '<div class="form-row"><label for="su-name">Full name</label>' +
      '<input id="su-name" class="input" placeholder="Alex Rivera" /></div>' +
      '<div class="form-row"><label for="su-email">Email</label>' +
      '<input id="su-email" type="email" class="input" autocomplete="email" /></div>' +
      '<div class="form-row"><label for="su-pass">Password</label>' +
      '<input id="su-pass" type="password" class="input" autocomplete="new-password" /></div>' +
      '<button type="button" class="btn btn--primary" style="width:100%;margin-top:0.5rem;" id="btn-signup">Create account</button>' +
      "</div></div>" +
      "</div></div>";

    const tabs = root.querySelectorAll("#auth-tabs .tab");
    const panels = root.querySelectorAll("[data-auth-panel]");
    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        const id = tab.getAttribute("data-auth-tab");
        tabs.forEach((t) => t.classList.toggle("is-active", t === tab));
        panels.forEach((p) => {
          p.hidden = p.getAttribute("data-auth-panel") !== id;
        });
      });
    });

    root.querySelector("#btn-login")?.addEventListener("click", () => {
      const email = normalizeEmail(root.querySelector("#login-email").value);
      const password = root.querySelector("#login-pass").value;
      if (!email || !password) {
        toast("Enter email and password");
        return;
      }
      const state = load();
      state.users = state.users || [];
      const user = state.users.find((u) => normalizeEmail(u.email) === email);
      if (!user || user.password !== password) {
        toast("Invalid email or password");
        return;
      }
      state.session = { userId: user.id };
      save(state);
      toast("Welcome back, " + user.name);
      global.Auth.afterLogin();
    });

    root.querySelector("#btn-signup")?.addEventListener("click", () => {
      const name = root.querySelector("#su-name").value.trim();
      const email = normalizeEmail(root.querySelector("#su-email").value);
      const password = root.querySelector("#su-pass").value;
      if (!name || !email || !password) {
        toast("Fill in name, email, and password");
        return;
      }
      const state = load();
      state.users = state.users || [];
      if (state.users.some((u) => normalizeEmail(u.email) === email)) {
        toast("An account with this email already exists");
        return;
      }
      const user = { id: generateId("u"), name, email, password };
      state.users.push(user);
      state.session = { userId: user.id };
      save(state);
      toast("Account created — you're signed in");
      global.Auth.afterLogin();
    });
  }

  function logout() {
    const state = load();
    state.session = { userId: null };
    save(state);
    toast("Signed out");
    window.location.reload();
  }

  function isLoggedIn() {
    const state = load();
    return !!(
      state.session &&
      state.session.userId &&
      Array.isArray(state.users) &&
      getUser(state, state.session.userId)
    );
  }

  function getUser(state, userId) {
    return state.users && state.users.find((u) => u.id === userId);
  }

  function afterLogin() {
    showAppShell();
    global.App.bootstrapAfterAuth();
  }

  function init() {
    if (isLoggedIn()) {
      showAppShell();
      global.App.bootstrapAfterAuth();
    } else {
      showAuthShell();
      renderAuth();
    }
  }

  global.Auth = {
    init,
    renderAuth,
    logout,
    isLoggedIn,
    afterLogin,
    normalizeEmail,
  };

  if (typeof document !== "undefined") {
    function boot() {
      try {
        init();
      } catch (err) {
        console.error("TripSync failed to start:", err);
        var ar = document.getElementById("auth-root");
        if (ar) {
          ar.hidden = false;
          ar.innerHTML =
            '<div class="auth-layout"><div class="auth-card"><h1 class="auth-title">Something went wrong</h1>' +
            "<p class=\"muted\">The app hit an error while starting. Open the browser console (F12) for details.</p>" +
            '<p class="muted" style="font-size:0.85rem;">Try clearing site data for this page if the problem persists.</p></div></div>';
        }
        var ap = document.getElementById("app");
        if (ap) ap.hidden = true;
      }
    }
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", boot);
    } else {
      boot();
    }
  }
})(typeof window !== "undefined" ? window : globalThis);