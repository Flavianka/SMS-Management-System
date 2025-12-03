
document.addEventListener("DOMContentLoaded", () => {
  const tableContainer = document.getElementById("contactsTable");
  
  const guardianSearchInput = document.getElementById("guardianSearchInput");
  const guardianSearchResults = document.getElementById("guardianSearchResults");
  const searchInput = document.getElementById("student_search");
  const suggestionsBox = document.getElementById("studentSuggestions");
  const addContactForm = document.getElementById("addContactForm");
  const editContactForm = document.getElementById("editContactForm");

  if (!tableContainer) {
    console.error("contactsTable element not found.");
    return;
  }

  let guardians = [];             // data from server
  let currentPage = 1;
  const rowsPerPage = 5;
  let currentSort = { column: null, asc: true };

  // -------------------------
  // Fetch guardians from server
  // -------------------------
  async function fetchGuardians() {
    try {
      const fd = new FormData();
      fd.append("action", "fetch");

      // append filters
      const grade = document.getElementById("filterGrade").value;
      const stream = document.getElementById("filterStream").value;
      const boarding = document.getElementById("filterBoarding").value;

      if (grade) fd.append("grade", grade);
      if (stream) fd.append("stream", stream);
      if (boarding) fd.append("boarding", boarding);



      const res = await fetch("contacts.php", { method: "POST", body: fd });
      const data = await res.json();
      

      if (data.status === "success" && Array.isArray(data.data)) {
        guardians = data.data;
        // Reset to page 1 if current page is out of range
        const totalPages = Math.max(1, Math.ceil(guardians.length / rowsPerPage));
        if (currentPage > totalPages) currentPage = totalPages;
        renderTable();
      } else {
        guardians = [];
        tableContainer.innerHTML = "<p>No guardians found.</p>";
      }
    } catch (err) {
      console.error("fetchGuardians error:", err);
      tableContainer.innerHTML = "<p>Error loading guardians.</p>";
    }
  }
  // Listen for filter changes and reload guardians
  document.getElementById("filterGrade").addEventListener("change", fetchGuardians);
  document.getElementById("filterStream").addEventListener("change", fetchGuardians);
  document.getElementById("filterBoarding").addEventListener("change", fetchGuardians);

  // -------------------------
  // Render table with pagination + sorting
  // -------------------------
  function renderTable() {
    // sort
    let sorted = [...guardians];
    if (currentSort.column) {
      sorted.sort((a, b) => {
        let A = (a[currentSort.column] ?? "").toString().toLowerCase();
        let B = (b[currentSort.column] ?? "").toString().toLowerCase();
        if (!isNaN(A) && !isNaN(B)) { A = Number(A); B = Number(B); }
        if (A < B) return currentSort.asc ? -1 : 1;
        if (A > B) return currentSort.asc ? 1 : -1;
        return 0;
      });
    }

    // page slice
    const totalPages = Math.max(1, Math.ceil(sorted.length / rowsPerPage));
    const start = (currentPage - 1) * rowsPerPage;
    const pageItems = sorted.slice(start, start + rowsPerPage);

    // table html
    let html = `<table class="contacts-table">
      <thead>
        <tr>
          <th data-col="guardian_name">Guardian</th>
          <th data-col="phone_number">Phone</th>
          <th data-col="student_name">Student</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>`;

    if (pageItems.length === 0) {
      html += `<tr><td colspan="4" style="text-align:center;padding:12px;">No records.</td></tr>`;
    } else {
      pageItems.forEach(g => {
        // escape minimal values for safety
        const guardian = g.guardian_name ?? "";
        const phone = g.phone_number ?? "";
        const student = g.student_name ?? "";

        html += `<tr>
          <td title="${guardian}">${guardian}</td>
          <td title="${phone}">${phone}</td>
          <td title="${student}">${student}</td>
          <td>
            <div class="action-buttons">
              <button class="edit-btn" data-id="${g.id}" data-guardian="${escapeAttr(guardian)}" data-phone="${escapeAttr(phone)}" data-student="${escapeAttr(student)}">Edit</button>
              <button class="delete-btn" data-id="${g.id}">Delete</button>
            </div>
          </td>
        </tr>`;
      });
    }

    html += `</tbody></table>`;

    // pagination (first, sliding window, last)
    let paginationHtml = `<div class="pagination">`;
    paginationHtml += `<button ${currentPage === 1 ? "disabled" : ""} data-goto="1">⏮</button>`;

    // sliding window: show up to 5 pages, with current centered when possible
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);
    if (endPage - startPage < 4) {
      if (startPage === 1) endPage = Math.min(totalPages, 5);
      else if (endPage === totalPages) startPage = Math.max(1, totalPages - 4);
    }
    for (let i = startPage; i <= endPage; i++) {
      paginationHtml += `<button class="${i === currentPage ? "active" : ""}" data-goto="${i}">${i}</button>`;
    }

    paginationHtml += `<button ${currentPage === totalPages ? "disabled" : ""} data-goto="${totalPages}">⏭</button>`;
    paginationHtml += `</div>`;

    html += paginationHtml;

    tableContainer.innerHTML = html;

    // attach header sort click handlers
    tableContainer.querySelectorAll("th[data-col]").forEach(th => {
      th.style.cursor = "pointer";
      th.onclick = () => {
        const col = th.getAttribute("data-col");
        if (currentSort.column === col) currentSort.asc = !currentSort.asc;
        else { currentSort.column = col; currentSort.asc = true; }
        renderTable();
      };
    });
  }

  // small helper to safely put into data-* attributes
  function escapeAttr(s) { return (s ?? "").replace(/"/g, "&quot;"); }

  // -------------------------
  // Event delegation for pagination / edit / delete
  // -------------------------
  tableContainer.addEventListener("click", (e) => {
    const btn = e.target.closest("button");
    if (!btn) return;

    // pagination buttons have data-goto
    if (btn.dataset.goto) {
      const page = Number(btn.dataset.goto);
      currentPage = page;
      renderTable();
      return;
    }

    // delete
    if (btn.classList.contains("delete-btn")) {
      const id = btn.dataset.id;
      deleteGuardian(id);
      return;
    }

    // edit
    if (btn.classList.contains("edit-btn")) {
      openEditModalFromButton(btn);
      return;
    }
  });

  // -------------------------
  // Delete guardian
  // -------------------------
  async function deleteGuardian(id) {
    if (!confirm("Delete this guardian?")) return;
    try {
      const fd = new FormData();
      fd.append("action", "delete");
      fd.append("id", id);
      const res = await fetch("contacts.php", { method: "POST", body: fd });
      const data = await res.json();
      if (data.status === "success") {
        fetchGuardians();
      } else {
        alert("Delete failed: " + (data.message || "unknown"));
      }
    } catch (err) {
      console.error("deleteGuardian error:", err);
      alert("Delete failed (see console).");
    }
  }

  // -------------------------
  // Edit modal open (from button)
  // -------------------------
  function openEditModalFromButton(btn) {
    const id = btn.dataset.id;
    const guardian = btn.dataset.guardian || "";
    const phone = btn.dataset.phone || "";
    const student = btn.dataset.student || "";

    // fill modal fields
    const idEl = document.getElementById("edit_guardian_id");
    const gEl = document.getElementById("edit_guardian_name");
    const pEl = document.getElementById("edit_phone_number");
    const sEl = document.getElementById("edit_student_name");

    if (idEl) idEl.value = id;
    if (gEl) gEl.value = guardian;
    if (pEl) pEl.value = phone;
    if (sEl) sEl.value = student;

    const modal = document.getElementById("editContactModal");
    if (modal) modal.style.display = "block";
  }

  // -------------------------
  // Edit form submit
  // -------------------------
  if (editContactForm) {
    editContactForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        const formData = new FormData(editContactForm);
        formData.append("action", "edit");
        const res = await fetch("contacts.php", { method: "POST", body: formData });
        const data = await res.json();
        if (data.status === "success") {
          document.getElementById("editContactModal").style.display = "none";
          fetchGuardians();
        } else {
          alert("Update failed: " + (data.message || "unknown"));
        }
      } catch (err) {
        console.error("edit submit error:", err);
      }
    });
  }

  // -------------------------
  // Add contact form
  // -------------------------
  if (addContactForm) {
    addContactForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const hiddenStudentId = document.getElementById("student_id");
      if (!hiddenStudentId || !hiddenStudentId.value) {
        alert("Please select a student from the suggestions.");
        return;
      }
      try {
        const fd = new FormData(addContactForm);
        fd.append("action", "add");
        const res = await fetch("contacts.php", { method: "POST", body: fd });
        const data = await res.json();
        if (data.status === "success") {
          addContactForm.reset();
          document.getElementById("addContactModal").style.display = "none";
          fetchGuardians();
        } else {
          alert("Add failed: " + (data.message || "unknown"));
        }
      } catch (err) {
        console.error("addContact error:", err);
      }
    });
  }

  // -------------------------
  // Student autocomplete (for Add Contact) - uses FormData POST
  // -------------------------
  if (searchInput && suggestionsBox) {
    let t = null;
    searchInput.addEventListener("input", () => {
      clearTimeout(t);
      const q = searchInput.value.trim();
      if (q.length < 2) {
        suggestionsBox.style.display = "none";
        return;
      }
      t = setTimeout(async () => {
        try {
          const fd = new FormData();
          fd.append("query", q);
          const res = await fetch("search_students.php", { method: "POST", body: fd });
          const data = await res.json();
          if (data.status === "success" && Array.isArray(data.students)) {
            suggestionsBox.innerHTML = "";
            data.students.forEach(stu => {
              const div = document.createElement("div");
              div.textContent = `${stu.first_name} ${stu.last_name} (${stu.grade_level} ${stu.stream})`;
              div.addEventListener("click", () => {
                searchInput.value = `${stu.first_name} ${stu.last_name}`;
                document.getElementById("student_id").value = stu.id;
                suggestionsBox.style.display = "none";
              });
              suggestionsBox.appendChild(div);
            });
            suggestionsBox.style.display = "block";
          } else {
            suggestionsBox.style.display = "none";
          }
        } catch (err) {
          console.error("autocomplete error:", err);
        }
      }, 250);
    });

    document.addEventListener("click", (e) => {
      if (!suggestionsBox.contains(e.target) && e.target !== searchInput) {
        suggestionsBox.style.display = "none";
      }
    });
  }

  // -------------------------
  // Guardian search (staff lookup) - calls search_guardians.php and shows results
  // -------------------------
  if (guardianSearchInput && guardianSearchResults) {
    let t2 = null;
    guardianSearchInput.addEventListener("input", () => {
      clearTimeout(t2);
      const q = guardianSearchInput.value.trim();
      if (q.length < 2) {
        guardianSearchResults.innerHTML = "";
        return;
      }
      t2 = setTimeout(async () => {
        try {
          const res = await fetch("search_guardians.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ query: q })
          });
          const data = await res.json();
          if (data.status === "success" && Array.isArray(data.guardians) && data.guardians.length > 0) {
            let out = `<table><thead><tr><th>Student</th><th>Guardian</th><th>Phone</th></tr></thead><tbody>`;
            data.guardians.forEach(g => {
              out += `<tr>
                <td>${g.first_name} ${g.last_name} (${g.grade_level} ${g.stream})</td>
                <td>${g.guardian_name}</td>
                <td>${g.phone_number}</td>
              </tr>`;
            });
            out += `</tbody></table>`;
            guardianSearchResults.innerHTML = out;
          } else {
            guardianSearchResults.innerHTML = "<p>No results found.</p>";
          }
        } catch (err) {
          console.error("Guardian search error:", err);
          guardianSearchResults.innerHTML = "<p>Error searching (see console).</p>";
        }
      }, 300);
    });
  }

  

  // kick off initial load
  fetchGuardians();
});
