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

### Autentykacja JWT i Mechanizm Refresh Token (Silent Refresh)
W celu zagwarantowania bezpieczeństwa i wygody użytkownika (aby sesja treningowa na siłowni nigdy nie została przerwana przez wygaśnięcie tokena), wdrożono dwuskładnikowy system tokenów:
1. **Access Token (Krótkotrwały JWT):**
   - Czas życia: 1 godzina (`ttl: 3600s`).
   - Generowany przez `LexikJWTAuthenticationBundle`.
   - Przekazywany w nagłówku HTTP `Authorization: Bearer <token>`.
2. **Refresh Token (Długotrwały token odświeżający):**
   - Czas życia: 30 dni (`ttl: 2592000s`).
   - Obsługiwany przez `GesdinetJWTRefreshTokenBundle` z dedykowaną encją `App\Entity\RefreshToken` i tabelą `refresh_tokens`.
   - Zwracany w odpowiedzi po udanym logowaniu (`POST /api/login`).
   - **Endpoint Odświeżania (`POST /api/token/refresh`):**
     - Przyjmuje w ciele JSON: `{ "refresh_token": "..." }`.
     - Zwraca nowy, świeży access token `{ "token": "...", "refresh_token": "..." }`.
     - Dostęp publiczny (`PUBLIC_ACCESS`) w `security.yaml` z dedykowanym firewallem `refresh_jwt` i punktem wejścia `entry_point: jwt`.
3. **Architektura Cichego Odświeżania w Aplikacji Mobilnej (Silent Refresh):**
   - **Magazyn Bezpieczny (`expo-secure-store`):** Przechowuje zarówno `jwt_token`, jak i `jwt_refresh_token` w zaszyfrowanym magazynie urządzenia (`SecureStore`).
   - **Axios Response Interceptor (`mobile/src/api/client.ts`):**
     - Przechwytuje błędy `401 Unauthorized` z API (poza `/login` i `/token/refresh`).
     - **Blokada współbieżności i kolejkowanie (`failedQueue`):** Jeśli kilka równoległych zapytań otrzyma 401 jednocześnie, wysyłane jest tylko jedno zapytanie odświeżające, a pozostałe oczekują w kolejce Promise'ów.
     - Po otrzymaniu nowego tokena, interceptor uaktualnia `SecureStore`, podmienia nagłówek `Authorization` i automatycznie ponawia wszystkie oczekujące żądania.
     - Użytkownik nie doświadcza żadnego wylogowania ani błędu w trakcie realizowania treningu.
   - **Bezpieczne Wylogowanie (`AuthContext`):** Funkcja `logout()` atomowo usuwa oba klucze (`jwt_token`, `jwt_refresh_token`) z pamięci urządzenia.

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
  - Posiada opcjonalne powiązanie z planem treningowym (`trainingPlan`), kolejność w cyklu (`dayNumber`) oraz flagę dnia odpoczynku (`isRestDay`).
  - **Treningi poza planem:** Pole `trainingPlan` jest opcjonalne (`nullable: true`) – użytkownik może w każdej chwili stworzyć niezależny trening wolny, który nie należy do żadnego cyklu.
- **WorkoutExercise:** Reprezentuje określone ćwiczenie przypisane do konkretnego treningu (łączy `Workout` z `Exercises`). Przechowuje unikalne notatki (np. wskazówki dotyczące techniki) oraz kolejność wykonywania w planie.
- **WorkoutExerciseSet:** Reprezentuje pojedynczą serię w danym ćwiczeniu. 
  - *Atrybuty:* Numer serii, ilość powtórzeń, założony ciężar oraz tempo (np. "3-1-X-1").
  - *Mechanika Drop Set:* Możliwość określenia serii jako "Drop Set" (`isDropSet`) wraz ze wskazaniem serii nadrzędnej (`parentSet`), co pozwala na kaskadowe przypisywanie redukcji ciężaru.

### Szablony Treningowe (Workout Templates - Wzorzec Blueprint vs Execution Log)
W celu odseparowania schematów wielokrotnego użytku od historii odbytych treningów wprowadzono dedykowany model szablonów:
- **WorkoutTemplate:** Reprezentuje ponadczasowy szablon treningu (np. "Push A - Klatka i Triceps"). Zawiera nazwę, opcjonalny opis, właściciela (`user`), powiązanego trenera (`trainer`), datę utworzenia oraz listę przypisanych ćwiczeń (`exercises`).
- **WorkoutTemplateExercise:** Ćwiczenie przypisane do szablonu z określoną kolejnością (`orderIndex`) oraz wskazówkami technicznymi (`notes`).
- **WorkoutTemplateExerciseSet:** Założenia serii w szablonie (numer serii, sugerowana liczba powtórzeń, sugerowany ciężar, tempo).
- **Zasady Biznesowe Szablonów:**
  - **Bezpieczeństwo historii:** Przekształcenie odbytego treningu w szablon (`POST /api/workout-templates/from-workout/{id}`) kopiuje ćwiczenia i serie do nowego obiektu `WorkoutTemplate`. Oryginalny trening w historii (`Workout`) pozostaje w 100% nienaruszony.
  - **Brak mieszania na listach:** Historia i bieżące treningi (`/api/workouts`) zawierają wyłącznie jednostki `Workout`. Szablony pobierane są osobnym endpointem (`/api/workout-templates`) i nie pojawiają się w historii ani kalendarzu sesji.
  - **Klonowanie do Planu:** Dodanie szablonu jako dnia w planie treningowym (`POST /api/training-plans/{id}/workouts` z `templateId`) powoduje głębokie sklonowanie ćwiczeń i serii do nowo utworzonego obiektu `Workout` przypisanego do cyklu. Dzięki temu modyfikacja ćwiczeń podczas treningu lub w danym cyklu nie wpływa na nadrzędny szablon.
