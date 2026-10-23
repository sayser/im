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

  const markPicker = document.querySelector("[data-mark-picker]");
  const markCatalogNode = document.querySelector("#mark-catalog");
  if (markPicker && markCatalogNode) {
    let catalog = [];
    try {
      catalog = JSON.parse(markCatalogNode.textContent || "[]");
    } catch (error) {
      catalog = [];
    }
    const kind = markPicker.querySelector("[data-mark-kind]");
    const os = markPicker.querySelector("[data-mark-os]");
    const zone = markPicker.querySelector("[data-mark-zone]");
    const deployment = markPicker.querySelector("[data-mark-deployment]");
    const query = markPicker.querySelector("[data-mark-q]");
    const list = markPicker.querySelector("[data-mark-list]");
    const menu = markPicker.querySelector("[data-mark-menu]");
    const toggle = markPicker.querySelector("[data-mark-toggle]");
    const all = markPicker.querySelector("[data-mark-all]");
    const empty = markPicker.querySelector("[data-mark-empty]");
    const chosen = new Set();
    const keyOf = (vm) => `${vm.side}:${vm.id}`;
    const matches = () => catalog.filter((vm) => {
      if (kind.value === "app" && vm.side !== "app") return false;
      if (kind.value === "db" && vm.side !== "db") return false;
      if (os.value && String(vm.os_id) !== os.value) return false;
      if (zone.value && String(vm.zone_id) !== zone.value) return false;
      if (deployment.value && String(vm.environment_id) !== deployment.value) return false;
      const text = query.value.trim().toLowerCase();
      if (!text) return true;
      const haystack = [vm.name, vm.os, vm.zone, vm.deployment, vm.ip].join(" ").toLowerCase();
      return haystack.includes(text);
    });
    const visibleChosen = () => {
      const visible = new Set(matches().filter((vm) => !vm.added).map(keyOf));
      let count = 0;
      chosen.forEach((key) => {
        if (visible.has(key)) count += 1;
      });
      return count;
    };
    const summary = () => {
      const count = visibleChosen();
      toggle.textContent = count === 0 ? "Select VMs" : `${count} VM${count === 1 ? "" : "s"} selected`;
    };
    const paint = () => {
      const rows = matches();
      list.replaceChildren();
      rows.forEach((vm) => {
        const key = keyOf(vm);
        const label = document.createElement("label");
        label.className = "mark-pick";
        const input = document.createElement("input");
        input.type = "checkbox";
        if (vm.added) {
          input.checked = true;
          input.disabled = true;
        } else {
          input.name = "vm_ids[]";
          input.value = key;
          input.checked = chosen.has(key);
          input.addEventListener("change", () => {
            if (input.checked) chosen.add(key);
            else chosen.delete(key);
            summary();
            const selectable = rows.filter((row) => !row.added);
            all.checked = selectable.length > 0 && selectable.every((row) => chosen.has(keyOf(row)));
          });
        }
        const cell = (className, value) => {
          const span = document.createElement("span");
          span.className = className;
          span.textContent = value;
          return span;
        };
        const kindPill = document.createElement("span");
        kindPill.className = `pill-kind ${vm.side === "db" ? "db" : "app"}`;
        kindPill.textContent = vm.side === "db" ? "DB" : "APP";
        const name = document.createElement("span");
        name.className = "mark-name";
        name.append(cell("", vm.name));
        if (vm.added) name.append(cell("mark-added", "added"));
        label.append(
          input,
          kindPill,
          name,
          cell("mark-meta", vm.os),
          cell("mark-meta", vm.zone),
          cell("mark-meta", vm.deployment),
          cell("mono", vm.ip)
        );
        list.append(label);
      });
      empty.hidden = rows.length !== 0;
      const selectable = rows.filter((vm) => !vm.added);
      all.checked = selectable.length > 0 && selectable.every((vm) => chosen.has(keyOf(vm)));
      all.disabled = selectable.length === 0;
      summary();
    };
    all.addEventListener("change", () => {
      matches().forEach((vm) => {
        if (vm.added) return;
        const key = keyOf(vm);
        if (all.checked) chosen.add(key);
        else chosen.delete(key);
      });
      paint();
    });
    [kind, os, zone, deployment].forEach((field) => field.addEventListener("change", paint));
    query.addEventListener("input", paint);
    toggle.addEventListener("click", () => {
      menu.hidden = !menu.hidden;
    });
    document.addEventListener("click", (event) => {
      if (!markPicker.contains(event.target)) menu.hidden = true;
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") menu.hidden = true;
    });
    paint();
  }

  const refreshPicks = () => {
    const zone = zoneSelect ? zoneSelect.value : "";
    document.querySelectorAll("[data-filter-target]").forEach((input) => {
      const selector = input.getAttribute("data-filter-target");
      const box = selector ? document.querySelector(selector) : null;
      if (!box) return;
      const query = input.value.trim().toLowerCase();
      let shown = 0;
      const rows = box.querySelectorAll("[data-label]");
      rows.forEach((row) => {
        const rowZone = row.getAttribute("data-zone");
        const zoneOk = rowZone === null || (zone !== "" && rowZone === zone);
        const label = (row.getAttribute("data-label") || "").toLowerCase();
        const textOk = query === "" || label.includes(query);
        row.hidden = !(zoneOk && textOk);
        const checkbox = row.querySelector("input");
        if (checkbox) checkbox.disabled = !zoneOk;
        if (zoneOk && textOk) shown += 1;
      });
      const empty = input.parentElement ? input.parentElement.querySelector("[data-zone-empty]") : null;
      if (empty) empty.hidden = rows.length === 0 || shown !== 0;
    });
  };
  zoneSelect?.addEventListener("change", refreshPicks);
  document.querySelectorAll("[data-filter-target]").forEach((input) => {
    input.addEventListener("input", refreshPicks);
  });
  refreshPicks();

  document.querySelectorAll("[data-url-list]").forEach((list) => {
    const add = list.querySelector("[data-url-add]");
    add?.addEventListener("click", () => {
      const rows = list.querySelectorAll(".url-row");
      const last = rows[rows.length - 1];
      if (!last) return;
      const next = last.cloneNode(true);
      const input = next.querySelector("input");
      if (input) {
        input.value = "";
        input.removeAttribute("value");
        input.removeAttribute("aria-invalid");
      }
      last.after(next);
      input?.focus();
    });
    list.addEventListener("click", (event) => {
      const button = event.target.closest("[data-url-remove]");
      if (!button || !list.contains(button)) return;
      const row = button.closest(".url-row");
      if (!row) return;
      const rows = list.querySelectorAll(".url-row");
      if (rows.length <= 1) {
        const input = row.querySelector("input");
        if (input) input.value = "";
        return;
      }
      row.remove();
    });
  });
})();
