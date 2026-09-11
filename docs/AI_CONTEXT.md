SmartSchool — Contexte projet pour les IA

Document de référence technique destiné à Claude, ChatGPT, Codex et aux autres assistants IA travaillant sur SmartSchool.

Dernière mise à jour : septembre 2026

1. Présentation du projet

SmartSchool est une plateforme professionnelle de gestion scolaire destinée principalement aux établissements scolaires de la République démocratique du Congo (RDC).

Objectif : digitaliser et centraliser la gestion quotidienne d'une école : élèves, enseignants, parents, administration, comptabilité, frais scolaires, paiements, notes, présences, inscriptions, bulletins, statistiques, communication, notifications et structure académique.

SmartSchool doit être conçu comme un véritable produit logiciel professionnel et évolutif, et non comme un simple prototype académique.

Modèle actuel

SmartSchool est actuellement pensé comme une plateforme de gestion pour un établissement scolaire à la fois. Ne pas transformer automatiquement le projet en SaaS multi-écoles sans décision explicite du propriétaire.

2. Technologies

Backend

PHP 8.2

PDO

MySQL / MariaDB

Frontend

HTML5

CSS3

JavaScript

Chart.js lorsque nécessaire

Boxicons pour les icônes

Environnement local

Windows

XAMPP

Apache

MySQL

Applications prévues

Electron pour desktop

Capacitor pour mobile

Les versions desktop/mobile doivent réutiliser autant que possible l'application web existante sans réécrire inutilement tout le projet.

3. Emplacement du projet

Projet local :

C:\xampp\htdocs\SmartSchool

URL locale :

http://localhost/SmartSchool/

Dépôt GitHub :

https://github.com/Roberto-shinga/Roberto-SmartSchool

Branche principale :

main

4. Architecture générale

SmartSchool/
├── admin/
├── accountant/
├── auth/
├── config/
├── database/
├── includes/
├── student/
├── teacher/
├── parent/
├── superadmin/
├── assets/
├── docs/
├── bootstrap.php
├── index.php
└── .gitignore

Les chemins et noms de dossiers existants doivent être respectés. Avant de créer un nouveau dossier ou fichier, vérifier l'architecture existante.

5. Fichiers fondamentaux

Fichiers importants :

bootstrap.php
config/database.php
config/constants.php
config/functions.php

Le bootstrap initialise l'environnement commun de l'application. Les fonctions communes et helpers existants doivent être réutilisés avant de créer de nouvelles fonctions équivalentes.

6. Rôles utilisateurs

Super Administrateur

Responsabilités les plus élevées concernant la configuration et la sécurité globale : configuration initiale, paramètres système, structure générale, sécurité, administration globale et supervision.

Il ne doit pas être confondu avec l'administrateur quotidien de l'école.

Administrateur

Gère principalement l'établissement au quotidien : élèves, enseignants, classes, inscriptions, frais scolaires, paiements, notes, présences, parents, organisation académique et paramètres autorisés.

Enseignant

Accède uniquement aux fonctionnalités correspondant à son rôle : classes, élèves, notes, présences, matières, profil et communication selon les permissions.

Élève

Possède un espace personnel. L'accès ne doit pas dépendre obligatoirement d'une adresse e-mail ; privilégier un identifiant scolaire adapté au contexte congolais.

Fonctionnalités possibles : notes, résultats, bulletins, présences, cours, livres, quiz, jeux éducatifs et informations scolaires.

Parent

Peut suivre les informations concernant son ou ses enfants : résultats, notes, présences, paiements, frais scolaires, bulletins, notifications et informations importantes.

Comptable

Responsable principalement des frais scolaires, paiements, reçus, suivi financier, historique des transactions et rapports financiers selon ses permissions.

7. Sécurité et permissions

Toute page protégée par rôle doit vérifier les permissions côté serveur.

Helper existant, par exemple :

requireSuperAdmin();

Ne jamais considérer le masquage d'un bouton ou une protection JavaScript comme une protection de sécurité. La sécurité doit être appliquée côté serveur.

8. Authentification

SmartSchool possède notamment :

connexion ;

sessions ;

rôles ;

mots de passe hashés ;

