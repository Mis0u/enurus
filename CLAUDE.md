# Enurus — CLAUDE.md

Application de suivi de musculation. Développeur solo (Misou). Symfony 7.4 / PHP 8.4.
**Toujours discuter et valider l'architecture avant d'écrire du code** — ne jamais coder
directement sur une demande de feature sans avoir fait valider l'approche.

## Docs par sujet — à lire seulement si le sujet est concerné
- Chantier dashboard progressif (widgets, paliers, streak, Chart.js) → lire `docs/dashboard-architecture.md`
- Avant toute création de fichier ou refactoring → lire `docs/consignes.md`
  (Clean Code, SOLID, tests TDD)

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
- **Code propre non négociable** : nommage explicite, fonctions courtes (~20 lignes,
  1 responsabilité, 0-2 arguments idéalement), SOLID appliqué systématiquement,
  règle du boy-scout à chaque passage dans un fichier. Détail complet dans
  `docs/consignes.md` — à lire avant toute création de fichier.

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
- `orphanRemoval: true` sur toutes les relations parent→enfant. `cascade: ['persist','remove']` pour les
  **compositions internes construites en mémoire puis flushées ensemble** (`Workout→WorkoutExercise→
  ExerciseSet`, `Routine→RoutineExercise` — ajoutées via `CollectionType`/DataTransformer, jamais
  persistées individuellement). `cascade: ['remove']` seul pour les relations `User→{Exercise,Workout,
  Routine,ContactThread}` : ces enfants sont toujours persistés indépendamment par leur propre service
  (ex. `ExerciseCreateService::create()` fait `$em->persist($exercise)` directement), le cascade persist
  depuis `User` ne servirait jamais — Doctrine Doctor flag ce cas comme "Bidirectional Association
  Inconsistency", c'est un faux positif connu du tool sur ce pattern.
- `performedAt` est `datetime_immutable`, sans timezone — **décision actée**, pas un oubli :
  `performedAt` représente une heure murale saisie par l'utilisateur ("séance à 8h"), pas un
  instant absolu. Passer en `datetimetz_immutable` ferait glisser la date d'un jour pour un
  utilisateur en fuseau extrême lors de la conversion UTC↔local. `datetimetz` reste réservé aux
  champs sécurité/audit (`lastLogin`, expiration de token) qui représentent un vrai instant
  absolu à comparer entre fuseaux. Doctrine Doctor signale ce mélange comme suspect
  ("Inconsistent Timezone Usage") — faux positif connu, ne pas y toucher. Traiter les nouvelles
  features de dates métier de la même façon (naive datetime), pour ne pas créer d'incohérence
  entre pages.
- Exercices publics (`isPublic = true`, `owner = null`) : jamais affectés par la cascade de
  suppression de compte utilisateur.

---

## Sécurité

Un Voter par entité avec attributs `_VIEW`/`_EDIT`/`_DELETE` (ex. `WorkoutVoter`, `RoutineVoter`,
`ExerciseVoter`). Règle unique et centralisée : `$entity->owner === $user`. Jamais de vérification
de propriété dupliquée dans un controller. Pas de voter `_LIST` : une liste n'est pas une ressource
individuelle, `#[IsGranted('ROLE_USER')]` + filtre `owner` en repository suffisent.

Endpoints XHR (delete, etc.) vérifient `$request->isXmlHttpRequest()` — protection faible (header
falsifiable) mais barrière de base contre les appels directs, doublée d'une vraie validation CSRF
(voir ci-dessous).

**CSRF sur les endpoints de suppression** : `App\Controller\Trait\ValidatesDeleteRequestTrait`
(`denyUnlessXmlHttpRequest()` + `denyUnlessValidCsrfToken()`) centralise les deux contrôles,
utilisé par tous les controllers de suppression (`RoutineDeleteController`, `WorkoutDeleteController`,
`ExerciseDeleteController`, `SettingsAvatarDeleteController`, `ContactThreadDeleteController`).
Le token est généré via `csrf_token('<entité>_delete_' ~ entity.id)` en Twig, transmis en header
`X-CSRF-Token` par `assets/utils/delete_confirmation.js` (`sendDeleteRequest()`). En test, jamais
régénérer le token via `CsrfTokenManagerInterface::getToken()` hors requête
(`SessionNotFoundException`) — le lire depuis le DOM déjà rendu via `Crawler`
(`FunctionalTestTrait::csrfTokenFromPage()`).