- **API Szablonów Treningowych (`/api/workout-templates`):**
  - `GET /api/workout-templates` – pobranie listy szablonów zalogowanego użytkownika,
  - `GET /api/workout-templates/{id}` – pobranie pełnych szczegółów szablonu wraz z ćwiczeniami i seriami,
  - `POST /api/workout-templates` – utworzenie nowego szablonu od zera,
  - `POST /api/workout-templates/from-workout/{workoutId}` – utworzenie szablonu na podstawie historycznego treningu,
  - `PATCH /api/workout-templates/{id}` – aktualizacja nazwy lub opisu szablonu,
  - `DELETE /api/workout-templates/{id}` – usunięcie szablonu.

### System Cykli i Planów Treningowych (Training Plan & Cycle)
- **TrainingPlan:** Reprezentuje całościowy cykl treningowy (np. "FBW 3 dni", "Push-Pull-Legs 6 dni"). Zawiera:
  - `name`: Nazwa planu/cyklu,
  - `description`: Opis i założenia cyklu,
  - `user`: Użytkownik / podopieczny, dla którego plan jest przeznaczony,
  - `creator`: Twórca planu (sam użytkownik lub trener tworzący plan dla podopiecznego),
  - `isActive`: Flaga określająca, czy cykl jest aktualnie realizowany przez użytkownika (aktywacja jednego planu automatycznie dezaktywuje pozostałe plany tego użytkownika),
  - `cycleDays`: Długość cyklu treningowego w dniach (domyślnie 7, w pełni konfigurowalna przez użytkownika: np. 3, 4, 5, 7, 14 dni).
- **Struktura dni cyklu (z użyciem encji Workout):**
  - Każda jednostka w cyklu reprezentowana jest przez encję `Workout` powiązaną z `trainingPlan` oraz numerem dnia w cyklu `dayNumber` (1, 2, ..., N).
  - **Wiele aktywności w jednym dniu:** System umożliwia zaplanowanie kilku treningów lub kilku aktywności w ramach tego samego dnia cyklu (`dayNumber`), np. rano trening siłowy, a po południu spacer lub rower.
  - **Obowiązek zdefiniowania wszystkich dni cyklu:** Użytkownik ustalając cykl o długości $N$ dni musi określić każdy dzień od 1 do $N$. Dzień może zawierać trening siłowy, lekką aktywność regeneracyjną lub zostać oznaczony jako pełny dzień odpoczynku (Full Rest Day).
  - **Typy jednostek w cyklu:**
    - **Trening siłowy (`activityType = 'WORKOUT'`, `isRestDay = false`):** Zawiera ćwiczenia (`WorkoutExercise`), serie (`WorkoutExerciseSet`), założenia obciążeniowe, powiązanie z szablonem (`WorkoutTemplate`) i może być uruchomiony w wizardzie aktywnego treningu.
    - **Lekka aktywność regeneracyjna (`isRestDay = true`, `activityType` np. `'WALK'`, `'CYCLING'`, `'RUNNING'`, `'SWIMMING'`, `'YOGA'`, `'STRETCHING'`, `'OTHER'`):** Zaplanowana aktywność o niskiej intensywności wspierająca regenerację (z opcjonalnym planowanym czasem trwania `plannedDurationMinutes` oraz dystansem `plannedDistanceKm`).
    - **Pełny dzień odpoczynku (`activityType = 'FULL_REST'`, `isRestDay = true`):** Całkowita regeneracja bez jednostki sportowej.
- **Kalkulacja i Podpowiedzi Przerw Pomiędzy Treningami:**
  - System analizuje rozkład jednostek treningowych w cyklu (uwzględniając powrót cykliczny z dnia $N$ do dnia 1) i dostarcza użytkownikowi podpowiedzi dotyczące zaplanowanych przerw regeneracyjnych. Użytkownik ma pełną swobodę decyzyjną (brak sztywnej blokady), a system edukuje i wskazuje liczbę dni odpoczynku pomiędzy sesjami siłowymi.