protection CSRF ;

vérification 2FA selon les fonctionnalités existantes ;

gestion des comptes actifs/inactifs ;

changement de mot de passe obligatoire dans certains cas.

Les mots de passe doivent toujours être stockés sous forme hashée. Ne jamais stocker un mot de passe en clair.

9. Base de données

Base principale :

smartschool

Tables importantes :

users
roles
schools
system_settings
system_setup
academic_years
cycles
levels
password_resets
invitations

ainsi que les autres tables déjà présentes.

Avant de modifier une table :

vérifier sa structure ;

vérifier ses relations ;

vérifier les fichiers PHP qui l'utilisent ;

éviter de casser les données existantes ;

créer une migration si nécessaire.

10. Structure scolaire RDC

La structure académique doit respecter autant que possible le système scolaire congolais.

Cycles actuels

1. Préscolaire
2. Primaire
3. Éducation de base
4. Humanités
5. Technique et professionnel

Le cycle technique et professionnel doit rester présent et être traité comme une partie importante du système.

Niveaux actuels

Primaire
Cycle terminal
Humanités

La structure exacte peut évoluer avec le développement du module académique.

11. Classes et organisation académique

Exemple d'organisation :

Primaire
├── 1ère primaire
│   ├── A
│   └── B
├── 2ème primaire
│   ├── A
│   └── B
...
└── 6ème primaire

Secondaire / Éducation de base
├── 1ère
└── 2ème

Humanités
├── 1ère
├── 2ème
├── 3ème
└── 4ème

Selon l'établissement, différentes options/filières peuvent être utilisées :

Commerciale et gestion ;

Scientifique ;

Pédagogie ;

Littéraire ;

Électricité ;

Coupe et couture ;

autres filières adaptées à l'établissement.

Ne pas imposer toutes les filières à toutes les écoles.

12. Assistant de configuration

SmartSchool possède un assistant de configuration initiale :

superadmin/setup.php

Étapes :

1. Établissement
2. Année scolaire
3. Structure
4. Administrateur
5. Vérification

Il utilise notamment :

system_setup
schools
academic_years
cycles
levels
users

L'assistant est une fonctionnalité importante du Super Administrateur.

13. Invitations

SmartSchool prévoit un système d'invitation pour certains membres du personnel :

enseignants ;

comptables ;

autres utilisateurs autorisés.

Table :

invitations

L'objectif est de permettre une création de compte sécurisée sans devoir créer manuellement chaque compte.

14. Réinitialisation des mots de passe

Table :

password_resets

Les tokens doivent être sécurisés et ne doivent pas être stockés en clair lorsqu'une conception plus sécurisée est utilisée.

15. Fonctionnalités principales prévues

Gestion des élèves

inscription ;

réinscription ;

informations personnelles ;

matricule/numéro scolaire ;

classe ;

historique ;

statut.

Gestion des enseignants

profil ;

numéro du personnel ;

matières ;

classes ;

présence ;

notes ;

invitations.

Gestion des parents

responsables ;

enfants associés ;

suivi scolaire ;

paiements ;

notifications.

Gestion des classes

niveaux ;

sections ;

salles ;

enseignants ;

élèves ;

matières.

Gestion des notes

encodage ;

consultation ;

calcul ;

moyennes ;

résultats ;

bulletins.

Gestion des présences

présence ;

absence ;

retard ;

historique ;

statistiques.

Gestion financière

frais ;

paiements ;

reçus ;

historique ;

rapports.

Communication

notifications ;

messages ;

informations scolaires.

Statistiques

effectifs ;

performances ;

paiements ;

présences ;

indicateurs administratifs.

16. Paiements

Le contexte RDC doit être pris en compte.

Le système doit pouvoir évoluer vers :

paiement bancaire ;

paiement manuel ;

mobile money ;

autres moyens adaptés au contexte local.

Les paiements manuels peuvent nécessiter une validation par l'établissement.

Les reçus doivent pouvoir être générés en PDF.

17. Interface utilisateur

Direction visuelle :

moderne ;

professionnelle ;

minimaliste ;

premium ;

responsive ;

claire ;

adaptée aux utilisateurs non techniques.

