// Arrastar e soltar sessões no calendário da Agenda.
// Chips agendados têm [data-session-chip] com a URL do move;
// células de dia têm [data-calendar-day] com a data (Y-m-d).
document.addEventListener('DOMContentLoaded', () => {
    const chips = document.querySelectorAll('[data-session-chip]');
    const cells = document.querySelectorAll('[data-calendar-day]');

    if (!chips.length || !cells.length) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const highlight = ['ring-2', 'ring-mvteal', 'ring-inset', 'rounded-lg'];

    chips.forEach((chip) => {
        chip.addEventListener('dragstart', (event) => {
            event.dataTransfer.setData('text/plain', chip.dataset.sessionChip);
            event.dataTransfer.effectAllowed = 'move';
            chip.classList.add('opacity-50');
        });

        chip.addEventListener('dragend', () => chip.classList.remove('opacity-50'));
    });

    cells.forEach((cell) => {
        cell.addEventListener('dragover', (event) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            cell.classList.add(...highlight);
        });

        cell.addEventListener('dragleave', () => cell.classList.remove(...highlight));

        cell.addEventListener('drop', async (event) => {
            event.preventDefault();
            cell.classList.remove(...highlight);

            const url = event.dataTransfer.getData('text/plain');

            if (!url) {
                return;
            }

            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ date: cell.dataset.calendarDay }),
            });

            if (response.ok) {
                // Recarrega para re-renderizar o mês com a sessão no novo dia.
                window.location.reload();
            }
        });
    });
});
