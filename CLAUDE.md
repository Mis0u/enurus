# FitTracker — CLAUDE.md

Application de suivi de musculation. Développeur solo (Misou). Symfony 7.4 / PHP 8.4.
**Toujours discuter et valider l'architecture avant d'écrire du code** — ne jamais coder
directement sur une demande de feature sans avoir fait valider l'approche.

## Docs par sujet — à lire seulement si le sujet est concerné
- Chantier dashboard progressif (widgets, paliers, streak, Chart.js) → lire `docs/dashboard-architecture.md`

---

## Stack

PHP 8.4 (property hooks), Doctrine ORM, Twig, Tailwind CSS v4.1.11, Stimulus (Hotwired),
Symfony UX (LiveComponents, Twig Components), Symfony Messenger (transport Doctrine),
Symfony Mailer (Mailtrap en dev), SweetAlert2, SortableJS, AssetMapper (pas de Webpack Encore),
Flysystem (local dev → Scaleway S3 prod), PostgreSQL, KnpPaginatorBundle.

Qualité : PHPStan niveau 9 + dead-code-detector, ECS, TwigCS, PHPMND, ESLint, Doctrine Doctor,
Captain Hook, PHPUnit 12.5.4, Castor comme task runner.

---

## Règles non négociables

- `declare(strict_types=1)` sur tous les fichiers PHP.
- **Single Action Controllers** : `final class`, `__invoke()`, `#[IsGranted('ROLE_USER')]` au niveau
  classe, Voter métier au niveau route (`#[IsGranted(XxxVoter::EDIT, subject: 'xxx')]`). Sans le
  premier IsGranted, un anonyme atteint le Voter et `getUser()` retourne `null` → comportement indéfini.
- **Zéro logique métier en Twig.** Le controller orchestre, les services calculent, la vue affiche
  des données déjà prêtes.
- **Property hooks PHP 8.4, jamais de getters/setters.** Getter strictement typé, setter défensif
  nullable quand un champ peut arriver vide d'un formulaire :
  ```php
  set(?float $weight) {
      if (null === $weight || $weight <= 0) return;
      $this->weight = $weight;
  }
  ```
  Le pattern `$this->x = $x ?? $this->x` satisfait PHPStan (`propertySetHook.noAssign`).
- **`LogicException` + `throw`**, jamais `assert()`, pour les gardes de nullité. Dans les tests :
  `assertIsString()`/`assertIsArray()` plutôt que `assert()` brut — sauf si l'extension
  `phpstan-phpunit` est absente du projet, auquel cas type guards explicites + `throw`.
- **`{% include %}` toujours avec `only`.** Chaque nouvelle variable doit remonter dans TOUTE la
  chaîne d'inclusion, pas seulement le dernier maillon — piège récurrent.
- **Pas de `~` en clé de hash Twig.**
- **Domaine de traduction toujours explicite** dans les appels `trans()`. Format **ICU** (`{name}`),
  jamais `%name%` — sauf `validators.xx.yaml` qui garde `{{ limit }}` (format natif Symfony
  `ConstraintViolation`, différent du reste).
- **8 langues supportées** : fr, en, it, es, pt, de, nl, pl. Routes locale-préfixées **sur tous les
  endpoints, y compris les endpoints AJAX jamais vus par l'utilisateur** — la résolution de locale
  dépend du préfixe `_locale` de la route matchée (`RouterListener`), pas d'un mécanisme de session.
  `LoginSuccessListener` ne fixe la locale qu'une fois, au login — jamais `Request::setLocale()` sur
  les requêtes suivantes. `_locale` n'est jamais passé manuellement à `redirectToRoute()`.
- **Fixtures = dev only, destructives.** Données de référence métier (ex. `svgIds` de
  `MuscleGroup`) = migrations Doctrine, jamais fixtures.
- **Responsive et accessibilité pris en compte systématiquement.**

---

## Modèle de données (cœur)

