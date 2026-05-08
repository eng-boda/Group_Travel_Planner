(function (global) {
  document.getElementById("btn-logout")?.addEventListener("click", function () {
    window.location.href = "/Website/view/Auth/logout.php";
  });
})(typeof window !== "undefined" ? window : globalThis);
