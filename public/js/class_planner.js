// View set
const view = document.getElementById("view");
const div_event = document.getElementById("div_event");

const form_events = document.getElementById("form_events")
let button_event = document.getElementById("add_event");
let label_button_event = document.getElementById("label_button_event");

const edit = document.getElementById("edit");
const form_cancellation = document.getElementById("form_cancellation");

let currentStudentId = (view.value === "CLASS") ? null : view.value;

view.addEventListener('change', () => {
    const selectedValue = view.value;
    const url = new URL(window.location);

    if (selectedValue == "CLASS"){
        currentStudentId = null;

        url.searchParams.delete("student");
    } else {
        currentStudentId = selectedValue;

        url.searchParams.set("student", selectedValue);
    }
    window.location.href = url.toString();
});

// Toggle visibility
if (currentStudentId){
    edit.style.display = "none";
    form_cancellation.style.display = "none";

    div_event.style.display = "none";
    
    document.querySelectorAll(".add-oral").forEach(btn => {
        btn.style.display = "inline-block";
    });

    document.querySelectorAll(".remove-oral").forEach(btn => {
        btn.style.display = "inline-block";
    });
    
    document.querySelectorAll(".slot-elimination").forEach(btn => {
        btn.style.display = "none";
    });
    
    document.querySelectorAll(".event-elimination").forEach(btn => {
        btn.style.display = "none";
    });
} else {
    edit.style.display = "block";
    form_cancellation.style.display = "block";

    div_event.style.display = "flex";

    document.querySelectorAll(".add-oral").forEach(btn => {
        btn.style.display = "none";
    });

    document.querySelectorAll(".remove-oral").forEach(btn => {
        btn.style.display = "none";
    });
    
    document.querySelectorAll(".slot-elimination").forEach(btn => {
        btn.style.display = "inline-block";
    });
    
    document.querySelectorAll(".event-elimination").forEach(btn => {
        btn.style.display = "inline-block";
    });
}

// Calendar overflow
const calendar = document.getElementById("calendar");
const headerContent = document.getElementById("header-content");

calendar.addEventListener("scroll", () => {
    if (calendar.scrollTop > 0) {
        headerContent.classList.add("scrolled");
    } else {
        headerContent.classList.remove("scrolled");
    }
});

// Oral's management
document.querySelectorAll(".add-oral, .remove-oral").forEach(btn => {
    btn.addEventListener("click", () => {
        const slotId = btn.getAttribute("data-slotid");

        fetch("../php/oral.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `idslot=${slotId}&idstudent=${currentStudentId}`
        })
        .then(res => res.text())
        .then(msg => {
            alert(msg);
            location.reload(); 
        });
    });
});

// Slot elimination
const idClass = document.body.dataset.idclass;

document.querySelectorAll(".slot-elimination").forEach(btn => {
    btn.addEventListener("click", () => {
        const slotId = btn.getAttribute("data-slotid");

        if (!confirm("Sei sicuro di voler eliminare questa interrogazione?")) {
            return;
        }

        fetch("../php/events.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `idslot=${slotId}&type=oral&idclass=${idClass}`
        })
        .then(res => res.text())
        .then(msg => {
            alert(msg);
            location.reload(); 
        });
    });
});

// Event elimination
document.querySelectorAll(".event-elimination").forEach(btn => {
    btn.addEventListener("click", () => {
        const eventId = btn.getAttribute("data-eventid");

        if (!confirm("Sei sicuro di voler eliminare questo evento?")) {
            return;
        }

        fetch("../php/events.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `idevent=${eventId}&type=other&idclass=${idClass}`
        })
        .then(res => res.text())
        .then(msg => {
            alert(msg);
            location.reload(); 
        });
    });
});

// Modal closure
const overlay = document.getElementById("overlay");
let edit_displayed = false; 

function openModal(modal){

    overlay.style.display = "flex";
    modal.style.display = "block";

}


function closeModal(){

    if(edit_displayed){

        if(formHasChanged()){

            const confirmExit = confirm(
                "Hai modifiche non salvate. Vuoi eliminarle?"
            );

            if(!confirmExit){
                return;
            }

            restoreForm();
        }

        edit_setting.style.display = "none";
        edit_displayed = false;

    } else {

        overlay.style.display = "none";
        container_settings.style.display = "none";
        form_events.style.display = "none";

    }
}

document.querySelectorAll(".close-modal").forEach(btn => {

    btn.addEventListener("click", () => {

        closeModal();

    });

});

// Settings toggle
const name_settings = document.getElementById("settings");
const container_settings = document.getElementById("div_settings");

function toggleSettings(){
    if(container_settings.style.display === "block"){
        closeModal();
    } else {
        openModal(container_settings);
    }
}

name_settings.addEventListener('click', () => {
    toggleSettings();
})

// Edit toggle
const edit_setting = document.getElementById("edit-setting");

let initialFormData = null;
let initialFormHTML = null;

