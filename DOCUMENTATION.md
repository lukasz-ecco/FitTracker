# Dokumentacja Projektu FitTracker

FitTracker to aplikacja internetowa do śledzenia treningów, pomiarów ciała oraz generowania spersonalizowanych rekomendacji ćwiczeń na podstawie obranych celów treningowych.

## 1. Stos Technologiczny

### Aplikacja Webowa (Backend & Web Frontend)
- **Backend:** PHP 8.2, Symfony 7.4, API Platform
- **Baza danych:** Relacyjna baza danych obsługiwana przez Doctrine ORM (MySQL / PostgreSQL)
- **Szablony:** Twig
- **Frontend / CSS:** Tailwind CSS (zintegrowany poprzez `symfonycasts/tailwind-bundle`), Alpine.js do interaktywnych komponentów (np. estetyczne rozwijane listy wyboru).
- **Testy:** PHPUnit

> Uwaga: Projekt webowy nie korzysta z tradycyjnego ekosystemu Node.js / NPM w głównym katalogu. Budowanie stylów Tailwind odbywa się bezpośrednio za pomocą dostarczonego bundle'a z poziomu CLI konsoli Symfony.

### Aplikacja Mobilna (Mobile)
- **Framework:** React Native, Expo, Expo Router (nawigacja oparta na plikach - Stack / Tabs).
- **Stylizacja:** Tailwind (StyleSheet API z zachowaniem nazewnictwa w UI Kit).
- **Komunikacja z API:** Axios (z interceptorami JWT).
- **Zarządzanie stanem:** React Context API (`AuthContext` z automatycznym odświeżaniem tokenów i sesji).

## 2. Struktura Bazy Danych i Encje

Aplikacja opiera się na relacyjnym modelu danych. Poniżej znajduje się opis najważniejszych encji w projekcie:

### Autoryzacja i Użytkownicy
- **User:** Reprezentuje użytkownika systemu. Przechowuje standardowe dane autoryzacyjne (email, zahasłowane hasło, role systemu Symfony) oraz posiada przypisane do siebie pomiary i cele treningowe. Przechowuje również dodatkowe dane fizyczne: płeć (`gender`), wiek (`age`), waga (`weight`), wzrost (`height`).
- **Endpoint Profilu:** Ze względu na unikalne reguły (brak ogólnodostępnej ścieżki PATCH/PUT na `User` w API Platform), autoryzacja oraz odczyt/modyfikacja profilu oparta jest na dedykowanym kontrolerze. Dostęp do własnego profilu (odczyt/edycja) odbywa się za pomocą zunifikowanego i w pełni udokumentowanego (OpenAPI) zasobu `GET/PATCH /api/me`.

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

### Relacja Trener - Podopieczny
- **TrainerTraineeConnection:** Encja łącząca konta trenera i podopiecznego (relacja Many-to-Many między użytkownikami z wykorzystaniem tabeli pośredniej).
  - *Atrybuty:* Obejmuje status zaproszenia (`PENDING`, `ACCEPTED`, `REJECTED`), datę utworzenia powiązania oraz znacznik `isMain`, określający, czy dany trener jest trenerem głównym podopiecznego.
  - *Role:* Przypisywanie ról odbywa się podczas rejestracji, gdzie użytkownik wybiera, czy zakłada konto jako Trener (`ROLE_TRAINER`), czy Podopieczny (`ROLE_TRAINEE`).

### System Zapisywania Treningów
- **Workout:** Reprezentuje pojedynczą jednostkę treningową (plan lub odbytą sesję). Zawiera nazwę, status (`DRAFT`, `PLANNED`, `COMPLETED`), właściciela (`user`) oraz opcjonalnie trenera (`trainer`), który ten plan ułożył.
- **WorkoutExercise:** Reprezentuje określone ćwiczenie przypisane do konkretnego treningu (łączy `Workout` z `Exercises`). Przechowuje unikalne notatki (np. wskazówki dotyczące techniki) oraz kolejność wykonywania w planie.
- **WorkoutExerciseSet:** Reprezentuje pojedynczą serię w danym ćwiczeniu. 
  - *Atrybuty:* Numer serii, ilość powtórzeń, założony ciężar oraz tempo (np. "3-1-X-1").
  - *Mechanika Drop Set:* Możliwość określenia serii jako "Drop Set" (`isDropSet`) wraz ze wskazaniem serii nadrzędnej (`parentSet`), co pozwala na kaskadowe przypisywanie redukcji ciężaru.