Palette générale :

Bleu
Blanc
Noir

Des dégradés légers et effets modernes peuvent être utilisés avec modération.

Le design peut utiliser des cartes, statistiques, graphiques, espaces blancs, micro-interactions et un glassmorphism léger lorsque pertinent.

Éviter les interfaces trop chargées.

18. Icônes

Le projet utilise :

Boxicons

Privilégier Boxicons plutôt que d'introduire inutilement une nouvelle bibliothèque.

19. Compatibilité mobile

SmartSchool doit être responsive sur :

ordinateur ;

tablette ;

smartphone.

Les utilisateurs élèves/parents peuvent particulièrement utiliser un smartphone.

20. Electron

Une version desktop est prévue avec Electron.

L'application desktop est pensée comme une enveloppe autour de l'application web.

URL locale utilisée :

http://localhost/SmartSchool/

L'intégration Electron ne doit pas provoquer une réécriture inutile du backend PHP.

21. Capacitor

Une application mobile est prévue avec Capacitor.

Configuration prévue :

appId: com.smartschool.app
appName: SmartSchool
webDir: www

Le développement mobile doit réutiliser autant que possible les interfaces et fonctionnalités web existantes.

22. Git et GitHub

GitHub est la source centrale du code.

Dépôt :

Roberto-shinga/Roberto-SmartSchool

Branche stable :

main

Règle importante

Ne pas développer directement une nouvelle fonctionnalité sur main.

Créer une branche :

feature/nom-fonctionnalite

Pour une correction :

fix/nom-probleme

Pour une amélioration technique :

refactor/nom

23. Workflow Git recommandé

Avant de commencer :

git checkout main
git pull origin main

Créer une branche :

git checkout -b feature/ma-fonctionnalite

Développer puis vérifier :

git status
git diff

Faire un commit clair :

git add .
git commit -m "feat: description de la fonctionnalité"

Envoyer la branche :

git push -u origin feature/ma-fonctionnalite

Créer ensuite une Pull Request vers main.

Workflow :

Pull Request
    ↓
CI
    ↓
Review
    ↓
Merge
    ↓
main

24. Commits

Utiliser des messages de commit clairs.

Exemples :

feat: add student registration
fix: correct payment calculation
refactor: simplify authentication helper
security: improve password reset validation
docs: update project context
ci: update PHP checks

Éviter les commits vagues comme update, test, changes, stuff ou fix.

25. CI/CD

Le projet utilise GitHub Actions pour vérifier la syntaxe PHP.

Workflow :

.github/workflows/php.yml

Il effectue notamment une vérification de syntaxe des fichiers PHP.

Toute nouvelle modification importante doit conserver la compatibilité avec cette vérification.

26. Fichiers sensibles

Ne jamais envoyer sur GitHub :

.env
secrets/
private/
config/local.php
logs/
uploads/

Les fichiers SQL locaux sont également ignorés selon le .gitignore actuel.

Ne jamais publier :

mots de passe ;

clés API ;

tokens privés ;

identifiants réels ;

secrets SMTP ;

clés de paiement.

27. Règles pour les IA

Toute IA travaillant sur SmartSchool doit respecter les règles suivantes.

Avant de modifier du code

Lire le fichier concerné.

Comprendre son fonctionnement.

Vérifier les fonctions déjà disponibles.

Vérifier les tables utilisées.

Vérifier les relations SQL si nécessaire.

Vérifier les permissions.

Vérifier les dépendances avec les autres fichiers.

Ne pas réécrire un fichier complet sans nécessité.

Ne pas inventer l'architecture

Toujours respecter l'architecture existante.

Avant de créer une table, route, fonction, fichier, classe ou API, vérifier si un équivalent existe déjà.

Ne pas supprimer sans raison

Ne jamais supprimer une fonctionnalité existante simplement pour simplifier le code.

Si une fonctionnalité doit être remplacée :

expliquer pourquoi ;

vérifier les dépendances ;

préserver la compatibilité ;

tester.

28. Base de données et migrations

Avant toute modification SQL :

Vérifier → comprendre → migrer → tester

Éviter les modifications destructives.

