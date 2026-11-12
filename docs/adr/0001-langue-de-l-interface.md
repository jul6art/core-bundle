# ADR-0001 — La langue de l'interface : choix, mémoire, application à la requête

- **Statut** : Accepted — 2026-11-12 (validé d'avance par jul6art : « écris l'ADR du lot 4, et dev le
  direct, je valide »)
- **Décideurs** : jul6art
- **Origine** : audit Fable 5.2, T20 (lot 4, troisième lot du chantier de remontée)

## Contexte

Chaque application recopie du squelette quatre classes pour la langue de l'interface :

| Classe | Rôle |
|---|---|
| `LocalePreference` | où vit le choix (session, compte) et dans quel ordre on le relit |
| `LocaleListener` | applique la langue à la requête **et** la rediffuse aux services `kernel.locale_aware` (priorité 7, après le pare-feu) ; depuis octobre, donne aussi sa langue à la page 404 |
| `LocaleController` | `POST /locale` (jeton `locale_switch`, langue dans le corps, 204), le contrat du contrôleur Stimulus `ui--locale-switcher` d'admin-bundle |
| `LocaleExtension` | `backoffice_locales()` et `locale_flag()` (drapeaux SVG) pour le sélecteur |

Mesure du 2026-11-12 après le lot 1 (`superp/docs/analyse/audit_fable_5_2_t20_copies.md`) :
`LocaleListener` 10 copies / 7 variantes, `LocalePreference` 7 / 3, `LocaleController` 8 / 3,
`LocaleExtension` 9 / 4. La liste des langues (`BackofficeLocales`) reste propre à chaque produit.

Règle 3 du plan de rangement : la langue n'a pas de domaine fonctionnel, sert aussi aux sites publics
sans back-office, et core-bundle a déjà `Translation/`, `Twig/` et `Controller/` — c'est la seule entrée
dans core-bundle de tout le chantier. Le gabarit du sélecteur reste dans l'application.

### Les écarts, un par un