- **API Cykli Treningowych (`/api/training-plans`):**
  - `GET /api/training-plans` – pobieranie listy planów (dla trenera możliwość filtrowania po `?traineeId=`),
  - `GET /api/training-plans/active` – pobieranie aktualnie aktywnego cyklu użytkownika z pełną rozpiską dni i ćwiczeń,
  - `GET /api/training-plans/active/recommended-workout` – inteligentna rekomendacja jednostki treningowej z aktywnego planu (z priorytetem zaległego treningu, dzisiejszego lub kolejnego w cyklu),
  - `GET /api/training-plans/{id}` – pobranie szczegółów wybranego cyklu,
  - `GET /api/training-plans/{id}/cycle-analysis` – analiza rozkładu cyklu, kalkulacja przerw pomiędzy treningami oraz podpowiedzi regeneracyjne,
  - `POST /api/training-plans` – tworzenie nowego cyklu (z obsługą `cycleDays` oraz walidacją pokrycia wszystkich dni cyklu),
  - `PATCH /api/training-plans/{id}` – edycja nazwy, opisu, długości cyklu (`cycleDays`) i statusu,
  - `POST /api/training-plans/{id}/activate` – aktywacja cyklu,
  - `POST /api/training-plans/{id}/workouts` – dodanie pozycji (treningu, lekkiej aktywności lub rest day) do cyklu,
  - `POST /api/training-plans/workouts/{workoutId}/start` – atomowe uruchomienie treningu z planu (tworzy nową sesję `Workout` ze statusem `IN_PROGRESS`, klonując ćwiczenia i serie bez niszczenia blueprintu planu),
  - `PATCH /api/training-plans/workouts/{workoutId}` – edycja pozycji w cyklu (w tym `activityType`, `plannedDurationMinutes`, `plannedDistanceKm`),
  - `DELETE /api/training-plans/workouts/{workoutId}` – usunięcie pozycji z cyklu,
  - `DELETE /api/training-plans/{id}` – usunięcie planu (powiązane treningi zachowują historię, a ich powiązanie z planem jest zerowane).
- **Rekomendacja i Rozpoczynanie Treningu z Planu:**
  - Przy rozpoczęciu nowego treningu (ekran `/workouts/new.tsx`) system prezentuje na samej górze dedykowaną kartę z rekomendowaną jednostką z aktywnego planu:
    1. **Zaległy trening (1. priorytet):** Jeśli użytkownik pominął zaplanowany trening w bieżącym tygodniu/okresie, system jako pierwszą opcję proponuje opuszczoną jednostkę z oznaczeniem `⚠️ Zaległy trening (Dzień X)`.
    2. **Dzisiejszy trening (2. priorytet):** Jeśli brak zaległości i na dziś przypada dzień treningowy, sugerowana jest dzisiejsza sesja `⚡ Dzisiejszy trening (Dzień X)`.
    3. **Kolejny w cyklu (3. priorytet):** Jeśli dzisiaj wypada dzień regeneracji (Rest Day) lub brak treningu na dziś, system sprawdza historię i proponuje kolejną sesję w kolejności `📅 Kolejny trening w Twoim cyklu (Dzień X)`.
  - **Bezpieczeństwo Blueprintu Planu:** Kliknięcie *„Rozpocznij ten trening”* wywołuje `POST /api/training-plans/workouts/{id}/start`, tworząc niezależną jednostkę sesji `IN_PROGRESS`, dzięki czemu definicja w planie pozostaje nietknięta i może być realizowana w nieskończoność w kolejnych cyklach.
  - **Elastyczność:** Użytkownik ma do dyspozycji modal *„Inny dzień z planu”* pozwalający uruchomić dowolny dzień cyklu, opcję *„Wczytaj do edycji”* ładującą ćwiczenia do draftu, jak i możliwość stworzenia klasycznego treningu spontanicznego poniżej.
- **Architektura Modularna Serwisów Planu (`src/Service/TrainingPlan/`):**
  - Wyeliminowano antywzorzec „God Object” z `TrainingPlanService`, dzieląc odpowiedzialności zgodnie z zasadą Single Responsibility Principle (SRP):
    1. **`TrainingPlanService` (Fasada i CRUD):** Zarządza cyklem życia planów (tworzenie, edycja, aktywacja, dodawanie i usuwanie pozycji dni). Pozostałe zadania deleguje do wyspecjalizowanych podserwisów z zachowaniem pełnej wstecznej kompatybilności API.
    2. **`TrainingPlanCycleAnalyzer` (`src/Service/TrainingPlan/`):** Czysty serwis analityczno-kalkulacyjny badający rozkład jednostek treningowych i przerw regeneracyjnych w cyklu (uwzględniając powrót cykliczny $N \rightarrow 1$).
    3. **`TrainingPlanRecommendationService` (`src/Service/TrainingPlan/`):** Dedykowany silnik wyznaczania optymalnego treningu z aktywnego planu w oparciu o priorytety: zaległy trening (`OVERDUE`), dzisiejszy trening (`TODAY`), kolejny w cyklu (`NEXT_IN_CYCLE`).
    4. **`TrainingPlanSessionLauncher` (`src/Service/TrainingPlan/`):** Realizuje wzorzec *Blueprint vs Execution* – odpowiada za atomowy start sesji `Workout` ze statusem `IN_PROGRESS`, głębokie klonowanie ćwiczeń i serii oraz ich inicjalizację (`isCompleted = false`).
  - **Dedykowane testy jednostkowe:** Każdy z wyspecjalizowanych serwisów posiada niezależny zestaw testów jednostkowych (`TrainingPlanCycleTest`, `TrainingPlanRecommendationTest`, `TrainingPlanSessionLauncherTest`, `TrainingPlanServiceTest`), minimalizując złożoność mockowania i gwarantując stabilność logiki biznesowej.