Ne pas supprimer une colonne ou une table utilisée sans plan de migration.

29. Sécurité

Toute nouvelle fonctionnalité doit prendre en compte :

authentification ;

autorisation ;

CSRF ;

validation ;

échappement HTML ;

injections SQL ;

gestion des sessions ;

contrôle des fichiers uploadés ;

protection des données sensibles.

Utiliser PDO et les requêtes préparées.

30. Style de développement

Le code doit être :

lisible ;

maintenable ;

cohérent ;

documenté lorsque nécessaire ;

compatible avec PHP 8.2 ;

réutilisable.

Éviter :

duplication inutile ;

logique métier dispersée ;

variables ambiguës ;

code mort ;

hacks temporaires non documentés.

31. Tests après modification

Après une modification PHP importante :

php -l fichier.php

Puis tester la fonctionnalité dans le navigateur.

Pour une modification SQL :

vérifier la structure ;

vérifier les données ;

tester les fonctionnalités dépendantes.

Pour une fonctionnalité critique :

test positif
test négatif
test permissions
test utilisateur non autorisé
test données invalides

32. Collaboration avec plusieurs IA

Plusieurs assistants peuvent travailler sur SmartSchool :

Claude
ChatGPT
Codex
VS Code

GitHub doit rester la source de vérité du code.

Une conversation IA ne doit jamais être considérée comme la seule mémoire du projet.

Ce fichier :

docs/AI_CONTEXT.md

sert de contexte technique commun.

33. Modifications concurrentes

Si une autre IA a récemment modifié le projet :

git checkout main
git pull origin main

avant de commencer une nouvelle tâche.

Ne jamais écraser silencieusement le travail d'une autre branche.

En cas de conflit :

STOP
↓
analyser le conflit
↓
comprendre les deux modifications
↓
résoudre volontairement
↓
tester

34. État actuel du projet

Le projet possède notamment :

authentification ;

gestion des rôles ;

administration ;

gestion académique ;

gestion des élèves ;

gestion des enseignants ;

gestion des paiements ;

paramètres ;

Super Administrateur ;

assistant de configuration ;

invitations ;

réinitialisation de mot de passe ;

structure académique ;

Git/GitHub ;

GitHub Actions.

Assistant de configuration :

superadmin/setup.php

35. Dernières modifications importantes

Une fonctionnalité d'assistant de configuration a été intégrée.

Commit :

feat: add SmartSchool setup wizard

Un workflow Symfony incorrect a été remplacé par un workflow adapté au projet PHP.

Commit :

ci: replace Symfony workflow with PHP checks

Ces modifications ont été fusionnées dans la branche principale.

36. Situation Git actuelle

La branche locale :

main

est synchronisée avec :

origin/main

État confirmé :

On branch main
Your branch is up to date with 'origin/main'.

nothing to commit, working tree clean

37. Prochaines priorités

Les prochaines fonctionnalités doivent être développées progressivement.

Priorités possibles :

stabiliser l'architecture ;

finaliser les permissions ;

finaliser la structure académique ;

gestion complète des élèves ;

gestion complète des enseignants ;

gestion des classes ;

gestion des matières ;

gestion des notes ;

gestion des présences ;

gestion financière ;

bulletins ;

notifications ;

statistiques ;

responsive/mobile ;

Electron ;

Capacitor ;

sécurité avancée ;

déploiement en production.

L'ordre peut changer selon les décisions du propriétaire du projet.

38. Consigne principale pour toute IA

Avant toute modification de SmartSchool :

Comprendre d'abord, modifier ensuite.

L'IA doit :

Lire
 ↓
Analyser
 ↓
Identifier les dépendances
 ↓
Proposer la modification
 ↓
Modifier
 ↓
Tester
 ↓
Vérifier Git

Ne jamais supposer qu'un fichier ou une fonctionnalité n'existe pas avant de vérifier le dépôt.

Ne jamais inventer une structure déjà présente dans le projet.

Ne jamais sacrifier les fonctionnalités existantes pour produire rapidement du nouveau code.

SmartSchool doit évoluer comme un logiciel professionnel, maintenable, sécurisé et adapté au contexte scolaire de la RDC