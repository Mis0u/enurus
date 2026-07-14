# Dashboard progressif — architecture et décisions

Ce fichier est référencé depuis `CLAUDE.md`. Lire ce document avant toute intervention sur le
dashboard (widgets, paliers, régularité, tonnage).

## Principe

Le dashboard affiche des widgets progressivement, débloqués par palier — **pas pour créer de la
rétention artificielle, mais parce que ces widgets n'ont techniquement rien de valable à afficher
avant un seuil de données minimal** (une courbe à 1 point n'est pas une courbe, un delta
semaine/mois calculé sur une seule séance n'a rien de comparatif). Le système de paliers rend cette
contrainte transparente et motivante plutôt que de laisser un graphique vide/absurde.

## Paliers actés (définitifs)

| Widget | Condition de déblocage |
|---|---|
| Résumé de dernière séance | 1 séance |
| Silhouette musculaire — filtre "Séance" uniquement | 1 séance |
| Régularité (streak, séances/semaine, séances/mois) | 2 séances |
| Silhouette musculaire — filtres "Semaine" / "Mois courant" | 2 séances |
| Tonnage soulevé — total annuel + graphique en barres | 1 séance, **aucun seuil** — voir section dédiée ci-dessous |

**Comparatifs de progression : abandonné, ne sera pas construit.** Envisagé un temps à 4 séances
(cf. ancien historique de ce fichier), retiré du scope avant implémentation — `DashboardState` ne
porte plus aucun champ lié (`progressionUnlocked`/`workoutsNeededForProgression` supprimés).
L'empty state (0 séance) n'annonce donc que 2 paliers, pas 3.

### Décisions de traçage

- Séances supprimées : ne comptent jamais dans aucun seuil (et sont physiquement supprimées de la
  base — pas de soft delete workout).
- Séance saisie rétroactivement (`performedAt` dans le passé au moment de la création) : compte
  immédiatement dans les seuils, peu importe la date de saisie réelle.
- Widget affiché mais peu de variation (ex. tonnage plat car mêmes charges) : **aucun message
  spécial**, comportement normal, pas un cas à gérer.
- Compte de suppression en période de grâce RGPD (30 jours) : dashboard reste 100% fonctionnel,
  l'utilisateur ne perd rien tant que le compte n'est pas définitivement purgé.
- Widgets non débloqués : **affichés en cartes verrouillées visibles** (cadenas + condition de
  déblocage), jamais simplement absents — cohérent avec l'empty state qui liste déjà les paliers.
  Continuité UX : l'empty state (0 séance) est le même composant que le dashboard progressif, juste
  l'état "0/5 widgets débloqués".
- Fuseaux horaires : non traités spécifiquement pour le dashboard. Le reste de l'app traite
  `performedAt` en naive datetime (TODO #10 non acté) — le dashboard doit rester cohérent avec
  l'historique, pas introduire une divergence de calcul de date isolée.

## Régularité / Streak — règles métier

- Objectif = 4 séances/semaine (valeur fixe globale pour l'instant, pas de personnalisation
  utilisateur prévue au modèle `User` actuellement).
- Le streak se casse sur **une semaine à 0 séance** (pas "sous l'objectif" — 1, 2 ou 3 séances dans
  la semaine maintiennent le streak).
- Débloqué à la 2e séance (`DashboardState::WORKOUTS_NEEDED_FOR_REGULARITY`, valeur de code faisant
  foi) : avec une seule séance, streak/deltas semaine/mois n'ont pas encore de signal utile (toujours
  1 ou 0) — un léger seuil rend le widget immédiatement pertinent au lieu d'afficher une comparaison
  triviale.

## Tonnage soulevé — règles définitives (widget implémenté, v2)

**La v1 (courbe + 4 filtres Dernière séance/Semaine/Mois courant/Total, seuils indépendants par
filtre) est abandonnée.** Problèmes de fond identifiés : "Dernière séance" est structurellement
incompatible avec une courbe (un instant unique n'est pas une période) ; le filtre "Total" faisait
doublon avec le total déjà visible sur la page de détail d'une séance (`WorkoutShowController`) ;
une courbe gère mal les jours sans séance et les séances multiples le même jour.

Le widget a désormais deux zones dans le même bloc :

- **Zone 1 (total annuel, fixe)** : texte "Total sur 1 année : X kg/lbs", **jamais piloté par un
  filtre**, tonnage réellement soulevé sur l'année calendaire en cours (1er janvier → aujourd'hui,
  jamais au-delà). Aucun seuil, affiché dès la 1ère séance.
- **Zone 2 (graphique en BARRES, pas une courbe)** — décision actée : les données sont
  journalières/hebdomadaires/mensuelles, donc discrètes, une courbe gère mal les jours sans séance.
  3 filtres, tous bornés à l'année calendaire en cours :
  - **Séances** : une barre par jour où au moins une séance a eu lieu (tonnage agrégé si plusieurs
    séances le même jour — jamais deux barres pour une même date). Jusqu'à ~365 barres/an → scroll
    horizontal (voir section dédiée), pas de mécanisme clic-pour-agrandir. Labels de date en
    tooltip uniquement (pas sous chaque barre — illisible à cette densité).
  - **Semaine** : une barre par semaine calendaire (lundi → dimanche) de l'année en cours, label =
    date du lundi.
  - **Mois** : une barre par mois calendaire de l'année en cours.
  - **Aucun seuil de déblocage par filtre** (contrairement à la v1) : le widget n'est rendu que si
    `dashboardState.workoutCount > 0`, donc au moins une barre réelle existe toujours.
- **Règle critique de borne de fin** (tous filtres confondus, y compris le total annuel) : borne de
  fin = `MIN(aujourd'hui, 31 décembre année en cours)`. Un utilisateur consultant en octobre ne voit
  aucune barre pour novembre/décembre — ces mois sont **absents**, pas affichés vides.
- **Zero-fill à l'intérieur de la plage affichée** : tout jour/semaine/mois sans séance, mais dans
  le passé (donc "constatable"), apparaît comme une barre à zéro. Seul ce qui est après aujourd'hui
  est absent, jamais ce qui est avant sans donnée.
- Implémenté dans `DashboardTonnageService::getData()` (calcul du total annuel + 3 séries
  zero-fillées + largeur de scroll du filtre Séances, calculée en PHP) et
  `DashboardTonnageChartBuilder` (construction du `Chart` type `bar` via `ChartBuilderInterface`,
  SRP séparé, couleur pleine `#f43f5e`).
- `WorkoutRepository::findTonnageSeriesByUser(User $user, DateTimeImmutable $start, DateTimeImmutable $end)`
  — la sous-requête DQL corrélée existante (déjà utilisée pour `WorkoutHistoryController` et
  `WorkoutShowController`) étendue avec des bornes de date obligatoires ; un seul appel par requête
  dashboard, borné à l'année en cours, alimente à la fois le total annuel et les 3 filtres. Tout le
  regroupement par jour/semaine/mois se fait ensuite en PHP, pas de `DATE_TRUNC` SQL.
- **Scroll horizontal du filtre Séances** : `chartjs-plugin-zoom` n'est ni installé (vérifié dans
  `importmap.php`/`assets/vendor/`) ni fourni par `symfony/ux-chartjs` — ne pas l'ajouter comme
  nouvelle dépendance sans validation explicite. Solution retenue, sans nouveau package : conteneur
  `overflow-x-auto` (scroll natif, tactile inclus) enveloppant un canvas dont la largeur minimale en
  pixels (`sessionsChartMinWidth`, nb de barres × 28px) est calculée en PHP et posée en
  `style="min-width:…"` côté Twig — même pattern que le scroll vertical de
  `_base_dashboard.html.twig`.

