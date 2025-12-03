document.addEventListener("DOMContentLoaded", () => {
  let pupilsData = [];     // full dataset from backend
  let filteredData = [];   // data after search & filters
  let currentPage = 1;
  const rowsPerPage = 5;

  const $ = id => document.getElementById(id);

  const searchInput    = $("searchInput")    || $("search-input");
  const filterGrade    = $("gradeFilter")    || $("filterGrade");
  const filterStream   = $("streamFilter")   || $("filterStream");
  const filterBoarding = $("boardingFilter") || $("filterBoarding");

  const pupilsTableBody  = $("pupilsTableBody");
  const pupilsPagination = $("pupilsPagination");

  // --- helper to apply filters and search locally ---
  function filterAndSearch() {
    const search = (searchInput?.value || "").toLowerCase();
    const grade  = filterGrade?.value || "";
    const stream = filterStream?.value || "";
    const boarding = filterBoarding?.value || "";

    return pupilsData.filter(pupil => {
      const admission = (pupil.admission_number ?? pupil.admission_no ?? "").toLowerCase();
      const fullName  = ((pupil.first_name ?? "") + " " + (pupil.last_name ?? "")).toLowerCase();

      const matchesSearch  = !search || admission.includes(search) || fullName.includes(search);
      const matchesGrade   = !grade || (pupil.grade_level ?? pupil.grade) === grade;
      const matchesStream  = !stream || pupil.stream === stream;
      const matchesBoarding = !boarding || (pupil.boarding_status ?? pupil.boarding) === boarding;

      return matchesSearch && matchesGrade && matchesStream && matchesBoarding;
    });
  }

  // --- Fetch all pupils from backend once ---
  async function loadPupils(page = 1) {
    try {
      const res = await fetch("get_pupils.php", { cache: "no-store" });
      const data = await res.json();
      pupilsData = Array.isArray(data) ? data : (data.rows || data.data || []);
      applyFilters(page);
    } catch (err) {
      console.error("Error loading pupils:", err);
      pupilsTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Error loading data.</td></tr>`;
    }
  }

  // --- reapply filters and re-render ---
  function applyFilters(page = 1) {
    filteredData = filterAndSearch();
    renderPupilsTable(page);
  }

  function renderPupilsTable(page = 1) {
    currentPage = page;
    pupilsTableBody.innerHTML = "";

    const start = (page - 1) * rowsPerPage;
    const pageItems = filteredData.slice(start, start + rowsPerPage);

    if (pageItems.length === 0) {
      pupilsTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">No records found.</td></tr>`;
      renderPagination(0);
      return;
    }

    const rowsHtml = pageItems.map((pupil, idx) => {
      const admission = pupil.admission_number ?? pupil.admission_no ?? "";
      const firstName = pupil.first_name ?? "";
      const lastName  = pupil.last_name ?? "";
      const grade     = pupil.grade_level ?? pupil.grade ?? "";
      const stream    = pupil.stream ?? "";
      const boarding  = pupil.boarding_status ?? pupil.boarding ?? "";

      return `
        <tr>
          <td>${start + idx + 1}</td>
          <td>${admission}</td>
          <td>${firstName}</td>
          <td>${lastName}</td>
          <td>${grade}</td>
          <td>${stream}</td>
          <td>${boarding}</td>
          <td>
            <div class="table-actions">
              <button onclick="editPupil(${pupil.id})">Edit</button>
              <button onclick="deletePupil(${pupil.id})">Delete</button>
            </div>
          </td>
        </tr>`;
    }).join("");

    pupilsTableBody.innerHTML = rowsHtml;
    renderPagination(filteredData.length);
  }

  function renderPagination(total) {
    pupilsPagination.innerHTML = "";
    const pageCount = Math.ceil(total / rowsPerPage);
    if (pageCount <= 1) return;

    const firstBtn = document.createElement("button");
    firstBtn.textContent = "<<";
    firstBtn.disabled = currentPage === 1;
    firstBtn.onclick = () => applyFilters(1);
    pupilsPagination.appendChild(firstBtn);

    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(pageCount, currentPage + 2);
    if (endPage - startPage < 4) {
      if (startPage === 1) endPage = Math.min(5, pageCount);
      else if (endPage === pageCount) startPage = Math.max(1, pageCount - 4);
    }

    for (let i = startPage; i <= endPage; i++) {
      const btn = document.createElement("button");
      btn.textContent = i;
      if (i === currentPage) {
        btn.classList.add("active");
        btn.style.backgroundColor = "#007bff";
        btn.style.color = "#fff";
      }
      btn.onclick = () => applyFilters(i);
      pupilsPagination.appendChild(btn);
    }

    const lastBtn = document.createElement("button");
    lastBtn.textContent = ">>";
    lastBtn.disabled = currentPage === pageCount;
    lastBtn.onclick = () => applyFilters(pageCount);
    pupilsPagination.appendChild(lastBtn);
  }

// -----------------------
// EDIT PUPIL MODAL SUPPORT
// -----------------------
window.editPupil = id => {
  const pupil = pupilsData.find(p => p.id == id);
  if (!pupil) return;

  // Fill modal fields
  $("edit_pupil_id").value = pupil.id;
  $("edit_pupil_first").value = pupil.first_name ?? "";
  $("edit_pupil_last").value = pupil.last_name ?? "";
  $("edit_pupil_grade").value = pupil.grade_level ?? pupil.grade ?? "";
  $("edit_pupil_stream").value = pupil.stream ?? "";
  $("edit_pupil_boarding").value = pupil.boarding_status ?? pupil.boarding ?? "";

  $("editPupilModal").style.display = "block";
};

document.getElementById("editPupilForm").addEventListener("submit", async function(e) {
  e.preventDefault();

  const id = $("edit_pupil_id").value;
  const formData = new FormData();
  formData.append("id", id);
  formData.append("first_name", $("edit_pupil_first").value);
  formData.append("last_name", $("edit_pupil_last").value);
  formData.append("grade_level", $("edit_pupil_grade").value);
  formData.append("stream", $("edit_pupil_stream").value);
  formData.append("boarding_status", $("edit_pupil_boarding").value);

  try {
    const res = await fetch("edit_pupil.php", {
      method: "POST",
      body: formData
    });
    const result = await res.json();

    if (result.status === "success") {
      // update local dataset
      const index = pupilsData.findIndex(p => p.id == id);
      if (index > -1) {
        pupilsData[index] = { ...pupilsData[index], ...Object.fromEntries(formData) };
        applyFilters(currentPage);
      }

      alert("Pupil updated successfully");
      $("editPupilModal").style.display = "none";
    } else {
      alert(result.message || "Failed to update pupil");
    }
  } catch (err) {
    console.error("Error updating pupil:", err);
    alert("An error occurred while updating.");
  }
});


// -----------------------
// DELETE PUPIL SUPPORT
// -----------------------
window.deletePupil = async id => {
  if (!confirm("Are you sure you want to delete this pupil?")) return;

  try {
    const formData = new FormData();
    formData.append("id", id);

    const res = await fetch("delete_pupil.php", {
      method: "POST",
      body: formData
    });
    const result = await res.json();

    if (result.status === "success") {
      // Remove from local dataset
      pupilsData = pupilsData.filter(p => p.id != id);
      applyFilters(currentPage);

      alert("Pupil deleted successfully");
    } else {
      alert(result.message || "Failed to delete pupil");
    }
  } catch (err) {
    console.error("Error deleting pupil:", err);
    alert("An error occurred while deleting.");
  }
};


  // --- Hook up filters ---
  if (searchInput)    searchInput.addEventListener("input", () => applyFilters(1));
  if (filterGrade)    filterGrade.addEventListener("change", () => applyFilters(1));
  if (filterStream)   filterStream.addEventListener("change", () => applyFilters(1));
  if (filterBoarding) filterBoarding.addEventListener("change", () => applyFilters(1));

  loadPupils(); // initial
});