### Interaktywny Dashboard Główny (`mobile/src/app/(app)/index.tsx`)
Ekran startowy aplikacji mobilnej agreguje najważniejsze aspekty bieżącego dnia użytkownika w lekkim, modułowym układzie:
1. **Aktywności z Planu na Dziś (`TodayPlanCard`):**
   - Prezentuje zaplanowaną jednostkę treningową (trening siłowy, spacer, rower, czy pełny Rest Day).
   - Wyróżnia zaległości (`OVERDUE`) z najwyższym priorytetem.
   - Umożliwia natychmiastowe uruchomienie sesji (`Rozpocznij trening`) z bezpośrednim klonowaniem do aktywnego treningu.
2. **Co Zrobiono Dzisiaj (`TodayCompletedCard`):**
   - Podsumowuje dzisiejsze ukończone jednostki (`status = 'COMPLETED'`): łączny czas trwania sesji (`min`), sumaryczny podniesiony tonaż (`kg`), liczbę jednostek oraz listę wykonanych treningów ze skrótem do ich szczegółów.
   - W przypadku braku aktywności wyświetla motywujący stan z szybką akcją przejścia do treningu spontanicznego.
3. **Automatyczny Licznik Kroków z Telefonu (`StepsTrackerCard` & `PhoneStepsModal`):**
   - Integracja ze sprzętowym sensorem telefonu (`expo-sensors` Pedometer): zlicza kroki od północy (`00:00:00`) automatycznie w tle bez drenowania baterii oraz aktualizuje licznik na żywo.
   - Wylicza procent dziennego celu (np. 10 000 kroków), szacowane spalone kalorie (`kcal`) oraz przebyty dystans (`km`).
   - Modal instruktażowy (`PhoneStepsModal`) wyjaśnia działanie czujnika sprzętowego oraz integrację z ekosystemami Google Health Connect i Apple HealthKit.
4. **Waga i Parametry Ciała z Wykresem Trendu (`WeightTrackerCard`, `WeightChart`, `AddWeightModal`):**
   - Prezentacja aktualnej wagi (`kg`), wzrostu (`cm`) oraz wskaźnika BMI z plakietką kategorii (Waga prawidłowa, Nadwaga, itp.).
   - Dedykowana encja `WeightLog` w bazie danych rejestrująca historię pomiarów z datami i notatkami.
   - Nowoczesny wykres słupkowo-liniowy zmian wagi w czasie z kalkulacją trendu (delta wagi).
   - Szybki przycisk „+ Dodaj pomiar” z modalem do natychmiastowego zapisu nowej wagi (aktualizuje bazę `weight_log`, profil usera i wykres).
5. **Endpointy Backendowe Dashboardu i Wagi (`DashboardApiController`):**
   - `GET /api/dashboard/summary` – agreguje plan na dziś, ukończone sesje, kroki i metryki wagi w jednym zoptymalizowanym zapytaniu.
   - `GET /api/weight-logs` – pobiera pełną historię wpisów wagi użytkownika.
   - `POST /api/weight-logs` – dodaje pomiar wagi, aktualizuje profil `User` i zwraca nowy wpis.

### Przebieg Treningu (Interaktywny interfejs)
Sekcja `/training-plan` umożliwia przegląd i realizację treningów.
- **Lista Treningów (`index`):** Wyświetla kafelki ze wszystkimi treningami użytkownika (`DRAFT`, `PLANNED`, `COMPLETED`).
- **Przegląd i Aktualizacja Na Bieżąco (`show`):** Po wejściu w trening otwiera się widok jego realizacji. Użytkownik widzi kolejne ćwiczenia i serie.
  - Wykorzystanie **Stimulus.js** (`workout_controller.js`) umożliwia modyfikowanie powtórzeń, ciężaru i statusu odhaczenia (`isCompleted`) "w locie" (Ajax/Fetch API). Zmiany zapisywane są bezpośrednio w bazie bez konieczności przeładowywania strony. Wprowadzono wskaźniki stanu i potwierdzenia zapisu (zielony ptaszek).