```
User (alias, email, password, gender: GenderEnum, locale, unit_of_measure)
  ├── Workout (owner, performedAt: DateTimeImmutable, duration?, note?, photoPath?, routine?)
  │     └── WorkoutExercise (exercise, position)
  │           └── ExerciseSet (weight: float [TOUJOURS EN KG EN BASE], reps: int, position)
  ├── Exercise (name, description?, isPublic, owner? [null = public])
  │     └── ExerciseMuscle (muscleGroup, type: PRIMARY|SECONDARY)
  │           └── MuscleGroup (name, position, svgIds: array<string>)
  └── Routine (name, description?)
        └── RoutineExercise (exercise, position)
```

Règles :
- Poids **toujours en kg en base**. Conversion lbs↔kg = responsabilité de la couche
  affichage/soumission (`WeightConverterService`), jamais du stockage.
- `cascade: ['persist','remove']` + `orphanRemoval: true` sur toutes les relations parent→enfant.
- `performedAt` est `datetime_immutable`, sans timezone actuellement (TODO #10 — évaluation
  `datetimetz_immutable` non actée, ne pas anticiper : traiter les nouvelles features comme le
  reste de l'app, naive datetime, pour ne pas créer d'incohérence entre pages).
- Exercices publics (`isPublic = true`, `owner = null`) : jamais affectés par la cascade de
  suppression de compte utilisateur.

---

## Sécurité

Un Voter par entité avec attributs `_VIEW`/`_EDIT`/`_DELETE` (ex. `WorkoutVoter`, `RoutineVoter`,
`ExerciseVoter`). Règle unique et centralisée : `$entity->owner === $user`. Jamais de vérification
de propriété dupliquée dans un controller. Pas de voter `_LIST` : une liste n'est pas une ressource
individuelle, `#[IsGranted('ROLE_USER')]` + filtre `owner` en repository suffisent.

Endpoints XHR (delete, etc.) vérifient `$request->isXmlHttpRequest()` — protection faible (header
falsifiable) mais barrière de base contre les appels directs.

**Audit CSRF en attente (non résolu)** : `RoutineDeleteController`, `WorkoutDeleteController`,
`ExerciseDeleteController` ne vérifient que `isXmlHttpRequest()`, sans validation de token CSRF.
Le JS de suppression de séance n'envoie pas de header `X-CSRF-Token`.

---

## Patterns transversaux éprouvés

### Formulaires
- FormType Symfony complet pour toute validation non-triviale ou réutilisée (mot de passe, workout).
- Pas de FormType pour des scalaires uniques auto-save en AJAX (ex. champs du profil réglages) —
  validation manuelle via `ValidatorInterface`, `csrf_token()` généré statiquement en Twig.
- **Toute la config structurelle d'un formulaire** (action, `csrf_token_id`, attributs Stimulus)
  vit dans `configureOptions()`, jamais répétée en Twig.
