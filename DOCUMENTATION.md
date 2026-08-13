# Dokumentacja Projektu FitTracker

FitTracker to aplikacja internetowa do śledzenia treningów, pomiarów ciała oraz generowania spersonalizowanych rekomendacji ćwiczeń na podstawie obranych celów treningowych.

## 1. Stos Technologiczny

- **Backend:** PHP 8.2, Symfony 7.4
- **Baza danych:** Relacyjna baza danych obsługiwana przez Doctrine ORM (MySQL / PostgreSQL)
- **Szablony:** Twig
- **Frontend / CSS:** Tailwind CSS (zintegrowany poprzez `symfonycasts/tailwind-bundle`), Alpine.js do interaktywnych komponentów (np. estetyczne rozwijane listy wyboru).
- **Testy:** PHPUnit

> Uwaga: Projekt nie korzysta z tradycyjnego ekosystemu Node.js / NPM w głównym katalogu. Budowanie stylów Tailwind odbywa się bezpośrednio za pomocą dostarczonego bundle'a z poziomu CLI konsoli Symfony.

## 2. Struktura Bazy Danych i Encje

Aplikacja opiera się na relacyjnym modelu danych. Poniżej znajduje się opis najważniejszych encji w projekcie:

### Autoryzacja i Użytkownicy
- **User:** Reprezentuje użytkownika systemu. Przechowuje standardowe dane autoryzacyjne (email, zahasłowane hasło, role systemu Symfony) oraz posiada przypisane do siebie pomiary i cele treningowe.

### Pomiary Ciała
- **BodyParts:** Słownik zawierający dostępne części ciała (np. klatka piersiowa, biceps, pas), które użytkownik może mierzyć.
- **Meseurments (Pomiary):** Przechowuje pojedyncze wpisy z wynikami pomiarów dla konkretnego użytkownika. 
  - *Relacje:* Połączone z `User` (kto dokonał pomiaru) oraz `BodyParts` (jakiej części ciała dotyczy).
  - *Walidacja:* Posiada wewnętrzną logikę (Assert) uniemożliwiającą wprowadzenie pomiarów z datą w przyszłości lub starszą niż 7 dni.

### Anatomia i Atlas Ćwiczeń
- **Exercises:** Główny słownik ćwiczeń. Zawiera nazwę, poziom trudności w skali od 1 do 9 oraz ogólny typ ćwiczenia.
- **Muscles:** Słownik określający ludzkie mięśnie.
- **ExerciseMuscle:** Encja łącząca określone ćwiczenie z zaangażowanymi w nie mięśniami (relacja Many-to-Many poprzez tabelę pośrednią). Wykorzystuje PHP Enum `MuscleActivationLevel`, określający stopień zaangażowania danego mięśnia podczas konkretnego ruchu.

### Cele Treningowe
- **GoalType:** Słownik typów celów treningowych przechowywany w bazie danych. Zawiera klucz maszynowy (`name`, np. `weight_loss`) oraz polską etykietę wyświetlaną użytkownikowi (`label`, np. `Redukcja wagi`). Pozwala administratorowi zarządzać dostępnymi celami bez modyfikacji kodu.
- **TrainingGoal:** Reprezentuje aktywny cel treningowy wyznaczony przez użytkownika. Powiązany z `GoalType` (typ celu), poziomem zaawansowania (1-3) oraz ewentualnymi notatkami.
- **ExerciseSupportedGoal:** Powiązanie określające, do których `GoalType` dane ćwiczenie pasuje najlepiej.

## 3. Główne Funkcjonalności i Reguły Biznesowe

### Zarządzanie Celami Treningowymi
Użytkownicy mogą zdefiniować swoje priorytety, przechodząc pod adres (trasę) `/training-goal/set`.
- **Wielokrotność Celów:** Użytkownik nie jest ograniczony do jednego celu. System pozwala posiadać i modyfikować wiele aktywnych celów treningowych jednocześnie (np. jednoczesna budowa masy oraz poprawa siły).
- **Poziom Zaawansowania:** Przy konfiguracji celu, użytkownik dobiera swój realny stopień zaawansowania (1 - Początkujący, 2 - Średniozaawansowany, 3 - Zaawansowany).

### System Rekomendacji Ćwiczeń
Spersonalizowana logika dobierająca ćwiczenia jest scentralizowana w serwisie `ExerciseSuggestionService`:
1. W pierwszej kolejności system pobiera z bazy danych **wszystkie aktywne cele** wybranego użytkownika.
2. Następnie, na podstawie każdego z celów wyszukiwane są powiązane ćwiczenia, które aktywnie go wspierają (tabela `ExerciseSupportedGoal`).
3. **Filtrowanie zaawansowania:** Ćwiczenia są rygorystycznie ograniczane maksymalnym poziomem trudności. Trudność jest wyliczana jako mnożnik ustalonego poziomu zaawansowania (Poziom 1 → max trudność 3, Poziom 2 → max 6, Poziom 3 → max 9).
4. System automatycznie agreguje wyniki ze wszystkich list, eliminuje powtarzające się wartości i sortuje wyniki alfabetycznie, aby zaprezentować gotowy zestaw ćwiczeń na stronie `/training-goal/suggestions`.

## 4. Architektura Frontendowa i Interfejs Użytkownika

Projekt celuje w nowoczesne i czyste podejście do tworzenia UI.

- **Tailwind CSS:** Klasy użytkowe (utility-first) są główną osią stylowania.
- **Globalny motyw formularzy (`tailwind_theme.html.twig`):** 
  - Wdrożono spójny, customowy motyw dla silnika formularzy Symfony. Nadpisuje on standardowy (surowy) wygląd przeglądarkowy.
  - System dba o całkowitą jednolitość estetyczną zwykłych inputów (`form_widget_simple`), etykiet tekstowych (`form_label`) oraz wieloliniowych obszarów (`textarea_widget`).
  - Interfejs korzysta z ukrytych mechanizmów **Alpine.js**, by renderować przyjazne użytkownikowi, własne kontrolki "Select" (dropdown), zdejmując z aplikacji konieczność utrzymywania ociężałych wtyczek w rodzaju Select2 czy React.

## 5. Przydatne Komendy Deweloperskie

Poniżej zestawienie powszechnych poleceń przydatnych do pracy nad projektem lokalnym.

> Uwaga: Podczas pracy nad wyglądem strony (edycja klas Tailwind w plikach `.html.twig` lub w konfiguratorach FormType), należy mieć uruchomiony w tle proces nasłuchujący, który natychmiastowo przebuduje wyjściowy plik CSS.

**Przebudowa CSS w czasie rzeczywistym (Watch Mode):**
```bash
php bin/console tailwind:build --watch
```

**Generowanie jednorazowego, zoptymalizowanego buildu CSS (na potrzeby produkcji):**
```bash
php bin/console tailwind:build
```

**Uruchamianie lub aktualizacja bazy (Migracje):**
*(Jeżeli korzystasz z zewnętrznych środowisk dockerowych, uruchom odpowiednio z poziomu docelowego kontenera PHP np. `docker-compose exec web...`)*
```bash
php bin/console doctrine:migrations:migrate
```