- **Tworzenie treningu (Szybkie dodawanie i autouzupełnianie):** Ekran `/workouts/new.tsx` pozwala na natychmiastowe tworzenie treningu spontanicznego z poziomu jednego formularza.
  - **Wyszukiwarka z Autocompletem (`ExerciseAutocomplete`):** Wyszukiwanie ćwiczeń w czasie rzeczywistym bezpośrednio pod polami nazwy i notatek, bez konieczności przechodzenia do osobnego ekranu.
  - **Automatyczne pobieranie parametrów z historii (`GET /api/exercises/{id}/last-history`):** Po kliknięciu ćwiczenia w liście podpowiedzi, system sprawdza ostatni ukończony trening (`COMPLETED`) użytkownika z tym ćwiczeniem i natychmiastowo przypisuje tę samą liczbę serii, powtórzeń oraz ciężarów.
  - **Domyślne parametry dla pierwszego razu:** Jeśli użytkownik nigdy wcześniej nie robił danego ćwiczenia, system przydziela 3 serie po 8 powtórzeń z pustym polem ciężaru (z placeholderem `"0"` dla ćwiczeń bez obciążenia).
  - **Atomowy Zapis i Natychmiastowy Start (`/workouts/active`):** Kliknięcie „Rozpocznij Trening” atomowo tworzy trening wraz z ćwiczeniami w API (`POST /api/workouts`) i natychmiastowo uruchamia tryb aktywnego śledzenia sesji (`/workouts/active`), całkowicie omijając pośredni ekran podglądu.
  - **Zapisz jako szablon (`POST /api/workout-templates`):** Dodano możliwość natychmiastowego zapisania skonfigurowanego zestawu ćwiczeń jako wielokrotny szablon bezpośrednio z poziomu formularza tworzenia treningu (przycisk na dole oraz ikona zakładki w nagłówku).
  - **Import ćwiczeń z szablonu (`WorkoutTemplatePickerModal`):** Na ekranie tworzenia treningu dodano dedykowaną akcję „Importuj z szablonu” (dostępną w nagłówku sekcji ćwiczeń oraz w górnym pasku nawigacji). Otwiera ona modal prezentujący wszystkie zapisane szablony użytkownika z wyszukiwarką i podglądem liczby ćwiczeń. Po wybraniu szablonu system automatycznie ładuje pełny zestaw ćwiczeń wraz z seriami do draftu (z inteligentnym zapytaniem o zastąpienie lub dołączenie do obecnych ćwiczeń) oraz ustawia domyślnie nazwę i opis treningu.
- **Historia Treningów (`/workouts/index.tsx`):**
  - Zakładka prezentuje **wyłącznie historyczne, ukończone sesje treningowe** (`status === 'COMPLETED'`), eliminując z widoku niedokończone szkice (`DRAFT`) i przyszłe dni z planu (`PLANNED`).
  - **Pasek statystyk sumarycznych:** Podsumowanie łącznej liczby ukończonych treningów, łącznego czasu spędzonego na sesjach (`h`) oraz sumarycznego podniesionego tonażu (`kg`).
  - **Wyszukiwanie i Filtrowanie:** Wbudowana wyszukiwarka (po nazwie treningu, planu lub ćwiczeniu) oraz szybkie filtry chipów (*Wszystkie*, *Z planu*, *Spontaniczne*).
  - **Karta Historycznego Treningu (`HistoryWorkoutCard`):** Pełny, bogaty podgląd zrealizowanej jednostki:
    - Data i dokładna godzina zakończenia sesji.
    - Oznaczenie źródła: etykieta planu (np. `Plan: FBW • Dzień 2`) lub `Trening spontaniczny`.
    - Kluczowe metryki: czas trwania (`min`), łączna objętość (`kg`), liczba ćwiczeń i wykonanych serii.
    - Pełna lista ćwiczeń i serii: rozbicie na serie z ciężarem i powtórzeniami (`S1: 10 × 60 kg`), oznaczenia `Drop Set`, maksymalny podniesiony ciężar w ćwiczeniu (`Max: X kg`) oraz notatki do ćwiczeń.
    - Rozwijanie i zwijanie długich list ćwiczeń.
    - Szybka akcja *„Zapisz jako szablon”* oraz przejście do pełnego podglądu.
- **Live Workout Tracking (Śledzenie Na Żywo):** Przycisk **"Rozpocznij trening"** (lub **"Wróć do Treningu"** dla treningów w toku) na ekranie szczegółów `/workouts/[id].tsx` uruchamia bezpośrednio tryb Wizard (prowadzenie "za rączkę") bez konieczności przechodzenia przez etap planowania. Ekran `/workouts/active.tsx` wyświetla globalny stoper trwania treningu oraz aktualnie wykonywane ćwiczenie, włączając opis oraz instrukcję GIF. Serie są odhaczane (isCompleted), a po odhaczeniu pojawia się "Rest Timer". Po wejściu w tryb aktywny status treningu automatycznie zmienia się na `IN_PROGRESS`.
- **Opcje Zakończenia Treningu i Tryb Tylko do Podglądu:**
  - W trybie Wizard (`/workouts/active.tsx`): Przycisk *"Zakończ"* (w nagłówku lub po ostatnim ćwiczeniu) wywołuje okno dialogowe z potwierdzeniem, automatycznie oblicza sumaryczną objętość (ciężar × powtórzenia ukończonych serii) oraz czas trwania w minutach, wysyła status `COMPLETED` do API i przekierowuje użytkownika do listy treningów.
  - W widoku szczegółów (`/workouts/[id].tsx`): Dla treningu w toku (`IN_PROGRESS`) dostępny jest przycisk *"Zakończ trening"*. Po zakończeniu widok prezentuje estetyczny baner sukcesu *"Trening Zakończony (Tylko do podglądu)"* z podsumowaniem czasu i objętości.
  - **Tryb Tylko do Podglądu (Read-only dla `COMPLETED`):**
    Gdy trening ma status `COMPLETED`, ekran szczegółów przechodzi w bezwzględny tryb podglądu:
    - Ukryte są wszelkie akcje modyfikujące: przycisk "Dodaj serię", "Edytuj ćwiczenia", "Wznów trening", "Rozpocznij trening" oraz "Zakończ trening".
    - Wiersze serii (`SetRow`) nie renderują edytowalnych pól `TextInput` (brak wywoływania zapytań `PATCH`), lecz statyczne, czytelne pigułki z ciężarem, liczbą powtórzeń oraz statusem ukończenia serii.
    - Bezpośrednie wejście pod adres `/workouts/active?id=...` z zakończonym treningiem zostaje zablokowane komunikatem ostrzegawczym i przekierowaniem do podglądu treningu.
