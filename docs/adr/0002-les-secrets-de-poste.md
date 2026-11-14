# ADR-0002 — Les secrets d'un cache `prod` sur un poste : `bin/dev-secrets`

- **Statut** : Accepted — 2026-11-12 (demande de jul6art : « améliore ces 3 points », point 2)
- **Décideurs** : jul6art
- **Origine** : la commande `make secrets` du squelette, relevée pendant la remontée de la langue de l'interface dans core-bundle

## Contexte

Le garde des secrets (3.9, T09) refuse un cache `prod` tant qu'un secret est vide, un exemple de recette
ou la valeur que `.env` versionne — sur un serveur comme sur un poste. Pour qu'un poste puisse quand même
construire un cache `prod`, T25 a recopié dans chaque application un script `bin/dev-secrets`, appelé par
`make secrets` : il écrit dans `.env.local` les secrets qui manquent, en reprenant la valeur que le poste
utilise déjà (`.env.dev.local`, `.env.dev`, `.env.docker`), pour que le hub, le stockage et la paire JWT
locaux suivent.

Mesure du 2026-11-12 : **dix copies** (neuf applications et le squelette), **deux variantes** (anglaise :
superp, devingame, devinthecode ; française : les autres), même logique. Deux défauts :

1. **Il reprend une valeur que le garde refuse.** `MERCURE_JWT_SECRET` d'exemple
   (`!ChangeThisMercureHubJWTSecretKey!`) chez superp, cereezer, cegeta, wtg (`.env.docker`) et
   devinthecode (`.env.dev`) ; `JWT_PASSPHRASE` d'exemple chez superp (`.env.docker`). Le cache `prod`
   qu'il devait rendre possible reste refusé.
2. **Il écrit dans `.env.local`, que l'environnement `dev` lit aussi** (sous `.env.dev`). Une valeur
   neuve tirée là change ce que la pile de développement signe ou déchiffre partout où `.env.dev` ne
   redéfinit pas la variable — chez superp, cereezer, cegeta et wtg pour `MERCURE_JWT_SECRET`. C'est ce
   qui interdisait de corriger le premier défaut en tirant simplement une valeur neuve.

Sa liste des valeurs d'exemple n'existait nulle part : elle est celle de `SecretsGuard::PLACEHOLDERS`,
dans core-bundle.

## Décision

1. **Le script vit dans core-bundle** (`bin/dev-secrets`, déclaré dans `composer.json` : `vendor/bin/
   dev-secrets`), à côté du garde dont il suit les règles. Lancé depuis la racine du projet
   (`PROJECT_DIR` sinon), il refuse de tourner ailleurs. Les dix copies disparaissent ; `make secrets`
   appelle `vendor/bin/dev-secrets`.
2. **`.env.prod.local` par défaut** : Symfony ne le lit qu'en `prod`. Ce que le script tire ne touche
   jamais la pile de développement. Un autre fichier reste possible (`vendor/bin/dev-secrets .env.docker`).
3. **Une valeur du poste n'est reprise que si le garde l'accepte** : vide, exemple de recette ou valeur
   versionnée → une neuve, et le script le dit (le nom, jamais la valeur). Le poste est lu dans l'ordre où
   la pile de développement le lit : `.env.dev.local`, `.env.dev`, `.env.local`, `.env.docker`.
4. **La paire JWT n'est régénérée que sans aucune phrase de poste** (comportement d'avant). Une phrase
   refusée est remplacée dans `.env.prod.local`, et la paire locale reste celle de développement : un
   lancement `prod` sur le poste ne signe alors aucun jeton — le script le dit.
5. **Ses valeurs d'exemple sont celles du garde, mot pour mot** : `Tests/Tooling/DevSecretsTest` les
   compare, et prouve sur un projet jetable ce que le garde accepte de `.env` + `.env.local` +
   `.env.prod.local`.

## Conséquences

- core-bundle 3.12.0 (mineure : un binaire de plus ; le badge n'écrit que la majeure).
- Chaque application : core-bundle ^3.12, `make secrets` → `vendor/bin/dev-secrets`, `bin/dev-secrets`
  retiré. Le squelette aussi (`common/overlay`).
- Les `.env.local` déjà écrits par l'ancien script restent : `.env.prod.local` passe devant en `prod`.
