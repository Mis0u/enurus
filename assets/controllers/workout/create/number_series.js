export function numerate(tbody) {
    tbody.querySelectorAll('tr').forEach((tr, i) => {
        tr.dataset.setIndex = i;
        tr.querySelector('td:first-child').textContent = i + 1;

        tr.querySelectorAll('input').forEach(input => {
            input.name = input.name.replace(
                /\[exerciseSets\]\[\d+\]/,
                `[exerciseSets][${i}]`
            );
            if (input.type === 'hidden') {
                input.value = i;
            }
        });
    });
}
