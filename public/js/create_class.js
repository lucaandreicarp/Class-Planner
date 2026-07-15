let subjectNumber = 1; // subject counter 
const table = document.getElementById('schedule_table');

document.getElementById('add_subject').addEventListener('click', () => {
    subjectNumber++;

    // Create the row
    const tr = document.createElement('tr');

    // First td
    const tdInput = document.createElement('td');
    const inputSubject = document.createElement('input');
    inputSubject.type = 'text';
    inputSubject.name = `subjects[subject${subjectNumber}]`
    inputSubject.placeholder = 'Inserisci una materia';
    inputSubject.required = true;
    tdInput.appendChild(inputSubject);
    tr.appendChild(tdInput);

    // Last 7 td with checkbox's
    const days = [1, 2, 3, 4, 5, 6, 7];
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

// Remove Subject
document.getElementById('remove_subject').addEventListener('click', () => {
    if(table.rows.length > 2){ // keep at least the header and the first row
        table.deleteRow(table.rows.length - 1);
        subjectNumber--;  // remove it when you will be able to remove a specific subject, not just the last one
    }
});

// Add Student
const containerStudents = document.getElementById('students_container');
document.getElementById('add_student').addEventListener('click', () => {
    const inputStudent = document.createElement('input');
    inputStudent.type = 'text';
    inputStudent.name = 'students[]';
    inputStudent.placeholder = 'Inserisci uno studente';
    inputStudent.required = true;
    containerStudents.appendChild(inputStudent);
});

// Remove Student
document.getElementById('remove_student').addEventListener('click', () => {
    const inputsStudents = containerStudents.getElementsByTagName('input');
    if (inputsStudents.length > 1) {
        containerStudents.removeChild(inputsStudents[inputsStudents.length - 1]);
    }
});

// Checkbox check
const form = document.getElementById('form_class');

form.addEventListener('submit', (e) => {
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