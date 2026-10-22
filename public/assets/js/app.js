(() => {
  const toggle = document.querySelector("[data-nav-toggle]");
  const scrim = document.querySelector("[data-nav-scrim]");
  const closeNav = () => document.body.classList.remove("nav-open");

  toggle?.addEventListener("click", () => {
    document.body.classList.toggle("nav-open");
  });
  scrim?.addEventListener("click", closeNav);
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeNav();
  });

  document.querySelectorAll("form[data-confirm-delete]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      const submitter = event.submitter;
      if (!submitter || submitter.value !== "delete") return;
      const message = form.getAttribute("data-confirm-delete") || "Delete this value?";
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  document.querySelectorAll("form[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      const message = form.getAttribute("data-confirm") || "Continue?";
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  document.querySelectorAll("[data-width]").forEach((bar) => {
    const width = Number(bar.getAttribute("data-width"));
    if (Number.isFinite(width)) bar.style.width = `${Math.max(0, Math.min(100, width))}%`;
  });

  const catalogNode = document.querySelector("#stack-catalog");
  const zoneSelect = document.querySelector("[data-zone-select]");
  const stackList = document.querySelector("#stack-names");
  const refillStacks = () => {
    if (!catalogNode || !zoneSelect || !stackList) return;
    let data = {};
    try {
      data = JSON.parse(catalogNode.textContent || "{}");
    } catch (error) {
      data = {};
    }
    const names = Array.isArray(data[zoneSelect.value]) ? data[zoneSelect.value] : [];
    stackList.replaceChildren();
    names.forEach((name) => {
      const option = document.createElement("option");
      option.value = name;
      stackList.append(option);
    });
  };
  zoneSelect?.addEventListener("change", refillStacks);
  refillStacks();

  document.querySelectorAll("[data-filter-target]").forEach((input) => {
    const selector = input.getAttribute("data-filter-target");
    const box = selector ? document.querySelector(selector) : null;
    if (!box) return;
    input.addEventListener("input", () => {
      const query = input.value.trim().toLowerCase();
      box.querySelectorAll("[data-label]").forEach((row) => {
        const label = (row.getAttribute("data-label") || "").toLowerCase();
        row.hidden = query !== "" && !label.includes(query);
      });
    });
  });
})();
