import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        console.log('✅ Kontroler modala podłączony poprawnie!');
    }
    // Definiujemy elementy, którymi chcemy sterować
    static targets = ['dialog', 'content', 'title'];

    // Metoda wywoływana po kliknięciu "Edytuj"
    async open(event) {
        // Pobieramy URL z atrybutu data-url klikniętego przycisku
        const url = event.currentTarget.dataset.url;
        const title = event.currentTarget.dataset.title;

        // Pokazujemy modal i wstawiamy spinner ładowania
        this.dialogTarget.classList.remove('hidden');
        if (title) {
            this.titleTarget.textContent = title;
        }
        this.contentTarget.innerHTML = `
            <div class="flex justify-center items-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        `;

        // Pobieramy formularz z serwera (AJAX)
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            this.contentTarget.innerHTML = await response.text();
        } catch (error) {
            this.contentTarget.innerHTML = '<p class="text-red-500 font-medium py-4">Wystąpił błąd podczas ładowania formularza.</p>';
        }
    }

    // Metoda do zamykania modala (X)
    close() {
        this.dialogTarget.classList.add('hidden');
        this.contentTarget.innerHTML = ''; // Czyścimy zawartość po zamknięciu
    }

    // Zamykanie po kliknięciu w ciemne tło poza formularzem
    closeOnBackground(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}