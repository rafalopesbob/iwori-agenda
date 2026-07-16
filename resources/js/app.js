import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Interações do calendário da Agenda:
// - clicar num dia vazio abre o agendamento naquela data;
// - arrastar uma sessão agendada para outro dia a reagenda.
//
// O arraste usa Pointer Events (não a API nativa de Drag and Drop do HTML5,
// que não funciona em telas de toque) para funcionar igual no mouse e no dedo.
document.addEventListener('DOMContentLoaded', () => {
    const cells = document.querySelectorAll('[data-calendar-day]');

    if (!cells.length) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const DRAG_THRESHOLD = 6;
    const highlight = ['ring-2', 'ring-mvteal', 'ring-inset'];

    // Clicar em qualquer parte vazia do dia (fora de uma sessão) agenda ali.
    // Checa [data-session-item], presente em toda sessão (não só nas arrastáveis),
    // para não disparar a navegação ao clicar nos botões de uma sessão já concluída/cancelada.
    cells.forEach((cell) => {
        cell.addEventListener('click', (event) => {
            if (event.target.closest('[data-session-item]')) {
                return;
            }

            window.location.href = cell.dataset.dayUrl;
        });
    });

    document.querySelectorAll('[data-session-chip]').forEach((chip) => {
        const handle = chip.querySelector('[data-drag-handle]') ?? chip;
        // Impede o navegador de rolar a página ao começar o toque no manuseador.
        handle.style.touchAction = 'none';

        handle.addEventListener('pointerdown', (downEvent) => {
            if (downEvent.button !== undefined && downEvent.button !== 0) {
                return;
            }

            const startX = downEvent.clientX;
            const startY = downEvent.clientY;
            let dragging = false;
            let ghost = null;
            let currentCell = null;

            const clearHighlight = () => currentCell?.classList.remove(...highlight);

            const onMove = (moveEvent) => {
                const dx = moveEvent.clientX - startX;
                const dy = moveEvent.clientY - startY;

                if (!dragging && Math.hypot(dx, dy) > DRAG_THRESHOLD) {
                    dragging = true;
                    chip.classList.add('opacity-40');

                    ghost = chip.cloneNode(true);
                    ghost.style.position = 'fixed';
                    ghost.style.pointerEvents = 'none';
                    ghost.style.zIndex = '9999';
                    ghost.style.width = chip.offsetWidth + 'px';
                    ghost.style.opacity = '0.9';
                    document.body.appendChild(ghost);
                }

                if (!dragging) {
                    return;
                }

                ghost.style.left = moveEvent.clientX + 12 + 'px';
                ghost.style.top = moveEvent.clientY + 12 + 'px';

                // Esconde o ghost momentaneamente para elementFromPoint enxergar a célula por baixo.
                ghost.style.display = 'none';
                const target = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY);
                ghost.style.display = '';

                const cell = target?.closest('[data-calendar-day]') ?? null;

                if (cell !== currentCell) {
                    clearHighlight();
                    currentCell = cell;
                    currentCell?.classList.add(...highlight);
                }
            };

            const onUp = async () => {
                handle.releasePointerCapture(downEvent.pointerId);
                handle.removeEventListener('pointermove', onMove);
                handle.removeEventListener('pointerup', onUp);
                handle.removeEventListener('pointercancel', onUp);

                if (!dragging) {
                    return;
                }

                const destination = currentCell;
                clearHighlight();
                ghost.remove();
                chip.classList.remove('opacity-40');

                if (!destination) {
                    return;
                }

                const response = await fetch(chip.dataset.sessionChip, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ date: destination.dataset.calendarDay }),
                });

                if (response.ok) {
                    // Recarrega para re-renderizar o mês com a sessão no novo dia.
                    window.location.reload();
                }
            };

            handle.setPointerCapture(downEvent.pointerId);
            handle.addEventListener('pointermove', onMove);
            handle.addEventListener('pointerup', onUp);
            handle.addEventListener('pointercancel', onUp);
        });
    });
});