### Przebieg Treningu (Interaktywny interfejs)
Sekcja `/training-plan` umożliwia przegląd i realizację treningów.
- **Lista Treningów (`index`):** Wyświetla kafelki ze wszystkimi treningami użytkownika (`DRAFT`, `PLANNED`, `COMPLETED`).
- **Przegląd i Aktualizacja Na Bieżąco (`show`):** Po wejściu w trening otwiera się widok jego realizacji. Użytkownik widzi kolejne ćwiczenia i serie.
  - Wykorzystanie **Stimulus.js** (`workout_controller.js`) umożliwia modyfikowanie powtórzeń, ciężaru i statusu odhaczenia (`isCompleted`) "w locie" (Ajax/Fetch API). Zmiany zapisywane są bezpośrednio w bazie bez konieczności przeładowywania strony. Wprowadzono wskaźniki stanu i potwierdzenia zapisu (zielony ptaszek).

#### Zapisywanie treningów (Mobile - React Native)
- **Tworzenie treningu:** Ekran `/workouts/new.tsx` pozwala na nadanie nazwy treningowi (domyślnie generowana z dzisiejszą datą). Po utworzeniu użytkownik jest kierowany do ekranu szczegółów.
- **Dodawanie ćwiczeń:** Zaimplementowano ekran `/workouts/select-exercise.tsx`, który listuje dostępne ćwiczenia. Umożliwia zaznaczenie **wielu ćwiczeń jednocześnie** i masowe wysłanie ich do backendu za pomocą pętli żądań API w locie (`Promise.all`).
- **Realizacja treningu:** Ekran `/workouts/[id].tsx` prezentuje dodane ćwiczenia. Każda nowa "Seria" domyślnie otrzymuje puste parametry.
- **Wpisywanie wyników (w locie):** Wartości (ciężar, ilość powtórzeń, czy seria jest ukończona) edytuje się w małych, wbudowanych inputach (`TextInput`), a stan zostaje zapisany automatycznie po opuszczeniu pola (zdarzenie `onBlur`) poprzez żądanie `PATCH`.

### Profile użytkowników
- Każdy użytkownik ma dedykowany profil, na którym widnieje awatar, typ konta (Trener/Podopieczny), cel treningowy oraz statystyki.ownicy mogą zdefiniować swoje priorytety, przechodząc pod adres (trasę) `/training-goal/set`.
- **Wielokrotność Celów:** Użytkownik nie jest ograniczony do jednego celu. System pozwala posiadać i modyfikować wiele aktywnych celów treningowych jednocześnie (np. jednoczesna budowa masy oraz poprawa siły).
- **Poziom Zaawansowania:** Przy konfiguracji celu, użytkownik dobiera swój realny stopień zaawansowania (1 - Początkujący, 2 - Średniozaawansowany, 3 - Zaawansowany).

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

### Współpraca Trener - Podopieczny
Zaimplementowano moduł łączący trenerów personalnych z podopiecznymi:
- **Dla Trenera (`TrainerController` oraz `TrainerInvitationService`):** Możliwość przeglądania podopiecznych oraz zapraszania nowych poprzez adres e-mail w systemie. Zaproszenia na starcie mają status oczekujący. Logika biznesowa wysyłki i walidacji zaproszeń została odseparowana do dedykowanego serwisu.
- **Dla Podopiecznego (`TraineeController`):** Możliwość przeglądania aktywnych współpracy oraz akceptacji bądź odrzucania otrzymanych zaproszeń e-mailowych. Podopieczny może zdefiniować jednego ze swoich trenerów jako Głównego. System w pełni wspiera wiele połączeń na obu końcach relacji.

