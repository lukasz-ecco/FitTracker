import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["collectionContainer"]
    static values = {
        index: Number,
        prototype: String,
    }

    connect() {
        // Obliczamy początkowy index na podstawie istniejących elementów
        this.indexValue = this.collectionContainerTarget.children.length;
    }

    addCollectionElement(event) {
        event.preventDefault();

        // Zamieniamy '__name__' na aktualny index, by inputy miały unikalne nazwy, np. exercise[exerciseMuscles][0][Muscle]
        const item = this.prototypeValue.replace(/__name__/g, this.indexValue);

        // Dodajemy wygenerowany HTML do kontenera
        this.collectionContainerTarget.insertAdjacentHTML('beforeend', item);

        // Zwiększamy index dla następnego elementu
        this.indexValue++;
    }

    removeCollectionElement(event) {
        event.preventDefault();

        // Znajdź najbliższy wiersz (wrapper elementu) i go usuń
        const wrapper = event.currentTarget.closest('.collection-item');
        if (wrapper) {
            wrapper.remove();
        }
    }
}