**Trace de compte supprimé** : `AccountDeletionService::purgeExpired()` enregistre un hash
SHA-256 de l'email (jamais l'email en clair) dans `DeletedAccountTrace`, à la suppression
effective du compte (pas à la demande). Purgé 6 mois après cette suppression effective par
`app:deleted-account-trace:purge` (`DeletedAccountTracePurgeCommand`) — délibérément plus long que
la rétention du compte lui-même (30 jours), pour une fenêtre de détection de réinscription
réaliste. À la confirmation d'email (pas à l'inscription, cf. ci-dessous),
`DeletedAccountReregistrationNotifierService::notifyIfReregistration()` (appelé depuis
`UserRegistrationService::completeRegistration()`) compare le hash du nouvel email à
`DeletedAccountTrace` et, en cas de correspondance, crée un `ContactThread` (message interne,
même mécanisme que `RegistrationWelcomeThreadService`) **appartenant au compte admin**
(`ADMIN_USER_EMAIL`, même paramètre env que le fil de bienvenue) plutôt qu'un email — apparaît
comme non lu dans la messagerie de l'admin. Ne bloque jamais l'inscription.

**Vérification d'email obligatoire** (`symfonycasts/verify-email-bundle`, pas de config dédiée —
contrairement à `reset-password-bundle`, ce bundle ne persiste rien en base, tout est dans un lien
signé avec expiration embarquée, 1h par défaut). `User::$isVerified` (défaut `false`) bloque le
login via `BlockedUserChecker::checkPreAuth()` (même classe que le blocage de compte, un seul
`user_checker` par firewall) — message dédié `security.account_not_verified`.
`UserRegistrationService::registerUser()` ne fait que créer le compte + envoyer l'email de
confirmation (`EmailVerificationService::sendConfirmationEmail()`) ; **aucune connexion auto**, pas
de fil de bienvenue, pas de check anti-réinscription à ce stade — tout ça est déplacé dans
`completeRegistration()`, déclenché uniquement par `EmailVerificationController` au clic sur le
lien. `VerifyEmailHelperInterface::validateEmailConfirmationFromRequest()` n'étant pas déclarée sur
l'interface (seulement `@method` sur la classe concrète `VerifyEmailHelper`, pour compat BC),
`EmailVerificationService` type-hint la classe concrète tout en forçant l'autowiring sur l'alias
public de l'interface via `#[Autowire(service: VerifyEmailHelperInterface::class)]` — évite
`validateEmailConfirmation()` (dépréciée depuis 1.17). `generateSignature()` a besoin de `'id'`
explicitement dans `$extraParams` (sinon absent de l'URL générée, faute d'erreur silencieuse à
l'usage) et de `'_locale'` pour respecter le préfixe de route obligatoire. Comptes fixtures créés
via `UserFixtures::createUser()` : toujours `isVerified = true` (jamais concernés par ce flux).

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
- `AttachesContactImageTrait` (`src/Service/Contact/`) : logique d'attache d'image optionnelle à
  un `ContactThreadMessage`, partagée entre `ContactThreadService::create()` et
  `ContactThreadReplyService::reply()` — la garde de nullité sur l'id de l'auteur reste dans
  chaque service appelant (le trait reçoit `$ownerId` déjà résolu, pas l'entité `User`).

### Requêtes Doctrine
- **Ne jamais `setMaxResults()` sur une requête avec JOIN sur une collection one-to-many** — le
  LIMIT s'applique aux lignes SQL, pas aux entités → perte silencieuse de données. Utiliser une
  sous-requête DQL corrélée à la place (déjà fait pour le tonnage).
- PR (poids max) calculé sur `weight` seul, jamais `weight × reps` — convention musculation.
- Muscles d'un workout dédupliqués en PHP : `PRIMARY` si primaire dans au moins un exercice.
  Tri primaires→secondaires en repository.
- Toutes les données lourdes précalculées dans le controller, passées à la vue en maps indexées —
  zéro requête N+1, zéro logique de calcul en Twig.
- **`WorkoutRepository` scindé par responsabilité** (ex-625 lignes/15 méthodes) : `WorkoutRepository`
  garde le cœur entité (`countByUser`, `findLatestByUser`, `findByUserPaginated`) et reste seul
  `ServiceEntityRepository<Workout>` ; `WorkoutMuscleRepository` (muscles sollicités + SVG,
  non-final car stubbé par `DashboardMuscleDistributionServiceTest`), `WorkoutTonnageRepository`
  (tonnage) et `WorkoutStatsRepository` (comptages/dates/totaux exercices-reps) sont de simples
  services injectés avec `WorkoutRepository` pour réutiliser `createQueryBuilder('w')` — pas de
  vrais repositories Doctrine, la convention de nom `...Repository` est gardée par cohérence.

### Pagination (KnpPaginatorBundle)
- Composant Twig partagé `templates/_pagination/_pagination.html.twig` (+ `_selector`,
  `_previous_page`, `_page_number`, `_next_page`) utilisé par Mes séances, Mes routines et
  Messagerie — mêmes variables partout : `pagination` (`PaginationInterface`), `route` (nom de
  route), `routeParams` (map de paramètres additionnels, ex. filtres date/type ; `{}` si aucun),
  `limitAllowed` (int[] du sélecteur "par page"). Fenêtre glissante native de Knp
  (`pagination.paginationData`, `page_range: 5` dans `config/packages/knp_paginator.yaml`), pas de
  calcul d'ellipsis fait main.
- Boutons "première page"/"dernière page" affichés uniquement quand la fenêtre glissante ne couvre
  pas déjà cette borne (`data.firstPageInRange > 1` / `data.lastPageInRange < data.last`, valeurs
  déjà fournies par `paginationData` — aucun changement de repository/controller nécessaire). Même
  logique côté JS Bibliothèque (`firstBtnTarget.hidden` / `lastBtnTarget.hidden`).
- Les repositories exposant une liste paginable retournent un `QueryBuilder` (jamais `getResult()`),
  y compris quand la requête fait un `JOIN` `addSelect` sur une collection one-to-many (ex.
  `ContactThreadRepository::findByOwnerOrderedByActivity` avec les messages) : le subscriber
  Doctrine ORM de Knp enveloppe la query dans `Doctrine\ORM\Tools\Pagination\Paginator` avec
  `fetchJoinCollection: true` par défaut, qui gère correctement LIMIT/OFFSET sur les lignes jointes
  — contrairement à un `setMaxResults()` manuel (règle ci-dessus).
- Bibliothèque (Exercise) reste une exception volontaire : pagination client-side en JS
  (`assets/controllers/exercise/list_controller.js`), dataset borné (référentiel public + exercices
  perso d'un seul user). Le rendu de sa fenêtre de pages (`#computePagesInRange`) reproduit à la
  main l'algorithme de `SlidingPagination::getPaginationData()` (même fenêtre de 5, même
  comportement aux bornes) pour une cohérence visuelle avec les 3 listes server-side, sans
  dupliquer leur mécanisme serveur.

### SVG / silhouette musculaire
- Composant : `{% include 'SVG_BODY/' ~ gender.value ~ '/front.html.twig' %}` (+ `/back.html.twig`).
- Couleurs réelles du projet (celles de la création d'exercice, **source de vérité**) : primaire
  `#f43f5e` (rose), secondaire `#06b6d4` (cyan, `rgb(6,182,212)` — même token que `--color-cyan-ft`,
  pas de 3ᵉ teinte dédiée). Posées en CSS sémantique, pas inline.
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
- **flatpickr** (`assets/controllers/workout/date-picker_controller.js`) pose `readonly` sur l'input
  tant que `allowInput` n'est pas explicitement activé (comportement par défaut, volontaire pour
  forcer la saisie via calendrier) — un champ `readonly` est barré de la validation de contrainte
  HTML5 par la spec W3C, donc `required` + `form.reportValidity()` ne s'y déclenchent jamais, même
  si le HTML généré côté serveur ne montre pas `readonly` (ajouté par flatpickr au runtime, invisible
  en vue source). Pour tout champ requis piloté par flatpickr : garde JS explicite (SweetAlert dédiée,
  même pattern que `workout.error.no_exercise`) plutôt que de compter sur la validation native. Idem
  côté Playwright : `.fill()` échoue son check d'editability sur ce champ — utiliser
  `input._flatpickr.setDate(date, true)` (cf. `tests/e2e/helpers.ts:fillDatePicker()`), qui déclenche
  un vrai `change`/`input` DOM comme une sélection manuelle.
- Thème CSS d'une lib importée en JS (ex. `import 'flatpickr/dist/flatpickr.min.css'`, même pattern
  que Quill) : ce CSS s'injecte après les `<link>` statiques du `<head>`, donc après tout thème
  custom qui le précède — à spécificité égale, la lib gagne la cascade. Surcharges en `!important`
  dans le fichier de thème custom (`assets/styles/date-picker.css`), pas de contournement plus propre
  trouvé pour ce cas précis.

### CSS / Tailwind v4
- `!important` = **suffixe** (`hidden!`), pas préfixe (`!hidden` = syntaxe v3 invalide).
- Toute classe arbitraire avec une **virgule** (`grid-cols-[minmax(0,1fr)_380px]`, certains
  `rgba()`) casse le parser de candidats Tailwind v4 → fragile, à vérifier immédiatement (`grep`
  dans `var/tailwind/app.built.css`, pas le fichier source `assets/styles/app.css`).
- Classes Tailwind avec variables CSS custom non résolues en JIT dans ce contexte → classes
  sémantiques dans un `.css` dédié avec valeurs hex directes.
- **Jamais de classe Tailwind construite par interpolation Twig** (`class="w-{{ width|default(5) }}"`,
  `class="text-{{ color }}"`) — Tailwind scanne le texte source des fichiers, il ne peut pas
  résoudre une variable Twig, donc la classe finale (`w-3.75`, `text-[#4a5568]`, etc.) n'apparaît
  jamais comme chaîne littérale contiguë et n'est **jamais générée** dans le CSS compilé. Symptôme :
  élément (souvent une icône SVG) à la taille par défaut du navigateur (~300×150px), cassant toute
  la mise en page — `rm -rf var/cache` / `castor assets` / redémarrage du serveur ne corrigent rien
  car le bug est dans la détection de candidats Tailwind, pas dans un cache. Déjà rencontré sur
  `templates/partials/_svg/*.html.twig` (props `width`/`height`/`color`) : corrigé via
  `@source inline("...")` dans `assets/styles/app.css` qui force la génération des classes
  utilisées dynamiquement. Toute nouvelle valeur numérique ajoutée à un de ces partials doit être
  ajoutée à la liste `@source inline(...)`, sinon même symptôme.
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
- **`messenger.transport.async` (route de `SendEmailMessage`) n'est pas fiable à interroger dans un
  test fonctionnel** : le DSN pointe une vraie table Doctrine (`doctrine://default?auto_setup=0`,
  aucun override en `.env.test`), pas un `InMemoryTransport` — un worker `messenger:consume`
  concurrent sur la machine peut déjà avoir consommé le message avant l'assertion, rendant le
  compte de messages instable. Tester la logique métier d'envoi au niveau du service (mock
  `EmailInterface`) plutôt qu'en comptant les messages du transport dans un test d'intégration.

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

