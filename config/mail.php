<?php
// ============================================================
//  SmartSchool RDC — Configuration SMTP
//  Fichier : config/mail.php
//
//  Tant que SMTP_HOST est vide, le systeme reste en mode
//  "developpement" : aucun email reel n'est envoye, mais les
//  codes/liens s'affichent a l'ecran pour pouvoir tester quand meme.
//
//  Pour activer l'envoi reel, remplis les valeurs ci-dessous puis
//  repasse APP_ENV a 'production' dans config/constants.php quand
//  tu es pret (sinon le bandeau de debug continuera a s'afficher
//  EN PLUS de l'envoi reel, ce qui est pratique pour verifier que
//  ca fonctionne avant de le desactiver).
// ============================================================

// ── Exemple avec Gmail ──────────────────────────────────────
// SMTP_HOST     : smtp.gmail.com
// SMTP_PORT     : 587
// SMTP_USER     : tonadresse@gmail.com
// SMTP_PASS     : un "mot de passe d'application" (PAS ton mot de passe Gmail normal)
//                 -> a generer sur https://myaccount.google.com/apppasswords
//                 (necessite la validation en 2 etapes activee sur le compte Google)
// SMTP_ENCRYPTION : tls
//
// ── Autres fournisseurs courants ─────────────────────────────
// Outlook/Office365 : smtp.office365.com, port 587, encryption tls
// Yahoo              : smtp.mail.yahoo.com, port 587, encryption tls
// Un hebergeur cPanel : demande a ton hebergeur (souvent mail.tondomaine.cd, port 465, ssl)

define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_USER', 'robertoshinga20@gmail.com');   // ton adresse Gmail complète
define('SMTP_PASS',       'vszhstxsbmmsdwkl');            // le code à 16 caractères, espaces ou pas, les deux marchent
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_DEBUG', true);

define('MAIL_FROM_ADDRESS', SMTP_USER ?: 'no-reply@smartschool.local');
define('MAIL_FROM_NAME',    'SmartSchool RDC V2');

