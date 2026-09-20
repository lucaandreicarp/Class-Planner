let subjectNumber = 1; // subject counter 
const table = document.getElementById('schedule_table');

document.getElementById('add_subject').addEventListener('click', () => {
    subjectNumber++;

    // Create the row
    const tr = document.createElement('tr');

    // First td
    const tdInput = document.createElement('td');

    // Input's div
    const divSubject = document.createElement('div'); 

    // Input text
    const inputSubject = document.createElement('input');
    inputSubject.type = 'text';
    inputSubject.name = `subjects[subject${subjectNumber}]`
    inputSubject.placeholder = 'Materia';
    inputSubject.required = true;

    // Button
    const buttonDelete = document.createElement('button');  
    buttonDelete.type = 'button';
    buttonDelete.className = 'remove';

    buttonDelete.addEventListener('click', function() {
        removeSubject(this);
    });

    // Icon
    const iconDelete = document.createElement('i');     
    iconDelete.setAttribute('data-lucide', 'trash-2');

    buttonDelete.appendChild(iconDelete);

    divSubject.appendChild(inputSubject);
    divSubject.appendChild(buttonDelete);

    tdInput.appendChild(divSubject);

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
    table.tBodies[0].appendChild(tr);

    // Rendering icons
    lucide.createIcons();
});

// Remove Subject
function removeSubject(button) {
    const tr = button.closest('tr');
    const table = tr.closest('table');

    if (table.querySelectorAll('tr').length > 3) {  // Keeps header and first row
        tr.remove();
    } else {
        alert("Devi mantenere almeno una materia!");
    }
}

// Add Student
const labelStudents = document.getElementById('students_label');
const containerStudents = document.getElementById('students_container');
const inputsStudents = containerStudents.getElementsByTagName('input');

document.getElementById('add_student').addEventListener('click', () => {
    
    // Div
    const divStudent = document.createElement('div');   
    
    // Input
    const inputStudent = document.createElement('input');  
    inputStudent.type = 'text';
    inputStudent.name = 'students[]';
    inputStudent.placeholder = 'Nome dello studente';
    inputStudent.required = true;

    // Button
    const buttonDelete = document.createElement('button');  
    buttonDelete.type = 'button';
    buttonDelete.className = 'remove';

    buttonDelete.addEventListener('click', function() {
        removeStudent(this);
    });

    // Icon
    const iconDelete = document.createElement('i');     
    iconDelete.setAttribute('data-lucide', 'trash-2');

    buttonDelete.appendChild(iconDelete);

    divStudent.appendChild(inputStudent);
    divStudent.appendChild(buttonDelete);

    containerStudents.appendChild(divStudent);

    // Update the label with the new count
    updateStudentLabel();

    // Rendering icons
    lucide.createIcons();
});

// Remove Student
function removeStudent(button) {
    const studentRow = button.closest('div');

    if (containerStudents.children.length > 1) {    // Keeps at least 1 student
        studentRow.remove();
        updateStudentLabel(); // Update the label with the new count
    } else {
        alert("Devi mantenere almeno uno studente!");
    }
}

// Set the initial label text with the count of students
function updateStudentLabel() {
    labelStudents.textContent = `Studenti (${inputsStudents.length})`;
}
updateStudentLabel();

// Checkbox check
const form = document.getElementById('form_class');

form.addEventListener('submit', (e) => {
    const rows = table.querySelectorAll('tr:nth-child(n+3)');
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