---

## TODO actifs du projet

| # | Item |
|---|------|
| 15 | Migration stockage vers Scaleway Object Storage en prod (Flysystem) — **fait côté code** : adaptateur `aws` configuré dans `config/packages/flysystem.yaml` (actif uniquement en `when@prod`, credentials via `SCALEWAY_ACCESS_KEY`/`SCALEWAY_SECRET_KEY`/`SCALEWAY_BUCKET_NAME`/`SCALEWAY_REGION`/`SCALEWAY_ENDPOINT`, jamais commités). Les 6 endroits qui construisaient l'URL publique d'un fichier en dur (`'/uploads/' ~ path`, casserait avec un stockage distant) sont passés par le filtre Twig `storage_url` (`src/Twig/Extension/StorageUrlExtension.php`), qui s'appuie sur l'option `public_url` du storage (`/uploads` en dev, `SCALEWAY_PUBLIC_URL` en prod) — même code, comportement correct dans les deux environnements. Reste à faire : créer le bucket réel chez Scaleway et renseigner les variables d'environnement dans Scalingo. |
| 20 | Nom du produit acté : **Enurus** (remplace FitTracker, déjà pris). Renommage du code fait (`brand+intl-icu.*.yaml`, textes en dur). `FROM_EMAIL` extrait en variable d'environnement (cf. #18, résolu) avec la valeur définitive `no-reply@enurus.com` (adresse à sens unique, aucune boîte de réception nécessaire — l'alignement DMARC/SPF repose sur l'authentification du domaine chez Brevo). Reste à faire côté Misou : dossier local et repo GitHub (`fit-tracker`) à renommer manuellement. |
| 21 | CI en place (GitHub Actions : PHPStan, ECS, TwigCS, PHPMND, ESLint, PHPUnit, Vitest, composer audit — branche protégée sur `main` avec check `qa` requis). Psalm taint, mutation testing (Infection) et Playwright e2e restent volontairement locaux/manuels (lancés avant push), pas en CI. `Procfile` créé (`postdeploy` migrations + `worker` messenger, cf. #24). Reste à définir : la CD (workflow GitHub Actions qui `git push` vers Scalingo après un `qa` vert sur `main`) |
| 22 | Mise en ligne du site — plan validé avec Misou (aucune connaissance devops, préférence PaaS managée). **Fait** : nom de domaine **`enurus.com`** acheté chez **Cloudflare Registrar** (prix au coût réel, DNS déjà unifié) ; app Scalingo **`enurus`** créée (région `osc-fr1`, Paris, déploiement en mode Git direct — pas l'intégration GitHub native, pour garder le déploiement gated par la CI) avec addon PostgreSQL **Starter 512M** (7,2€/mois, essai gratuit 29 jours actif) ; `FROM_EMAIL` réglé sur `no-reply@enurus.com` (cf. #20) ; monitoring d'erreurs **Sentry** intégré (`sentry/sentry-symfony`, actif en prod uniquement via `config/bundles.php` + `config/packages/sentry.yaml`, `SENTRY_DSN` à renseigner). Reste à faire : comptes **Brevo** et **Scaleway** à créer, DNS chez Cloudflare (SPF/DKIM/DMARC fournis par Brevo, CNAME vers Scalingo), premier déploiement, puis scaler manuellement le container `worker` à 1 sur le dashboard Scalingo (reste à 0 par défaut après déploiement). Une fois stabilisé, brancher la CD, cf. #21. |
| 23 | Résilience en cas d'afflux massif (scénario "influenceur" : ~100k inscriptions en une journée) — décisions actées avec Misou : **compte Brevo payant** dès le lancement (le plan gratuit à 300 emails/jour bloquerait la quasi-totalité des utilisateurs à l'étape de vérification d'email obligatoire, `BlockedUserChecker` + `isVerified`). Points à anticiper avant tout pic prévisible (à traiter le moment venu, pas dans l'immédiat) : dimensionnement du tier Postgres Scalingo (connexions/IOPS — chaque requête écrit une session via `DoctrineSessionHandler`, en plus des `User` créés), nombre de workers `messenger:consume` (actuellement un seul consommateur pour la queue Doctrine partagée par les emails et les notifications Telegram admin, cf. #24, deviendrait le goulot d'étranglement même avec Brevo payant), et scaling des containers Scalingo (réactif, pas instantané face à un pic soudain). |
| 24 | Notification admin par Telegram (`ContactThreadAdminNotifierService`) sur nouveau message/réponse utilisateur dans un `ContactThread` — `ChatMessage` routé vers le transport `async` (même queue Doctrine que `SendEmailMessage`, cf. `config/packages/messenger.yaml`). **Rien à faire en dev** : sans worker actif les notifs restent en attente en base, lancer `php bin/console messenger:consume async` manuellement suffit pour tester. **En prod (au moment de #22)**, il faudra déclarer un process type `worker` Scalingo dédié exécutant `messenger:consume async` en continu (supervisé, redémarré automatiquement) — aujourd'hui aucun worker ne tourne nulle part, ni les emails ni les notifs Telegram ne partent réellement sans ça. |

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

## Commits

- **Gitmoji obligatoire** : chaque commit commence par un emoji gitmoji pertinent
  (ex. `✨` feature, `🐛` fix, `♻️` refactor, `✅` tests, `📝` docs, `🔧` config).
- **Titre** : 48 caractères maximum (emoji inclus), toujours en anglais, commence par une majuscule.
- **Message de corps** : optionnel, à ajouter si le commit nécessite un contexte
  que le titre seul ne porte pas (raison d'un choix, effet de bord, breaking change).
  En anglais également.