### Integracja API i Aplikacji Mobilnej
Aplikacja mobilna dzieli się na dedykowane karty w zależności od typu konta.
Dostępny jest rozbudowany **Profil Użytkownika**, który wykorzystuje zagnieżdżoną nawigację (Stack Navigator). 
- **Edycja Profilu:** Operuje na komponencie `Snackbar` by płynnie i nienachalnie poinformować użytkownika o sukcesie. Zapis uaktualnia globalny stan sesji (`AuthContext -> updateUser`) zwalniając aplikację z wymuszonego przeładowywania widoku. Płeć, imię i nazwisko pozostają zablokowane biznesowo po rejestracji (ich edycja nie jest możliwa). Zaimplementowano także upload zdjęcia profilowego jako obrazu kodowanego w Base64 (przesyłanego w polu `profilePictureBase64`), który backend dekoduje i zapisuje na serwerze. Użyto `KeyboardAvoidingView`, aby zapewnić pełną widoczność pól nad klawiaturą systemową na obu platformach.
- **Zarządzanie Celami (Mobile):** Użytkownicy mogą przeglądać, dodawać i usuwać swoje cele bezpośrednio z poziomu telefonu. Komunikacja opiera się o endpointy `/api/training-goals` oraz `/api/goal_types`. Wprowadzono estetyczne modale do dodawania celów z szybkimi pickerami w formie przycisków ("pills") oraz obsługę notatek.

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

## 6. Architektura API (Wzorzec Service-Repository-Controller)

W celu zapewnienia czytelności i łatwości testowania, API zostało zrefaktoryzowane zgodnie z podziałem odpowiedzialności:

- **Kontrolery (Controllers):** Są maksymalnie "odchudzone" (tzw. "thin controllers"). Odpowiadają wyłącznie za odbieranie żądań HTTP (Request), delegowanie pracy do odpowiednich serwisów oraz zwracanie odpowiedzi HTTP (Response/JsonResponse) z odpowiednimi kodami statusu na podstawie przechwyconych wyjątków.
- **Serwisy (Services):** Przechowują główną logikę biznesową (np. `MeasurementService`, `TrainingGoalService`, `WorkoutService`). Walidują dane wejściowe, zarządzają cyklem życia encji, powiązaniami z innymi obiektami i rzucają wyjątki, gdy coś pójdzie nie tak.
- **Repozytoria (Repositories):** Odpowiadają za pobieranie danych z bazy. Przeniesiono do nich logikę wyszukiwania z uwzględnieniem autoryzacji (np. metody `findUserMeasurement(id, user)`, `findUserGoal(id, user)`), dzięki czemu unika się pobierania encji, do których użytkownik nie ma dostępu.

### Obsługa Błędów i Walidacja (Global Exception Handling)
- Wdrożono własną klasę wyjątków `App\Exception\ValidationException`. Serwisy wykorzystują walidator Symfony i w przypadku naruszenia reguł rzucają właśnie ten wyjątek.
- **Globalne Przechwytywanie (Event Subscriber):** Utworzono klasę `ApiExceptionSubscriber`, która podpięta jest pod `KernelEvents::EXCEPTION`. Mechanizm ten centralnie łapie wszystkie błędy występujące na ścieżkach `/api/*`.
- Dzięki temu kontrolery są w 100% wolne od bloków `try-catch`. Subscriber automatycznie przetwarza zgłaszane przez logikę biznesową wyjątki (`ValidationException`, `InvalidArgumentException`, `AccessDeniedException`, `NotFoundHttpException`, `ConflictHttpException`) i konwertuje je na jednolite, wystandaryzowane odpowiedzi JSON z odpowiednimi kodami HTTP (odpowiednio: `400`, `403`, `404`, `409`).

### Dodatkowe Reguły Biznesowe API
- **Pomiary:** Użytkownik nie ma możliwości edytowania wprowadzonych pomiarów starszych niż 7 dni. Zmiana takiego pomiaru kończy się wyjątkiem, który obsługiwany jest przez globalny Subscriber (`400 Bad Request`).