- **Zarządzanie Szablonami i Tworzenie Planów (Mobile):**
  - **Zapis z historii (`/workouts/[id]`):** Na ekranie szczegółów treningu dostępny jest przycisk *"Zapisz jako szablon"*, który jednym kliknięciem tworzy z odbytej sesji wielokrotny szablon.
  - **Dedykowany Kreator Szablonu (`/workouts/templates/new`):**
    - Pełny konfigurator ćwiczeń i serii: możliwość dodawania ćwiczeń z bazy (`ExercisePickerModal`), określania liczby serii, założeń ciężaru, powtórzeń oraz notatek technicznych (`TemplateExerciseItem`).
    - Opcja automatycznego wypełnienia ćwiczeniami i seriami z wybranego historycznego treningu (`WorkoutHistoryPickerModal`).
  - **Zarządzanie i Edycja Szablonów (`/workouts/templates/[id]`):**
    - Pełny ekran edycji istniejącego szablonu: pobiera aktualne ćwiczenia i serie, pozwala na dodawanie nowych ćwiczeń (`ExercisePickerModal`), modyfikację ciężarów, liczby powtórzeń, tempa oraz dodawanie/usuwanie serii (`TemplateExerciseItem`).
    - Zmiany zapisywane są przez `PATCH /api/workout-templates/{id}` (obsługa aktualizacji ćwiczeń i serii w `WorkoutTemplateService`).
    - W przypadku edycji szablonu wywołanej z poziomu dnia w planie (`workoutId`, `planId`), dzień w planie automatycznie synchronizuje swój zestaw ćwiczeń z nową wersją szablonu, a zatwierdzenie zmian przyciskiem *„Zakończ zmiany i wróć do planu”* natychmiast przenosi użytkownika z powrotem do podglądu planu bez zbędnych okien dialogowych.
  - **Tworzenie i Edycja Dni Planu (`AddDayModal`, `EditDayModal`):**
    - W widokach planów kafelki dni (`PlanDayCard`) prezentują przycisk **„Edytuj dzień”** (zamiast „Otwórz trening”), dostępny zarówno dla dni treningowych, jak i dni regeneracji (Rest Day).
    - Lista podglądu ćwiczeń w `PlanDayCard` domyślnie pokazuje do 3 pozycji, a kliknięcie w `+ X więcej ćwiczeń...` płynnie rozwija pełną listę wszystkich ćwiczeń z możliwością ponownego zwinięcia (`Zwiń ćwiczenia`).
    - Komponent `EditDayModal` jest w pełni zorientowany na zarządzanie **szablonem** dnia cyklu:
      - Prezentuje przypisany szablon oraz przycisk **„Edytuj ten szablon (ćwiczenia i serie)”**, który przenosi bezpośrednio do konfiguratora szablonu `/workouts/templates/[id]`.
      - Umożliwia zmianę szablonu na inny z bazy użytkownika (lub utworzenie nowego szablonu na bazie bieżących ćwiczeń).
      - Pozwala na zmianę nazwy i opisu dnia oraz usunięcie dnia z cyklu.
    - Zmiany w dniu planu obsługiwane są przez dedykowany endpoint `PATCH /api/training-plans/workouts/{workoutId}` w `TrainingPlanApiController`.
- **Wspólny Design System UI (`mobile/src/components/ui/`):**
  - **`CustomButton` (`components/ui/CustomButton.tsx`):** Główny, elastyczny komponent przycisku w aplikacji:
    - Rozmiary (`size`): `'sm'` (kompaktowy do kart, akcji i wierszy), `'md'` (standardowy), `'lg'` (duży blokowy formularzy).
    - Warianty (`variant`): `'primary'` (akcent główny brandu), `'secondary'` (ciemny neutralny), `'outline'` (ramka brandowa), `'danger'` (akcje destrukcyjne/usuwanie), `'ghost'` (tekst z ikoną bez tła), `'glass'` (półprzezroczyste tło do kafelków).
    - Wsparcie dla `isLoading`, `disabled`, `icon`, `iconPosition` oraz automatycznego lub wymuszonego `fullWidth`.
  - **`Badge` (`components/ui/Badge.tsx`):** Uniwersalny komponent odznaki / etykiety statusowej:
    - Warianty (`variant`): `'success'` (zielony: ukończony, aktywny, rest day), `'info'` (niebieski: w toku, szablon, liczba ćwiczeń), `'warning'` (bursztynowy: rekord max kg), `'danger'` (czerwony: drop-set, ostrzeżenie), `'purple'` (fioletowy: trening spontaniczny), `'neutral'` (szary: nowy, domyślny).
    - Rozmiary (`size`): `'sm'`, `'md'` z opcjonalną ikoną.
  - Wyeliminowano powielane, pisane ad-hoc inline style przycisków i plakietek w `HistoryWorkoutCard`, `PlanDayCard`, `WorkoutListCard` oraz `EditDayModal`.

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
- **Blokada Modyfikacji Zakończonych Treningów:** Trening oznaczony statusem `COMPLETED` staje się całkowicie niemodyfikowalny (tylko do podglądu). Wszelkie operacje modyfikacji:
  - Aktualizacja danych treningu (`PATCH /api/workouts/{id}`),
  - Dodanie ćwiczenia (`POST /api/workouts/{id}/exercises`),
  - Dodanie serii (`POST /api/workouts/exercises/{id}/sets`),
  - Edycja lub usunięcie serii (`PATCH /api/workouts/sets/{id}`),
  kończą się wyjątkiem `\InvalidArgumentException('Nie można modyfikować zakończonego treningu.')` i są zwracane z kodem `400 Bad Request`. Zakończony trening nie podlega również procedurze usuwania pustych serii (`cleanupEmptySets`).
