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

  const taskForm = document.querySelector("[data-task-form]");
  const taskPicker = document.querySelector("[data-task-picker]");
  const taskCatalogNode = document.querySelector("#task-catalog");
  if (taskForm && taskPicker && taskCatalogNode) {
    let taskCatalog = [];
    let taskSelected = [];
    try {
      taskCatalog = JSON.parse(taskCatalogNode.textContent || "[]");
    } catch (error) {
      taskCatalog = [];
    }
    const taskSelectedNode = document.querySelector("#task-selected");
    try {
      taskSelected = JSON.parse(taskSelectedNode ? taskSelectedNode.textContent || "[]" : "[]");
    } catch (error) {
      taskSelected = [];
    }
    const taskState = new Map();
    taskSelected.forEach((row) => {
      if (row && row.key) taskState.set(String(row.key), { checked: true, user: String(row.user || "") });
    });
    const taskFilters = {};
    taskPicker.querySelectorAll("[data-task-filter]").forEach((field) => {
      taskFilters[field.getAttribute("data-task-filter")] = field;
      field.addEventListener("keydown", (event) => {
        if (event.key === "Enter") event.preventDefault();
      });
    });
    const taskList = taskPicker.querySelector("[data-task-list]");
    const taskEmpty = taskPicker.querySelector("[data-task-empty]");
    const taskAll = taskPicker.querySelector("[data-task-all]");
    const taskCount = taskPicker.querySelector("[data-task-count]");
    const taskAssign = taskPicker.querySelector("[data-task-assign]");
    const taskError = taskPicker.querySelector("[data-task-client-error]");
    const taskTemplate = document.querySelector("#task-user-template");
    const taskScroller = taskPicker.querySelector(".task-pick-scroll");
    const taskKey = (vm) => `${vm.side}:${vm.id}`;
    const taskMatches = () => taskCatalog.filter((vm) => {
      if (taskFilters.side.value === "app" && vm.side !== "app") return false;
      if (taskFilters.side.value === "db" && vm.side !== "db") return false;
      if (taskFilters.zone_id.value && String(vm.zone_id) !== taskFilters.zone_id.value) return false;
      if (taskFilters.environment_id.value && String(vm.environment_id) !== taskFilters.environment_id.value) return false;
      if (taskFilters.os_id.value && String(vm.os_id) !== taskFilters.os_id.value) return false;
      if (taskFilters.client_id.value && String(vm.client_id) !== taskFilters.client_id.value) return false;
      if (taskFilters.tag.value === "__none__" && vm.tag) return false;
      if (taskFilters.tag.value && taskFilters.tag.value !== "__none__" && vm.tag !== taskFilters.tag.value) return false;
      const text = taskFilters.q.value.trim().toLowerCase();
      if (!text) return true;
      return [vm.name, vm.ip, vm.os, vm.zone, vm.deployment, vm.client, vm.tag].join(" ").toLowerCase().includes(text);
    });
    const taskEnsure = (key) => {
      if (!taskState.has(key)) taskState.set(key, { checked: false, user: "" });
      return taskState.get(key);
    };
    const taskCell = (className, value) => {
      const span = document.createElement("span");
      span.className = className;
      span.textContent = value || "—";
      return span;
    };
    const paintTasks = () => {
      const top = taskScroller ? taskScroller.scrollTop : 0;
      const rows = taskMatches();
      taskList.replaceChildren();
      rows.forEach((vm) => {
        const key = taskKey(vm);
        const rowState = taskEnsure(key);
        const row = document.createElement("div");
        row.className = "task-pick";
        const check = document.createElement("input");
        check.type = "checkbox";
        check.checked = rowState.checked;
        check.addEventListener("change", () => {
          rowState.checked = check.checked;
          paintTasks();
        });
        const kind = document.createElement("span");
        kind.className = `pill-kind ${vm.side === "db" ? "db" : "app"}`;
        kind.textContent = vm.side === "db" ? "DB" : "APP";
        const user = taskTemplate.cloneNode(true);
        user.removeAttribute("id");
        user.hidden = false;
        user.value = rowState.user;
        user.addEventListener("change", () => {
          rowState.user = user.value;
          if (user.value) rowState.checked = true;
          paintTasks();
        });
        row.append(
          check,
          kind,
          taskCell("task-name", vm.name),
          taskCell("mark-meta", vm.zone),
          taskCell("mark-meta", vm.deployment),
          taskCell("mark-meta", vm.os),
          taskCell("mark-meta", vm.tag),
          taskCell("mono", vm.ip),
          user
        );
        taskList.append(row);
      });
      taskEmpty.hidden = rows.length !== 0;
      const visibleKeys = rows.map(taskKey);
      taskAll.checked = visibleKeys.length > 0 && visibleKeys.every((key) => taskState.get(key)?.checked);
      taskAll.disabled = visibleKeys.length === 0;
      let selectedCount = 0;
      let hiddenCount = 0;
      const visible = new Set(visibleKeys);
      taskState.forEach((rowState, key) => {
        if (!rowState.checked) return;
        selectedCount += 1;
        if (!visible.has(key)) hiddenCount += 1;
      });
      taskCount.textContent = `${rows.length} shown · ${selectedCount} selected${hiddenCount ? ` · ${hiddenCount} hidden by the filter` : ""}`;
      if (taskScroller) taskScroller.scrollTop = top;
    };
    taskAll.addEventListener("change", () => {
      taskMatches().forEach((vm) => {
        taskEnsure(taskKey(vm)).checked = taskAll.checked;
      });
      paintTasks();
    });
    Object.values(taskFilters).forEach((field) => {
      field.addEventListener("input", paintTasks);
      field.addEventListener("change", paintTasks);
    });
    taskPicker.querySelector("[data-task-apply]").addEventListener("click", () => {
      taskError.hidden = true;
      if (!taskAssign.value) {
        taskError.textContent = "Choose a person, then assign the checked VMs.";
        taskError.hidden = false;
        return;
      }
      let any = false;
      taskMatches().forEach((vm) => {
        const rowState = taskEnsure(taskKey(vm));
        if (!rowState.checked) return;
        rowState.user = taskAssign.value;
        any = true;
      });
      if (!any) {
        taskError.textContent = "Check the VMs you want to assign.";
        taskError.hidden = false;
        return;
      }
      paintTasks();
    });
    taskForm.addEventListener("submit", (event) => {
      taskForm.querySelectorAll("[data-task-item]").forEach((node) => node.remove());
      const missing = [];
      taskState.forEach((rowState, key) => {
        if (!rowState.checked) return;
        if (!rowState.user) {
          missing.push(key);
          return;
        }
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "items[]";
        input.value = `${key}:${rowState.user}`;
        input.setAttribute("data-task-item", "1");
        taskForm.append(input);
      });
      if (missing.length || ![...taskState.values()].some((rowState) => rowState.checked)) {
        event.preventDefault();
        taskError.textContent = missing.length ? "Choose a person for every selected VM." : "Select at least one VM.";
        taskError.hidden = false;
      }
    });
    paintTasks();
  }

  document.querySelectorAll("[data-task-check]").forEach((input) => {
    input.addEventListener("change", () => {
      input.form?.requestSubmit();
    });
  });
})();
