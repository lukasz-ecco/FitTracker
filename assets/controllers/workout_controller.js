import { Controller } from '@hotwired/stimulus';

/*
 * This is a stimulus controller for handling live workout updates.
 */
export default class extends Controller {
    static values = {
        updateUrl: String,
        setId: Number,
    };

    static targets = ["row", "reps", "weight", "isCompleted", "statusIcon"];

    connect() {
        this.updateVisualState();
    }

    async updateSet(event) {
        if (event && event.type === 'change' && event.target.type !== 'checkbox' && event.target.tagName !== 'SELECT') {
            // Only update on change if it's a checkbox/select, for text inputs wait for blur (or debounce, but blur is safer here).
            // Actually, Stimulus 'change' on input texts triggers on blur or enter, which is fine!
        }

        const data = {
            isCompleted: this.isCompletedTarget.checked,
            reps: parseInt(this.repsTarget.value, 10) || 0,
            weight: parseFloat(this.weightTarget.value) || 0.0
        };

        try {
            this.statusIconTarget.innerHTML = `<svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;
            
            const response = await fetch(this.updateUrlValue, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const result = await response.json();
            
            if (result.success) {
                this.updateVisualState();
                this.statusIconTarget.innerHTML = `<svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
                
                setTimeout(() => {
                    if (this.statusIconTarget) {
                        this.statusIconTarget.innerHTML = '';
                    }
                }, 2000);
            }
        } catch (error) {
            console.error("Error updating set:", error);
            this.statusIconTarget.innerHTML = `<svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`;
        }
    }

    updateVisualState() {
        if (this.isCompletedTarget.checked) {
            this.rowTarget.classList.add('bg-green-50', 'border-green-200');
            this.rowTarget.classList.remove('bg-white', 'border-gray-200');
        } else {
            this.rowTarget.classList.add('bg-white', 'border-gray-200');
            this.rowTarget.classList.remove('bg-green-50', 'border-green-200');
        }
    }
}