| Où | Écart | Classement |
|---|---|---|
| devinthehood, wtg, tradebot, squelette | session puis compte ; 404 : langue de l'adresse puis préférence (tradebot, wtg : préférence seule) | **référence**, la plus récente : devinthehood |
| cereezer | + l'en-tête `Accept-Language` en dernier (sous-étiquette primaire : `fr-BE` vaut `fr`) ; seconde route `/portal/locale` pour son pare-feu du portail | **besoin du produit** : une source de plus ; la route est l'affaire de l'application |
| cegeta | `_locale` de la route d'abord, puis session, compte, `?lang=`, `Accept-Language`, **pays** (`CF-IPCountry`, `CountryLanguage`) ; cinq langues (nl, de, lb en plus, avec leurs drapeaux) | **besoin du produit** : sources configurables + une source de l'application (le pays) ; les drapeaux vont au bundle |
| devingame | la langue de l'adresse (`/fr/…`) gagne **et** se retient dans une session déjà ouverte — jamais une session ouverte pour ça | **besoin du produit** : option `remember: [route]` |
| devinlive | compte avant session, cookie `user_locale` (un an), `Accept-Language` ; écouteur à la priorité 20 (avant le pare-feu : le compte n'y est pas encore), qui écrit session et cookie à **chaque** requête ; sélecteur public `GET /change-locale/{locale}` | ordre et cookie : configurables ; écriture à chaque requête et priorité 20 : **dérive**, abandonnée ; sélecteur public : reste dans l'application, sur le service du bundle |
| squelette, modes web et admin | `?_locale=` bascule et se retient en session ; pas de compte | option `query_parameter: _locale` + `remember: [query]` |
| superp | aucune des trois classes de mécanique ; sa `LocaleExtension` porte surtout ses fonctions de pays, ses drapeaux sont des emoji, sa bascule vit dans `SecurityController` (jeton et route à lui) | **hors du lot** : rien de recopié à remonter, superp ne change pas |
| devinthecode | pas de back-office Twig | non concerné |

## Décision

### 1. core-bundle livre la langue de l'interface (3.11.0)

Sous `Translation/Locale/` :

- **`SupportedLocales`** — la liste offerte et la langue par défaut (configuration).
- **`LocaleSourceInterface`** — une source de candidat : `locale(Request, ?UserInterface): ?string`. Le
  candidat est brut ; c'est `LocalePreference` qui le valide.
- **Les sources livrées**, nommées : `route` (l'attribut `_locale`), `session` (`_locale`), `account` (la
  propriété du compte, `locale` par défaut, lue et écrite par PropertyAccess — aucune interface à
  implémenter), `query` (le paramètre configuré), `cookie` (le cookie configuré), `accept_language`
  (la première langue annoncée que l'application parle, sous-étiquette primaire, `null` sinon — jamais
  le premier de la liste, qui rendrait les rangs suivants inatteignables).
- **`LocalePreference`** — `resolve()` : la première source qui rend une langue **offerte** ; une valeur
  non offerte est ignorée et la suivante reprend la main. `remember()` : la session, le compte (et son
  `flush`), et le cookie sur la réponse quand il est configuré.
- **`LocaleListener`** — la requête à la priorité 7 et la rediffusion ; la page 404 (langue de
  l'adresse, puis préférence sans compte, la session d'un visiteur rouverte) ; la mémoire des sources
  listées dans `remember` — `route` dans une session déjà ouverte seulement, `query` (une bascule
  explicite) toujours.

Et :

- **`Controller\LocaleController`** — `POST`, jeton `locale_switch`, langue dans le corps, 404 pour une
  langue non offerte, 403 pour un jeton invalide (`AccessDeniedHttpException`, jamais l'exception de
  sécurité qu'un pare-feu changerait en redirection), 204. **Aucune route déclarée** : l'application la
  nomme (`admin_locale_switch`, `/locale`), et peut en déclarer plusieurs (cereezer, son portail).
- **`Twig\LocaleExtension`** — `backoffice_locales()` et `locale_flag()`, drapeaux SVG pour en, fr, nl, de,
  lb (chaîne vide pour une autre langue). Les noms ne changent pas : les gabarits des applications non
  plus.

### 2. Configuration, désactivée par défaut

```yaml
core:
    locale:
        enabled: true
        supported: !php/const App\Security\BackofficeLocales::SUPPORTED   # défaut : kernel.enabled_locales
        default: !php/const App\Security\BackofficeLocales::DEFAULT       # défaut : kernel.default_locale
        sources: [route, session, account]   # l'ordre est le sens ; un id de service = une source de l'application
        account_property: locale
        query_parameter: lang
        cookie: { name: '', lifetime: 31536000 }
        remember: []                          # route et/ou query
```

Désactivée : une application garde son propre écouteur tant qu'elle n'active pas la clé — sinon deux
écouteurs se disputeraient la requête.

### 3. Ce qui reste dans chaque application

| Application | Après le lot |
|---|---|
| devinthehood, wtg, tradebot, squelette (backoffice) | `core.locale` (sources par défaut), la route du sélecteur |
| cereezer | + `accept_language` ; ses deux routes (back-office et portail) |
| cegeta | sources `[route, session, account, query, accept_language, App\…\CountryLocaleSource]` ; sa carte des pays devient une source |
| devingame | `remember: [route]` |
| devinlive | sources `[account, session, cookie, accept_language]`, `account_property: preferredLocale`, cookie `user_locale` ; son `GET /change-locale/{locale}` reste, sur `LocalePreference` ; la liste des langues sort de `LocaleService` vers la configuration |
| squelette (web, admin) | `query_parameter: _locale`, `remember: [query]`, sources `[query, session]` |
| superp | inchangé |

Partout : `BackofficeLocales` (la liste du produit) reste et alimente `supported`/`default`. Les quatre
classes recopiées disparaissent.

## Comportements qui changent (assumés)

- **devinlive** : l'écouteur tourne après le pare-feu — la langue du **compte** s'applique enfin (à la
  priorité 20, `Security::getUser()` n'avait pas encore de jeton) ; la session et le cookie ne sont plus
  écrits à chaque requête, seulement au choix explicite.
- Partout ailleurs : aucun — mêmes sources dans le même ordre, mêmes routes, même jeton, même 204.

## Tests exigés

- **Bundle** : chaque source (dont `accept_language` : `fr-BE`, `en_US`, aucune langue gérée → `null`) ;
  `LocalePreference` (ordre, valeur non offerte ignorée, `remember` : session, compte écrit et persisté,
  cookie) ; l'écouteur (langue appliquée **et** rediffusée au traducteur ; 404 sous `/fr/…` ; `remember`
  route sans ouvrir de session, query) ; le contrôleur (204, jeton invalide 403, langue inconnue 404,
  compte et session écrits) ; l'extension (liste, drapeaux) ; la configuration (désactivée par défaut,
  défauts du noyau) ; dépendances hautes et basses ; PHPStan, Rector, style.
- **Chaque application** : ses tests existants joués avant et après (`LocaleSwitchTest`,
  `LocaleCatalogueTest`, cegeta `LocaleNegotiationTest`) ; porte complète. **Dans Chrome, avant et
  après** : basculer la langue par le sélecteur (204, page rechargée dans l'autre langue), recharger
  (la langue tient), et une adresse inconnue rend la 404 dans la langue choisie.
- **Squelette** : les cinq modes générés, leur porte jouée ; le parcours Chrome sur un backoffice généré.

## Conséquences

- core-bundle 3.11.0 (version mineure, badge). Les applications montent leur plancher.
- Démonstrations d'abord (cereezer, tradebot, wtg, devinthehood), puis devingame, cegeta, devinlive.