- **Pomiary:** Użytkownik nie ma możliwości edytowania wprowadzonych pomiarów starszych niż 7 dni. Zmiana takiego pomiaru kończy się wyjątkiem, który obsługiwany jest przez globalny Subscriber (`400 Bad Request`).
- **Baza Ćwiczeń i Wyszukiwanie Globalne (`GET /api/exercises`):**
  - Obsługuje parametry `search` (lub `q`), `page` (domyślnie 1) oraz `limit` (domyślnie 20).
  - Wyszukuje ćwiczenia w całej bazie danych po fragmencie nazwy.
  - **Sortowanie:** w pierwszej kolejności malejąco według **popularności w planach treningowych użytkowników** (liczba wystąpień w `WorkoutExercise`), a w drugiej kolejności rosnąco **alfabetycznie** po nazwie ćwiczenia.
  - **Prezentacja Multimediów (GIFy ćwiczeń):** Obiekt ćwiczenia zwraca pole `gifUrl` (relatywna ścieżka do `/uploads/exercise-gifs/...` lub zewnętrzny URL). Aplikacja mobilna (poprzez helper `getMediaUrl`) oraz interfejs webowy renderują miniatury i animacje GIF przy każdym ćwiczeniu (w liście bazy ćwiczeń, widoku wyboru ćwiczeń do treningu oraz podczas aktywnego treningu), a w przypadku braku GIFa wyświetlają estetyczny placeholder.
  - **Szczegóły Ćwiczenia (`GET /api/exercises/{id}`):**
    - Zwraca pełny zestaw danych pojedynczego ćwiczenia: podstawowe informacje (`id`, `name`, `difficulty`, `type`, `gifUrl`, `description`), listę zaangażowanych partii mięśniowych (`muscles`: `id`, `name`, `bodyPart`, `activationLevel`, `activationLabel`) oraz wspierane cele treningowe (`supportedGoals`: `id`, `name`, `label`).
    - W przypadku nieznalezienia ćwiczenia zwraca status `404 Not Found`.
  - **Historia Wykonania Ćwiczenia (`GET /api/exercises/{id}/last-history`):**
    - Zwraca dane z ostatniego ukończonego treningu (`COMPLETED`) zalogowanego użytkownika, w którym wykonywał to ćwiczenie (`hasHistory: true`, `workoutDate`, oraz tablica `sets` z wartościami `setNumber`, `reps`, `weight`, `tempo`, `isDropSet`).
    - Jeżeli użytkownik jeszcze nigdy nie ukończył tego ćwiczenia w treningu, endpoint zwraca `hasHistory: false` oraz domyślne parametry: 3 serie po 8 powtórzeń z wartością `weight: null` (dla której aplikacja mobilna prezentuje placeholder `"0"`).
  - **Tworzenie Treningu ze Wstępnymi Ćwiczeniami (`POST /api/workouts`):**
    - Opcjonalne pole `exercises` w ciele żądania JSON pozwala na zdefiniowanie ćwiczeń i ich serii od razu podczas tworzenia nowego treningu (np. przy tworzeniu treningu spontanicznego).
    - `WorkoutService` tworzy i wiąże encje `WorkoutExercise` oraz `WorkoutExerciseSet` w jednej atomowej transakcji, zwracając obiekt treningu w grupach `['workout:read', 'workout:read:full']`.
  - **Aplikacja Mobilna (Karta Pojedynczego Ćwiczenia):** Dedykowany ekran `/workouts/exercise/[id]` otwierany po kliknięciu w dowolną pozycję na liście ćwiczeń. Wyświetla duży, czytelny podgląd animacji GIF, szczegółowy opis techniki wykonania, kafelki partii mięśniowych z kolorystycznym oznaczeniem stopnia aktywacji (Wysoki/Średni/Niski) oraz pigułki wspieranych celów treningowych.
  - **Aplikacja Mobilna (Baza ćwiczeń & Wybór ćwiczeń):** Wykorzystuje mechanizm debouncingu (300ms) w polu wyszukiwania oraz **Lazy Loading (infinite scroll)** poprzez zdarzenie `onEndReached` w `FlatList`, dociągając kolejne porcje po 20 ćwiczeń w trakcie przewijania listy w dół.
  - **Aplikacja Mobilna (Prowadzenie za Rękę podczas Treningu `/workouts/active`):**
    - **Karta Aktywnej Serii (`ActiveSetGuideCard`):** Interfejs wyraźnie instruuje użytkownika o aktualnie wykonywanej serii (np. *"Teraz robimy: Serię 1"*), prezentując duże kafelki z docelową liczbą powtórzeń i ciężarem oraz dedykowany przycisk **"Zakończ serię"**.
    - **Automatyczny Timer Przerwy (`RestTimerOverlay`):** Zakończenie serii automatycznie uruchamia odliczanie przerwy (z opcją -30s / +30s / Pomiń) i wyświetla zapowiedź kolejnej serii do wykonania. Podczas trwania przerwy przycisk zakończenia kolejnej serii jest zablokowany (stan `Trwa przerwa (odpocznij)`), dopóki licznik nie dobiegnie końca lub użytkownik nie kliknie "Pomiń".
    - **Szybka Edycja Wykonania (`EditSetModal`):** Użytkownik może w każdej chwili wyedytować faktycznie zrobione powtórzenia i ciężar za pomocą intuicyjnego modalu ze skrótami (+/-) lub bezpośredniego wpisywania, a także dodać kolejne serie do ćwiczenia.
    - **Pasek Postępu Serii (`SetsList`):** Górne pigułki statusu (np. `S1 ✔`, `S2 ⚡`, `S3`) oraz lista wszystkich serii pozwalają łatwo podejrzeć i zmienić dowolną serię.
    - **Wymuszenie Kolejności Wykonywania Serii:** Zablokowano możliwość oznaczenia serii jako wykonanej nie po kolei. Kolejna seria (np. Seria 3) jest zablokowana ikoną kłódki i nie może zostać ukończona, dopóki wszystkie poprzedzające ją serie (Seria 1, Seria 2) nie zostaną oznaczone jako wykonane.