- `mapped: false` + DataTransformer pour les inputs complexes (JSON caché → Collection d'entités).
  Le hidden input ne reçoit **jamais** de valeur pré-remplie via l'option `'data'` en édition — le
  JS l'initialise seul depuis le DOM existant, pour éviter les doubles appels `transform()`.
- `PRE_SUBMIT` listener sur `CollectionType` pour peupler dynamiquement les entrées ajoutées en JS
  (Symfony ignore ce qu'il ne connaît pas à l'init du form). Vérifier `has()` avant `add()` en édition.
- `render_rest: false` supprime le token CSRF → toujours `{{ form_row(form._token) }}` explicite
  avant `form_end`.
- `csrf_token_id` fixe et identique entre form et JS quand un controller Stimulus (Symfony UX)
  remplace le token de façon asynchrone — sinon vérif serveur KO sur soumission `fetch`.

### Upload de fichiers
- `ImageUploadService` générique (ne connaît ni Workout ni User), paramétré par `$context`
  (sous-dossier) + `$ownerId`. UUID v4 comme nom de fichier stocké (empêche collision et path
  traversal). `writeStream`, jamais `write` (fichiers 5 Mo).
- Service métier dédié par entité (`WorkoutPhotoService`, `UserAvatarService`) qui orchestre :
  remplace, persiste le nouveau chemin, **supprime l'ancien fichier après le flush** (si le flush
  échoue, l'ancien fichier est préservé).
- Validation double couche : front (MIME `file.type` + taille `file.size`, jamais codés en dur —
  dérivés de `data-*` attributes) et back (`#[MapUploadedFile]` + `Assert\File`, MIME réel via
  `finfo`, pas l'extension).
- `ImageConstraints` : constantes partagées back/front (taille max, MIME autorisés), single source
  of truth — jamais de valeur dupliquée en dur côté Twig.
- Chemins stockés en base = **relatifs**, jamais une URL publique.

### Requêtes Doctrine
- **Ne jamais `setMaxResults()` sur une requête avec JOIN sur une collection one-to-many** — le
  LIMIT s'applique aux lignes SQL, pas aux entités → perte silencieuse de données. Utiliser une
  sous-requête DQL corrélée à la place (déjà fait pour le tonnage).
- PR (poids max) calculé sur `weight` seul, jamais `weight × reps` — convention musculation.
- Muscles d'un workout dédupliqués en PHP : `PRIMARY` si primaire dans au moins un exercice.
  Tri primaires→secondaires en repository.
- Toutes les données lourdes précalculées dans le controller, passées à la vue en maps indexées —
  zéro requête N+1, zéro logique de calcul en Twig.

### SVG / silhouette musculaire
- Composant : `{% include 'SVG_BODY/' ~ gender.value ~ '/front.html.twig' %}` (+ `/back.html.twig`).
- Couleurs réelles du projet (celles de la création d'exercice, **source de vérité**) : primaire
  `#f43f5e` (rose), secondaire `#a855f7` (violet, `rgb(168,85,247)`). Posées en CSS sémantique,
  pas inline.
- Coloriage piloté par un controller Stimulus recevant les `svgIds` en `data-*-value` JSON
  (ex. `workout--show--muscles` : `data-...-primary-value` / `-secondary-value`).
- **3 propriétés CSS obligatoires** sur le `g` SVG : `fill`, `stroke`, ET `color` (les SVG utilisent
  `fill: currentColor`, sans `color` explicite l'héritage ne marche pas).
- Fichiers SVG female contiennent des typos connues dans leurs ids (`later-head-triceps`,
  `upper-trapzeius`) — ne pas "corriger" innocemment sans vérifier tous les usages.

### Frontend / Stimulus
- Classe JS pure (non-Stimulus) plutôt qu'un controller surchargé, quand une logique complexe doit
  être extraite (ex. `NoteModalManager` reçoit `application` Stimulus en constructeur).
- Membres privés ES2022 (`#methode`) pour l'encapsulation stricte.
- Bouton hors de la hiérarchie DOM d'un controller Stimulus → `data-action` ne fonctionne pas.
  Attacher un listener par `id` dans `connect()`, ré-attacher via l'event `turbo:load`.
- Sélecteurs DOM sur des conteneurs dont les enfants portent le même `data-*` qu'un sous-élément
  → toujours `:scope > [data-x]`, jamais `querySelectorAll` nu.
- `form.reportValidity()` explicite en tout début de handler si une soumission passe par
  `fetch`/`FormData` manuel — ce chemin contourne toujours la validation HTML5 native.
- Toute mutation d'état partagé (ex. état `disabled` d'un bouton selon plusieurs conditions) doit
  passer par un point central unique — jamais plusieurs endroits qui écrasent l'état indépendamment.
- Messages d'erreur/succès toujours transmis en `data-*-value` traduits depuis Twig, jamais codés
  en dur dans le JS (compatibilité i18n).
- Toast SweetAlert2 (`assets/utils/toast.js`, `showSuccessToast()`) plutôt qu'un feedback visuel
  DOM custom — pattern uniforme sur tout le projet.
- Cache AssetMapper : si un `console.log` fraîchement ajouté n'apparaît jamais malgré un controller
  qui se connecte bien, suspecter le cache avant la logique (`asset-map:compile` + vidage cache navigateur).

### CSS / Tailwind v4
- `!important` = **suffixe** (`hidden!`), pas préfixe (`!hidden` = syntaxe v3 invalide).
- Toute classe arbitraire avec une **virgule** (`grid-cols-[minmax(0,1fr)_380px]`, certains
  `rgba()`) casse le parser de candidats Tailwind v4 → fragile, à vérifier immédiatement (`grep`
  dans `var/tailwind/app.built.css`, pas le fichier source `assets/styles/app.css`).
- Classes Tailwind avec variables CSS custom non résolues en JIT dans ce contexte → classes
  sémantiques dans un `.css` dédié avec valeurs hex directes.
- Fichier `.css` dédié réservé aux pseudo-éléments, keyframes, états dynamiques JS — sinon
  Tailwind-first partout.
- `_base_dashboard.html.twig` pose volontairement `overflow-hidden` deux fois (wrapper + `<main>`)
  — chaque page doit fournir son propre scroll via un wrapper `<div class="flex-1 overflow-y-auto">`
  dans son bloc `state`.

### Tests
- Pattern `FunctionalTestTrait`, un seul `static::createClient()`/`login()` par test, jamais deux.
  `static::getContainer()` **toujours après**, jamais avant.
- Fixtures dédiées par scénario de test, via `getReference()` + `UserFixtures::REFERENCE_PREFIX`.
- `createMock()` uniquement si on vérifie un appel précis (`expects(self::once())`), `createStub()`
  sinon.
- CSRF en test : lire le token depuis le DOM déjà rendu via le Crawler, jamais régénérer via
  `CsrfTokenManagerInterface::getToken()` hors requête (`SessionNotFoundException`).
- Rate limiter en test : `ArrayAdapter` avec déclaration de service manuelle + `autoconfigure:
  false` — sinon Symfony le reset après **chaque requête** en env test via `ResetInterface`.
- `ImageTestHelper` : toujours créer de vrais fichiers image (`imagecreatetruecolor`) dans les
  tests d'upload — Symfony valide le MIME réel via `finfo`.
- **Jamais `findOneBy(..., ['performedAt' => 'DESC'])` pour retrouver "le workout qu'on vient de
  créer".** `performedAt` est une donnée métier saisie par l'utilisateur, pas un horodatage de
  création — un `Workout` fixture peut porter la même date (voire une heure plus tardive) que celui
  tout juste soumis par le test, et passer devant en tri `DESC` (piège déjà documenté côté fixtures :
  `WorkoutFixtures::generateDates()` pioche des jours **aléatoires**, dont potentiellement
  aujourd'hui). Un tri secondaire `'id' => 'DESC'` ne corrige rien tant que `performedAt` reste la
  clé primaire du tri : il ne s'applique qu'en cas d'égalité stricte sur `performedAt`, jamais entre
  `2026-07-14 00:00:00` (soumis par le test) et `2026-07-14 15:30:00` (fixture du jour). La seule
  clé fiable pour "le plus récemment créé" est `'id' => 'DESC'` **seul** : les entités utilisent
  `UuidGenerator` configuré en **UUIDv7** (chronologiquement ordonnable), donc l'`id` reflète l'ordre
  réel d'insertion indépendamment de `performedAt`.
- **Ne jamais mettre `final` sur une classe stubbée/mockée via `createStub()`/`createMock()`** dans
  un test unitaire — PHPUnit génère un double en sous-classant la classe cible, ce qui échoue
  silencieusement (`unresolvableReturnType` côté PHPStan) si elle est `final`. Vérifier
  `grep -rn "createStub(\|createMock(" tests` avant d'ajouter `final` à un repository ou service.

### PHPStan (niveau 9)
- `json_encode(..., JSON_THROW_ON_ERROR)` plutôt que `if (false === ...)`.
- `json_decode()` + usage direct → type guards explicites (`is_array`/`is_string` + `throw new
  \LogicException`) plutôt que `assertIsArray()` si l'extension `phpstan-phpunit` est absente.
- Jamais de `@phpstan-ignore`.
- Enum backed n'a **pas** de `__toString()` — toujours `.value` explicite en Twig.

---

## Fixtures existantes (`UserFixtures` / `WorkoutFixtures`)

```php
WORKOUT_USERS = [
    ['email' => 'user-fixture-11-workout@test.com', 'count' => 11, 'spreadDays' => 42],
    ['email' => 'user-fixture-26-workout@test.com', 'count' => 26, 'spreadDays' => 90],  // female
    ['email' => 'user-fixture-51-workout@test.com', 'count' => 51, 'spreadDays' => 180], // unit = LBS
]
```
Mot de passe fixture universel : `pass_1234`. Référence : `UserFixtures::REFERENCE_PREFIX . email`.
`WorkoutFixtures::generateDates()` pioche des jours **aléatoires** dans la fenêtre `spreadDays` —
rien ne garantit qu'une séance tombe pile à la date la plus ancienne. Pour tester une frontière de
palier précise, préférer des dates fixes en dur.
TODO #17 (non résolu) : `UserFixtures` a beaucoup grossi, refactor à prévoir.

---

## TODO actifs du projet

| # | Item |
|---|------|
| 3 | Menus mobile manquants dans Profil (Bibliothèque, Mes routines, Réglages, Déconnexion) |
| 4 | SVG dynamiques (en cours) |
| 10 | Évaluer `datetimetz_immutable` pour `Workout::$performedAt` — pas encore acté |
| 15 | Migration stockage vers Scaleway Object Storage en prod (Flysystem) |
| 16 | Description d'exercice au survol (tooltip/popover) |
| 17 | Refactoriser `UserFixtures` |
| 18 | Sortir `fittracker@gmail.com` en variable d'environnement |
| 20 | Sessions persistées en base pour invalider toutes les sessions actives au changement de mot de passe |
| 21 | Notifier l'admin si un email de compte supprimé (>30j) se réinscrit |
| 22 | Migrer les emails existants vers `emails/_base.html.twig` |
| — | Renommer `templates/routine/history/` → `templates/routine/list/` |
| — | Audit CSRF sur les controllers de suppression (voir section Sécurité) |
| — | Durée de rétention du hash `DeletedAccountTrace` non fixée, purge non implémentée |
| — | Vérifier sous-collections `Routine`/`Exercise` avec fichiers physiques échappant à `deletePhysicalFiles()` |
| — | Discuter d'un split SRP de `WorkoutRepository` (625 lignes, 15 méthodes publiques : comptage, tonnage, muscles sollicités, pagination, SVG, dates — plusieurs responsabilités mélangées) |
| — | Discuter de la duplication du bloc upload d'image entre `ContactThreadReplyService`/`ContactThreadService`, et de la fusion possible des 3 méthodes `buildDailyPoints`/`buildWeeklyPoints`/`buildMonthlyPoints` de `DashboardTonnageService` (zero-fill jour/semaine/mois très similaires) |

---

## Traductions — pièges connus

- `#` dans une valeur YAML démarre un commentaire → **toujours quoter** (`"..."`) toute valeur
  contenant `#`. À vérifier systématiquement dans les 8 fichiers de langue.
- Format ICU partout (`{name}`), sauf `validators.xx.yaml` qui garde `{{ limit }}`.
- Polonais : 4 formes de pluriel requises (`=1`, `few`, `many`, `other`).
- Clés alphabétiquement triées.

---

## Commandes utiles

- Projet Composer/AssetMapper pur.
- `php bin/console asset-map:compile` — recompiler après modif JS non détectée (cache AssetMapper).
- Castor comme task runner pour PHPUnit (vérifier `castor.php` pour les tâches exactes).
- PHPStan niveau 9 obligatoire avant toute PR — jamais de `@phpstan-ignore`.