## Graphiques — Chart.js via Symfony UX (pas npm brut)

Décision actée et **implémentée** (`composer require symfony/ux-chartjs`, fait pour le widget
Tonnage), pas `npm install chart.js`. Raisons :

- Le bundle supporte nativement AssetMapper (le projet n'utilise pas Webpack Encore) — zéro config
  supplémentaire, dépendance déjà posée sur `symfony/stimulus-bundle`.
- Construction du chart (labels, dataset, options) en PHP via `ChartBuilderInterface` injecté dans
  un service dédié — cohérent avec "logique en PHP, jamais en Twig/JS".
- Même outillage réutilisable pour les futurs graphiques du dashboard admin — pas de duplication
  d'écosystème (ChartJS npm pour l'admin + ux-chartjs pour le user serait redondant).
- Point de vigilance connu : configurer des couleurs depuis PHP n'est pas idéal (pas de variables
  CSS nativement supportées) — passer les couleurs projet (`#f43f5e`, `#06b6d4`) en hex direct
  depuis le service PHP, cohérent avec le pattern CSS sémantique déjà utilisé ailleurs.

## Composants réutilisables identifiés (ne pas recoder)

- Calcul de tonnage (sous-requête DQL corrélée) : déjà écrit pour `WorkoutHistoryController` et
  `WorkoutShowController`.
- Calcul de PR par exercice : déjà écrit dans `WorkoutShowController`.
- Dédup + tri des muscles (primaire/secondaire) : déjà en repository, réutilisé tel quel.
- Silhouette SVG + coloriage : composant `_muscles_html.twig` + controller Stimulus
  `workout--show--muscles` existants — probablement à renommer en controller plus générique si
  réutilisé aussi dans le dashboard (à trancher en session).
- Layout : `_base_dashboard.html.twig` existe déjà (blocs `title`, `topbar`, `state`).
- `WeightConverterService`, `WorkoutVoter` : à réutiliser tels quels.

## `DashboardUnlockService` — à créer, n'existe pas encore

Service dédié qui détermine, pour un user donné, quels widgets afficher selon son état (nombre de
séances, ancienneté de la première séance). Séparé des services de calcul de données eux-mêmes
(SRP strict) — un service décide *quoi* afficher, d'autres décident *comment* calculer. Doit
retourner un état par widget (`unlocked` / `locked` + condition restante), consommé par un seul
template dashboard qui affiche soit les données réelles, soit l'état verrouillé.

## Fixtures à créer pour ce chantier

Les 3 users `WORKOUT_USERS` existants (11/26/51 séances) dépassent déjà tous les seuils — bons pour
tester le dashboard complet, mais aucun ne permet de tester un palier intermédiaire isolément. Il
manque, à créer avec des **dates fixes en dur** (pas la génération aléatoire de
`generateDates()`, pour fiabiliser les tests de frontière) :

1. Un user à **1 séance** — palier 1 uniquement, widget Tonnage affiché (total annuel + 1 barre)
   sans aucun autre seuil à tester (v2 n'a plus de seuil par filtre).
2. Un user à **2-3 séances récentes**, toutes dans la même semaine calendaire — palier 2 débloqué,
   utile pour vérifier le zero-fill des jours/semaines/mois sans séance sur les filtres Séances,
   Semaine et Mois.

## Points encore ouverts / non tranchés à date

- Nom exact/chemin du futur `DashboardUnlockService` (ou fusion dans un autre service).
- Si le controller Stimulus `workout--show--muscles` est renommé/généralisé pour le dashboard, ou
  dupliqué (à trancher selon si sa logique est strictement liée à "workout show" ou déjà générique).
- Le TODO #10 (`datetimetz_immutable`) reste non traité pour le dashboard — traiter comme le reste
  de l'app (naive datetime) tant que ce n'est pas résolu globalement.