### Architektura Mobilnego Design Systemu (Unifikacja UI)
Zgodnie z zasadą DRY i profesjonalnym wzorcem Design Systemu, wyeliminowano powielanie lokalnych styli przycisków, odznak i plakietek w komponentach mobilnych:
- **`CustomButton` (`mobile/src/components/ui/CustomButton.tsx`):**
  - **Rozmiary:** `sm` (kompaktowy do kart/wierszy), `md` (standardowy modalowy/nawigacyjny), `lg` (duży formularzowy, domyślny).
  - **Warianty:** `primary` (brandowy niebieski `#2563EB`), `secondary` (`#334155`), `outline` (ramka), `danger` (czerwony ostrzegawczy), `ghost` (przezroczysty z hover/press), `glass` (półprzezroczysty kafelkowy).
  - **Wsparcie:** `isLoading` z `ActivityIndicator`, `disabled`, `icon`, `iconPosition` ('left' | 'right') oraz opcjonalne `fullWidth`.
- **`Badge` (`mobile/src/components/ui/Badge.tsx`):**
  - **Warianty semantyczne:** `success` (zielony), `info` (niebieski), `warning` (bursztynowy), `danger` (czerwony), `purple` (fioletowy), `neutral` (szary).
  - **Rozmiary:** `sm` (subtelne etykiety), `md` (nagłówki kart).
  - **Wsparcie:** ikony, kropki statusowe, spójne zaokrąglenia i typografia.
- **Wykonana refaktoryzacja i czyszczenie:**
  - `PlanCard`, `PlanDayCard`, `EditDayModal`, `AddDayModal`, `WorkoutListCard`, `HistoryWorkoutCard`, `ConnectionCard`, `GoalCard`, `ExerciseListItem`, `WorkoutExerciseCard`, `TemplateExerciseItem`, `ExercisePickerModal`, `WorkoutHistoryPickerModal` oraz ekrany w `app/(app)/` (`plans/index.tsx`, `plans/[id].tsx`, `workouts/index.tsx`, `workouts/templates/new.tsx`, `workouts/templates/[id].tsx`) zostały w 100% przekształcone do korzystania z `CustomButton` i `Badge`.
  - Usunięto wszystkie martwe style (np. `addDayButton`, `actionPill`, `difficultyBadge`, `levelBadge`, `activeBadge`, `statusBadge`, `planActionRow`, `newButton`) oraz nieużywane importy.

### Historia Treningów (`/workouts/index.tsx`)
- Zakładka treningów prezentuje **wyłącznie ukończone sesje historyczne** (`COMPLETED`).
- Zintegrowano sumaryczny pasek statystyk (łączna liczba zrealizowanych sesji, łączny czas treningów w godzinach, łączny zsumowany tonaż w kg).
- Dodano wyszukiwarkę treningów oraz filtry chipowe: *Wszystkie*, *Z planu*, *Spontaniczne*.
- Karta `HistoryWorkoutCard` prezentuje pełny zagnieżdżony podgląd sesji: nazwę planu (jeśli trening pochodzi z planu), datę, czas trwania, tonaż, listę wykonanych ćwiczeń z dokładną rozpiską serii (ciężar, powtórzenia, status wykonania, drop-sety), maksymalny ciężar (`Max: X kg`), notatki oraz opcję szybkiego zapisu jako stały szablon.

### Komendy CLI (Tłumaczenie i Zarządzanie Danymi)
- **`php bin/console app:translate-exercises`**: Tłumaczy nazwy oraz opisy ćwiczeń na język polski za pomocą DeepL API.