edit.addEventListener('click', () => {

    openModal(edit_setting);
    edit_displayed = true;

    initialFormData = new FormData(form_class); // To save content
    initialFormHTML = form_class.innerHTML; // To save structure

});

function restoreForm(){

    form_class.innerHTML = initialFormHTML;

    // To add again functions to new html elements
    setupSubjectButtons();
    setupStudentButtons();

}

function formHasChanged(){

    const currentState = new FormData(form_class);

    // Transforming data into a comparable format
    const initial = {};
    const current = {};

    // Saving initial state
    for (const [key, value] of initialFormData.entries()) {

        if (!initial[key]) {
            initial[key] = [];
        }

        initial[key].push(value);
    }

    // Saving current state
    for (const [key, value] of currentState.entries()) {

        if (!current[key]) {
            current[key] = [];
        }

        current[key].push(value);
    }

    // Tranforming into comparable strings
    return JSON.stringify(initial) !== JSON.stringify(current);
}

// Add/Remove Subject
let table;
let subjectNumber = 0; // subject counter 

function setupSubjectButtons(){
    table = document.getElementById('schedule_table');
    subjectNumber = table.getElementsByTagName("tr").length - 1;

    document.getElementById('add_subject').addEventListener('click', () => {
        subjectNumber++;

        // Create the row
        const tr = document.createElement('tr');

        // First td
        const tdInput = document.createElement('td');
        const inputSubject = document.createElement('input');
        inputSubject.type = 'text';
        inputSubject.name = `subjects[subject${subjectNumber}]`
        inputSubject.placeholder = 'Materia';
        inputSubject.required = true;
        tdInput.appendChild(inputSubject);
        tr.appendChild(tdInput);
        // Last 6 td with checkbox's
        const days = [1, 2, 3, 4, 5, 6];
        days.forEach(day => {
            const tdCheckbox = document.createElement('td');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = `schedule[subject${subjectNumber}][${day}]`; // multidimensional array
            tdCheckbox.appendChild(checkbox);
            tr.appendChild(tdCheckbox);
        });

        // Add the tr to the table
        table.appendChild(tr);
    });

    document.getElementById('remove_subject').addEventListener('click', () => {
        if(table.rows.length > 2){ // keep at least the header and the first row
            table.deleteRow(table.rows.length - 1);
            subjectNumber--;  // remove it when you will be able to remove a specific subject, not just the last one
        }
    });
}

// Add/Remove Student
function setupStudentButtons(){
    const containerStudents = document.getElementById('students_container');
    document.getElementById('add_student').addEventListener('click', () => {
        const inputStudent = document.createElement('input');
        inputStudent.type = 'text';
        inputStudent.name = 'students[]';
        inputStudent.placeholder = 'Nome studente';
        inputStudent.required = true;
        containerStudents.appendChild(inputStudent);
    });

    document.getElementById('remove_student').addEventListener('click', () => {
        const inputsStudents = containerStudents.getElementsByTagName('input');
        if (inputsStudents.length > 1) {
            containerStudents.removeChild(inputsStudents[inputsStudents.length - 1]);
        }
    });
}

setupSubjectButtons();
setupStudentButtons();  

// Checkbox check
const form_class = document.getElementById('form_class');

form_class.addEventListener('submit', (e) => {
    const rows = table.querySelectorAll('tr:not(:first-child)');
    let error = false;

    rows.forEach(row => {
        const checkbox = row.querySelectorAll('input[type="checkbox"]');
        const atleastOneSelected = Array.from(checkbox).some(cb => cb.checked);

        if (!atleastOneSelected) {
            error = true;
        }
    });

    if (error) {
        e.preventDefault();
        alert("Ogni materia deve avere almeno un giorno selezionato!");
    }            
});

// Event toggle
function toggleEvents(){
    if(form_events.style.display === "block"){
        closeModal();
    } else {
        openModal(form_events);
    }
}

button_event.addEventListener('click', () => {
    toggleEvents();
})

// Event form
const typeSelect = document.getElementById("type");
const oralDiv = document.querySelector(".oral");
const otherDiv = document.querySelector(".other");
const nameInput = document.getElementById("event_name");

function toggleEventType() {
    if(typeSelect.value === "oral"){
        oralDiv.style.display = "block";  // show oral
        otherDiv.style.display = "none";  // hide other

        nameInput.required = false;
    } else {
        oralDiv.style.display = "none";   // hide oral
        otherDiv.style.display = "block"; // show other

        nameInput.required = true;
    }
}

typeSelect.addEventListener("change", toggleEventType); // If the select changes
toggleEventType(); // Refresh at starting page

// Dates check
form_events.addEventListener("submit", (e) => {
    const start = document.getElementById("start_date").value;
    const end = document.getElementById("end_date").value;

    if (start > end) {
        e.preventDefault();
        alert("La data di inizio non può essere successiva alla data di fine!");
    }
});